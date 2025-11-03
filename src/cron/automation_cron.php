<?php
// --- automation_cron.php ---
// Runs every minute to process automation triggers and steps.

require_once dirname(__FILE__) . '/../../config/db.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// --- 1. Process Triggers: New contacts added to a list ---
$sql = "SELECT a.id as automation_id, a.trigger_list_id, c.id as contact_id
        FROM automations a
        JOIN contacts c ON a.trigger_list_id = (SELECT list_id FROM contact_list_map WHERE contact_id = c.id ORDER BY list_id DESC LIMIT 1)
        WHERE c.created_at > (NOW() - INTERVAL 1 MINUTE)";
$new_contacts = $mysqli->query($sql);

while ($contact_trigger = $new_contacts->fetch_assoc()) {
    $automation_id = $contact_trigger['automation_id'];
    $contact_id = $contact_trigger['contact_id'];

    // Get the first step of the automation
    $first_step = $mysqli->query("SELECT id FROM automation_steps WHERE automation_id = $automation_id ORDER BY step_order ASC, id ASC LIMIT 1")->fetch_assoc();
    if ($first_step) {
        $step_id = $first_step['id'];
        // Add to automation queue
        $mysqli->query("INSERT INTO automation_queue (automation_step_id, contact_id, status) VALUES ($step_id, $contact_id, 'pending')");
        echo "Queued contact {$contact_id} for automation {$automation_id}.\n";
    }
}

// --- 2. Process Pending Steps in the Queue ---
$pending_steps = $mysqli->query("SELECT q.id, q.automation_step_id, q.contact_id, s.type, s.wait_days, s.email_campaign_id_template FROM automation_queue q JOIN automation_steps s ON q.automation_step_id = s.id WHERE q.status = 'pending'");

while ($step_to_run = $pending_steps->fetch_assoc()) {
    $queue_id = $step_to_run['id'];
    $current_step_id = $step_to_run['automation_step_id'];
    $contact_id = $step_to_run['contact_id'];

    // Mark as processing
    $mysqli->query("UPDATE automation_queue SET status = 'processing' WHERE id = $queue_id");

    if ($step_to_run['type'] === 'wait') {
        // Schedule the next step
        $wait_days = $step_to_run['wait_days'];
        $next_step_id = 0; // Logic to get next step in sequence
        // UPDATE automation_queue SET status = 'waiting', scheduled_at = NOW() + INTERVAL $wait_days DAY, automation_step_id = $next_step_id

    } elseif ($step_to_run['type'] === 'send_email') {
        // Queue the email to be sent by campaign_cron.php
        // 1. Get contact email
        // 2. Get email template
        // 3. Deduct credits
        // 4. Insert into campaign_queue

        // Then, queue the next step
    }

    // Mark as complete
    $mysqli->query("UPDATE automation_queue SET status = 'completed' WHERE id = $queue_id");
    echo "Processed queue item {$queue_id}.\n";
}

echo "Automation cron finished.\n";
