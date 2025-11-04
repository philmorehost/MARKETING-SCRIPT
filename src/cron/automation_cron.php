<?php
// This cron job should be run every minute.
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/functions.php';

// --- Process New Contacts ---
// Find contacts who were added to a list with an active automation since their last automation check
$new_contacts_sql = "
    SELECT c.id as contact_id, a.id as automation_id
    FROM contacts c
    JOIN contact_list_map clm ON c.id = clm.contact_id
    JOIN automations a ON clm.list_id = a.trigger_list_id
    WHERE a.status = 'active'
    AND c.created_at >= NOW() - INTERVAL 1 MINUTE
";
$new_contacts_result = $mysqli->query($new_contacts_sql);

while ($row = $new_contacts_result->fetch_assoc()) {
    // Find the first step of the automation
    $first_step_stmt = $mysqli->prepare("SELECT id FROM automation_steps WHERE automation_id = ? ORDER BY id ASC LIMIT 1");
    $first_step_stmt->bind_param('i', $row['automation_id']);
    $first_step_stmt->execute();
    $first_step = $first_step_stmt->get_result()->fetch_assoc();

    if ($first_step) {
        // Add the contact to the automation queue for the first step
        $queue_stmt = $mysqli->prepare("INSERT INTO automation_queue (automation_step_id, contact_id, status) VALUES (?, ?, 'pending')");
        $queue_stmt->bind_param('ii', $first_step['id'], $row['contact_id']);
        $queue_stmt->execute();
    }
}


// --- Process Pending Queue Steps ---
$pending_steps_sql = "
    SELECT aq.id as queue_id, aq.contact_id, s.type, s.wait_days, s.email_campaign_id_template, a.team_id, a.user_id, a.id as automation_id
    FROM automation_queue aq
    JOIN automation_steps s ON aq.automation_step_id = s.id
    JOIN automations a ON s.automation_id = a.id
    WHERE aq.status = 'pending' AND a.status = 'active'
";
$pending_steps_result = $mysqli->query($pending_steps_sql);

while ($step_to_process = $pending_steps_result->fetch_assoc()) {
    if ($step_to_process['type'] === 'wait') {
        // If the step is a 'wait', update its status and set the 'process_at' time
        $process_at = date('Y-m-d H:i:s', strtotime("+{$step_to_process['wait_days']} days"));
        $update_queue_stmt = $mysqli->prepare("UPDATE automation_queue SET status = 'waiting', process_at = ? WHERE id = ?");
        $update_queue_stmt->bind_param('si', $process_at, $step_to_process['queue_id']);
        $update_queue_stmt->execute();

    } elseif ($step_to_process['type'] === 'send_email') {
        $price_per_email = (float)get_setting('price_per_email_send', $mysqli, 1);
        $team_owner_id_stmt = $mysqli->prepare("SELECT owner_user_id FROM teams WHERE id = ?");
        $team_owner_id_stmt->bind_param('i', $step_to_process['team_id']);
        $team_owner_id_stmt->execute();
        $team_owner_id = $team_owner_id_stmt->get_result()->fetch_assoc()['owner_user_id'];

        $balance_stmt = $mysqli->prepare("SELECT credit_balance FROM users WHERE id = ?");
        $balance_stmt->bind_param('i', $team_owner_id);
        $balance_stmt->execute();
        $balance = (float)$balance_stmt->get_result()->fetch_assoc()['credit_balance'];

        if ($balance >= $price_per_email) {
            $mysqli->begin_transaction();
            try {
                // Deduct credits
                $deduct_stmt = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance - ? WHERE id = ?");
                $deduct_stmt->bind_param('di', $price_per_email, $team_owner_id);
                $deduct_stmt->execute();

                // Add to campaign_queue
                $contact_email_stmt = $mysqli->prepare("SELECT email FROM contacts WHERE id = ?");
                $contact_email_stmt->bind_param('i', $step_to_process['contact_id']);
                $contact_email_stmt->execute();
                $contact_email = $contact_email_stmt->get_result()->fetch_assoc()['email'];

                $queue_email_stmt = $mysqli->prepare("INSERT INTO campaign_queue (campaign_id, contact_id, email_address, status) VALUES (?, ?, ?, 'pending')");
                $queue_email_stmt->bind_param('iis', $step_to_process['email_campaign_id_template'], $step_to_process['contact_id'], $contact_email);
                $queue_email_stmt->execute();

                // Mark this step as complete
                $update_queue_stmt = $mysqli->prepare("UPDATE automation_queue SET status = 'completed' WHERE id = ?");
                $update_queue_stmt->bind_param('i', $step_to_process['queue_id']);
                $update_queue_stmt->execute();

                $mysqli->commit();

                // Find and queue the next step (outside transaction)
                $next_step_stmt = $mysqli->prepare("SELECT id FROM automation_steps WHERE automation_id = ? AND id > (SELECT automation_step_id FROM automation_queue WHERE id = ? ) ORDER BY id ASC LIMIT 1");
                $next_step_stmt->bind_param('ii', $step_to_process['automation_id'], $step_to_process['queue_id']);
                $next_step_stmt->execute();
                $next_step = $next_step_stmt->get_result()->fetch_assoc();
                if ($next_step) {
                    $queue_next_stmt = $mysqli->prepare("INSERT INTO automation_queue (automation_step_id, contact_id, status) VALUES (?, ?, 'pending')");
                    $queue_next_stmt->bind_param('ii', $next_step['id'], $step_to_process['contact_id']);
                    $queue_next_stmt->execute();
                }

            } catch (Exception $e) {
                $mysqli->rollback();
            }
        }
        // If not enough credits, the step remains 'pending' and will be retried.
    }
}


// --- Process Waiting Steps ---
// Find steps where the 'wait' period is over
$waiting_steps_sql = "SELECT id, contact_id, automation_step_id FROM automation_queue WHERE status = 'waiting' AND process_at <= NOW()";
$waiting_steps_result = $mysqli->query($waiting_steps_sql);

while($waiting_step = $waiting_steps_result->fetch_assoc()){
    // Mark the waiting step as complete
    $mysqli->query("UPDATE automation_queue SET status = 'completed' WHERE id = {$waiting_step['id']}");

    // Find and queue the next step
    $automation_id_stmt = $mysqli->prepare("SELECT automation_id FROM automation_steps WHERE id = ?");
    $automation_id_stmt->bind_param('i', $waiting_step['automation_step_id']);
    $automation_id_stmt->execute();
    $automation_id = $automation_id_stmt->get_result()->fetch_assoc()['automation_id'];

    $next_step_stmt = $mysqli->prepare("SELECT id FROM automation_steps WHERE automation_id = ? AND id > ? ORDER BY id ASC LIMIT 1");
    $next_step_stmt->bind_param('ii', $automation_id, $waiting_step['automation_step_id']);
    $next_step_stmt->execute();
    $next_step = $next_step_stmt->get_result()->fetch_assoc();
    if ($next_step) {
        $queue_next_stmt = $mysqli->prepare("INSERT INTO automation_queue (automation_step_id, contact_id, status) VALUES (?, ?, 'pending')");
        $queue_next_stmt->bind_param('ii', $next_step['id'], $waiting_step['contact_id']);
        $queue_next_stmt->execute();
    }
}
        $next_step_stmt = $mysqli->prepare("SELECT id FROM automation_steps WHERE automation_id = ? AND id > ? ORDER BY id ASC LIMIT 1");
        $next_step_stmt->bind_param('ii', $step_to_process['automation_id'], $step_to_process['id']);
        $next_step_stmt->execute();
        $next_step = $next_step_stmt->get_result()->fetch_assoc();
        if ($next_step) {
            $queue_next_stmt = $mysqli->prepare("INSERT INTO automation_queue (automation_step_id, contact_id, status) VALUES (?, ?, 'pending')");
            $queue_next_stmt->bind_param('ii', $next_step['id'], $step_to_process['contact_id']);
            $queue_next_stmt->execute();
        }
    }
}


// --- Process Waiting Steps ---
// Find steps where the 'wait' period is over
$waiting_steps_sql = "SELECT id, contact_id, automation_step_id FROM automation_queue WHERE status = 'waiting' AND process_at <= NOW()";
$waiting_steps_result = $mysqli->query($waiting_steps_sql);

while($waiting_step = $waiting_steps_result->fetch_assoc()){
    // Mark the waiting step as complete
    $mysqli->query("UPDATE automation_queue SET status = 'completed' WHERE id = {$waiting_step['id']}");

    // Find and queue the next step
    $automation_id_stmt = $mysqli->prepare("SELECT automation_id FROM automation_steps WHERE id = ?");
    $automation_id_stmt->bind_param('i', $waiting_step['automation_step_id']);
    $automation_id_stmt->execute();
    $automation_id = $automation_id_stmt->get_result()->fetch_assoc()['automation_id'];

    $next_step_stmt = $mysqli->prepare("SELECT id FROM automation_steps WHERE automation_id = ? AND id > ? ORDER BY id ASC LIMIT 1");
    $next_step_stmt->bind_param('ii', $automation_id, $waiting_step['automation_step_id']);
    $next_step_stmt->execute();
    $next_step = $next_step_stmt->get_result()->fetch_assoc();
    if ($next_step) {
        $queue_next_stmt = $mysqli->prepare("INSERT INTO automation_queue (automation_step_id, contact_id, status) VALUES (?, ?, 'pending')");
        $queue_next_stmt->bind_param('ii', $next_step['id'], $waiting_step['contact_id']);
        $queue_next_stmt->execute();
    }
}
