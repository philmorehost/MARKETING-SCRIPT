<?php
// src/cron/sms_cron.php
// This script is run by a cron job every minute.

if (PHP_SAPI !== 'cli') {
    die('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/src/lib/functions.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    echo "Database connection failed: " . $mysqli->connect_error . "\n";
    exit(1);
}

echo "Running SMS Cron Job...\n";

// --- API Details ---
$api_token = get_setting('philmorsms_api_key');
$api_url = 'https://app.philmoresms.com/api/sms.php';

if (empty($api_token)) {
    echo "PhilmoreSMS API key is not configured. Exiting.\n";
    exit(1);
}

// --- Fetch a batch of SMS to send ---
$batch_size = 100; // Send 100 messages per run
$stmt = $mysqli->prepare("
    SELECT sq.id, sq.phone_number, sc.sender_id, sc.message_body
    FROM sms_queue sq
    JOIN sms_campaigns sc ON sq.sms_campaign_id = sc.id
    WHERE sq.status = 'queued'
    LIMIT ?
");
$stmt->bind_param("i", $batch_size);
$stmt->execute();
$result = $stmt->get_result();
$messages_to_send = $result->fetch_all(MYSQLI_ASSOC);

if (empty($messages_to_send)) {
    echo "No SMS in queue. Exiting.\n";
    exit(0);
}

echo "Found " . count($messages_to_send) . " messages to send.\n";
$update_stmt = $mysqli->prepare("UPDATE sms_queue SET status = ?, api_message_id = ? WHERE id = ?");

// --- Group messages by sender and body to send in batches ---
$campaign_batches = [];
foreach ($messages_to_send as $msg) {
    $key = $msg['sender_id'] . '::' . $msg['message_body'];
    if (!isset($campaign_batches[$key])) {
        $campaign_batches[$key] = [
            'sender_id' => $msg['sender_id'],
            'message' => $msg['message_body'],
            'recipients' => [],
            'queue_ids' => []
        ];
    }
    $campaign_batches[$key]['recipients'][] = $msg['phone_number'];
    $campaign_batches[$key]['queue_ids'][] = $msg['id'];
}


// --- Send Batches to API ---
foreach ($campaign_batches as $batch) {
    $postData = [
        'token' => $api_token,
        'senderID' => $batch['sender_id'],
        'recipients' => implode(',', $batch['recipients']),
        'message' => $batch['message'],
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $api_response = json_decode($response, true);

    // --- Update Queue Status ---
    // This is a simplified update. A real implementation would parse the response
    // to match individual message IDs if the API provides them.
    if ($http_code == 200 && isset($api_response['status']) && $api_response['status'] === 'success') {
        $status = 'sent';
        $message_id = $api_response['message_id'] ?? 'batch_' . time();
        echo "  - Batch sent successfully. Sender: {$batch['sender_id']}\n";
    } else {
        $status = 'failed';
        $message_id = $response;
         echo "  - Batch failed. Sender: {$batch['sender_id']}. Response: $response\n";
    }

    foreach ($batch['queue_ids'] as $queue_id) {
        $update_stmt->bind_param("ssi", $status, $message_id, $queue_id);
        $update_stmt->execute();
    }
}

echo "Cron job complete.\n";
$mysqli->close();
exit(0);
