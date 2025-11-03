<?php
// --- sms_cron.php ---
// This script should be executed by a cron job every minute.
// e.g., * * * * * php /path/to/your/project/src/cron/sms_cron.php

require_once dirname(__FILE__) . '/../../config/db.php';
require_once dirname(__FILE__) . '/../lib/functions.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// --- Settings ---
$api_token = get_setting('philmorsms_api_key', $mysqli);
$default_sender_id = get_setting('philmorsms_sender_id', $mysqli);
$batch_limit = 100; // Number of SMS to send per cron run

if (empty($api_token)) {
    // Log this error in a real application
    die("PhilmoreSMS API key is not set.");
}

// --- Main Logic ---

// Fetch a batch of queued SMS messages
$stmt = $mysqli->prepare(
    "SELECT q.id, q.phone_number, c.sender_id, c.message_body
     FROM sms_queue q
     JOIN sms_campaigns c ON q.sms_campaign_id = c.id
     WHERE q.status = 'queued'
     ORDER BY q.id ASC
     LIMIT ?"
);
$stmt->bind_param('i', $batch_limit);
$stmt->execute();
$queue_items = $stmt->get_result();

if ($queue_items->num_rows === 0) {
    echo "No pending SMS to send.\n";
    exit;
}

while ($item = $queue_items->fetch_assoc()) {
    $queue_id = $item['id'];
    $recipient = $item['phone_number'];
    $sender_id = $item['sender_id'] ?: $default_sender_id;
    $message = $item['message_body'];

    // Update status to 'sending' to prevent re-processing
    $mysqli->query("UPDATE sms_queue SET status = 'sending' WHERE id = $queue_id");

    // --- PhilmoreSMS API Call ---
    $postData = http_build_query([
        'token' => $api_token,
        'senderID' => $sender_id,
        'recipients' => $recipient,
        'message' => $message
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://app.philmoresms.com/api/sms.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // --- Process Response & Update Queue ---
    if ($http_code == 200) {
        // Assuming a successful API call means the message is sent.
        // A more robust solution would check the response body for a success message
        // and a message_id.
        $update_stmt = $mysqli->prepare("UPDATE sms_queue SET status = 'sent', api_message_id = ? WHERE id = ?");
        $api_response_id = $response; // Storing raw response for now
        $update_stmt->bind_param('si', $api_response_id, $queue_id);
        $update_stmt->execute();
        echo "Successfully sent SMS to {$recipient}\n";
    } else {
        // API call failed
        $update_stmt = $mysqli->prepare("UPDATE sms_queue SET status = 'failed' WHERE id = ?");
        $update_stmt->bind_param('i', $queue_id);
        $update_stmt->execute();
         echo "Failed to send SMS to {$recipient}. HTTP Code: {$http_code}\n";
    }
}

echo "Cron run finished.\n";
