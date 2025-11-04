<?php
// --- automation_cron.php ---
// Runs every minute to process automation triggers and steps.

define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/config/db.php';
require_once APP_ROOT . '/src/lib/functions.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) { die("DB connection error"); }

$price_per_email = (float)get_setting('price_per_email_send', $mysqli, 1);

// --- 1. Process Triggers --- (Refactored in previous step)
$trigger_sql = "
    SELECT a.id as automation_id, c.id as contact_id
    FROM contact_list_map clm
    JOIN contacts c ON clm.contact_id = c.id
    JOIN automations a ON clm.list_id = a.trigger_list_id
    LEFT JOIN automation_queue aq ON a.id = (SELECT s.automation_id FROM automation_steps s WHERE s.id = aq.automation_step_id) AND aq.contact_id = c.id
    WHERE a.status = 'active' AND c.created_at >= NOW() - INTERVAL 2 MINUTE AND aq.id IS NULL
";
$new_triggers = $mysqli->query($trigger_sql);

if ($new_triggers) {
    $stmt_first_step = $mysqli->prepare("SELECT id FROM automation_steps WHERE automation_id = ? ORDER BY id ASC LIMIT 1");
    $stmt_insert_queue = $mysqli->prepare("INSERT INTO automation_queue (automation_step_id, contact_id, status, scheduled_at) VALUES (?, ?, 'pending', NOW())");
    while ($trigger = $new_triggers->fetch_assoc()) {
        $stmt_first_step->bind_param('i', $trigger['automation_id']);
        $stmt_first_step->execute();
        $first_step_res = $stmt_first_step->get_result();
        if ($first_step_res && $first_step_res->num_rows > 0) {
            $step_id = $first_step_res->fetch_assoc()['id'];
            $stmt_insert_queue->bind_param('ii', $step_id, $trigger['contact_id']);
            $stmt_insert_queue->execute();
            echo "Contact {$trigger['contact_id']} started automation {$trigger['automation_id']}.\n";
        }
    }
}


// --- 2. Process Pending Steps in the Queue ---
$queue_sql = "SELECT q.id as queue_id, q.automation_step_id, q.contact_id, s.automation_id, s.type, s.wait_days, s.email_campaign_id_template, a.user_id, a.team_id FROM automation_queue q JOIN automation_steps s ON q.automation_step_id = s.id JOIN automations a ON s.automation_id = a.id WHERE q.status = 'pending' AND q.scheduled_at <= NOW()";
$pending_steps = $mysqli->query($queue_sql);

if($pending_steps) {
    while ($step_to_run = $pending_steps->fetch_assoc()) {
        $queue_id = $step_to_run['queue_id'];
        $mysqli->query("UPDATE automation_queue SET status = 'processing' WHERE id = {$queue_id}");

        $next_step_id = get_next_step($mysqli, $step_to_run['automation_id'], $step_to_run['automation_step_id']);

        if ($step_to_run['type'] === 'wait') {
             $wait_days = (int)$step_to_run['wait_days'];
            if ($next_step_id) {
                $mysqli->query("UPDATE automation_queue SET automation_step_id = {$next_step_id}, status = 'pending', scheduled_at = NOW() + INTERVAL {$wait_days} DAY WHERE id = {$queue_id}");
            } else {
                $mysqli->query("DELETE FROM automation_queue WHERE id = {$queue_id}");
            }
        } elseif ($step_to_run['type'] === 'send_email') {
            $contact_email = get_contact_email($mysqli, $step_to_run['contact_id']);
            $team_owner_id = get_team_owner_id($mysqli, $step_to_run['team_id']);

            if ($contact_email && $team_owner_id) {
                $user_balance = get_user_balance($mysqli, $team_owner_id);

                if ($user_balance >= $price_per_email) {
                    $mysqli->begin_transaction();
                    try {
                        $stmt_deduct = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance - ? WHERE id = ?");
                        $stmt_deduct->bind_param('di', $price_per_email, $team_owner_id);
                        $stmt_deduct->execute();

                        $stmt_queue_email = $mysqli->prepare("INSERT INTO campaign_queue (campaign_id, contact_id, email_address, status) VALUES (?, ?, ?, 'queued')");
                        $stmt_queue_email->bind_param('iis', $step_to_run['email_campaign_id_template'], $step_to_run['contact_id'], $contact_email);
                        $stmt_queue_email->execute();

                        $mysqli->commit();
                        echo "Queue {$queue_id}: Email queued for contact {$step_to_run['contact_id']}.\n";

                        if ($next_step_id) {
                             $mysqli->query("UPDATE automation_queue SET automation_step_id = {$next_step_id}, status = 'pending', scheduled_at = NOW() WHERE id = {$queue_id}");
                        } else {
                            $mysqli->query("DELETE FROM automation_queue WHERE id = {$queue_id}");
                        }
                    } catch (Exception $e) {
                        $mysqli->rollback();
                        echo "Queue {$queue_id}: Transaction failed. Error: " . $e->getMessage() . "\n";
                    }
                } else {
                    echo "Queue {$queue_id}: Insufficient credits. Pausing.\n";
                    $mysqli->query("UPDATE automation_queue SET status = 'paused' WHERE id = {$queue_id}");
                }
            }
        }
    }
}

function get_next_step($db, $automation_id, $current_step_id) {
    // (This function already uses prepared statements)
    $stmt = $db->prepare("SELECT id FROM automation_steps WHERE automation_id = ? AND id > ? ORDER BY id ASC LIMIT 1");
    $stmt->bind_param('ii', $automation_id, $current_step_id);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->num_rows > 0 ? $res->fetch_assoc()['id'] : null;
}
function get_team_owner_id($db, $team_id) {
    $stmt = $db->prepare("SELECT owner_user_id FROM teams WHERE id = ?");
    $stmt->bind_param('i', $team_id);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->num_rows > 0 ? $res->fetch_assoc()['owner_user_id'] : null;
}
function get_contact_email($db, $contact_id) {
    $stmt = $db->prepare("SELECT email FROM contacts WHERE id = ? AND email IS NOT NULL");
    $stmt->bind_param('i', $contact_id);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->num_rows > 0 ? $res->fetch_assoc()['email'] : null;
}
function get_user_balance($db, $user_id) {
    $stmt = $db->prepare("SELECT credit_balance FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->num_rows > 0 ? (float)$res->fetch_assoc()['credit_balance'] : 0;
}

echo "Automation cron finished.\n";
$mysqli->close();
