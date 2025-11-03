<?php
// This script should be run every minute via a cron job.
// Example: * * * * * /usr/bin/php /path/to/your/project/src/cron/sms_cron.php >> /path/to/your/project/logs/sms.log 2>&1

define('APP_ROOT', dirname(__DIR__, 2)); require_once APP_ROOT . '/config/db.php';
require_once APP_ROOT . '/src/lib/functions.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// --- Fetch API Settings ---
$api_key = get_setting('philmorsms_api_key', $mysqli);
$default_sender_id = get_setting('philmorsms_sender_id', $mysqli);
$api_url = 'https://app.philmoresms.com/api/sms.php';

if (empty($api_key)) {
    echo "PhilmoreSMS API key is not set. Exiting.\n";
    exit;
}

// --- Fetch a batch of pending SMS messages ---
$limit = 100; // Process 100 messages per run
$stmt = $mysqli->prepare(
    "SELECT q.id, q.phone_number, c.sender_id, c.message_body
     FROM sms_queue q
     JOIN sms_campaigns c ON q.sms_campaign_id = c.id
     WHERE q.status = 'queued'
     ORDER BY q.id ASC
     LIMIT ?"
);
$stmt->bind_param('i', $limit);
$stmt->execute();
$pending_sms = $stmt->get_result();

if ($pending_sms->num_rows === 0) {
    echo "No pending SMS to send.\n";
    exit;
}

// --- Process each SMS ---
while ($sms = $pending_sms->fetch_assoc()) {
    $queue_id = $sms['id'];
    $recipient = $sms['phone_number'];
    $sender_id = !empty($sms['sender_id']) ? $sms['sender_id'] : $default_sender_id;
    $message = $sms['message_body'];

    // Update status to 'sending' to prevent reprocessing
    $update_stmt = $mysqli->prepare("UPDATE sms_queue SET status = 'sending' WHERE id = ?");
    $update_stmt->bind_param('i', $queue_id);
    $update_stmt->execute();

    // --- Make API Call to PhilmoreSMS ---
    $postData = http_build_query([
        'token' => $api_key,
        'senderID' => $sender_id,
        'recipients' => $recipient,
        'message' => $message
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    $final_status = '';
    $api_message_id = null;

    if ($err) {
        $final_status = 'failed';
        echo "cURL Error for queue ID {$queue_id}: {$err}\n";
    } else {
        // Assuming the API returns a simple success/fail or a JSON response.
        // This part needs to be adapted based on the actual API response format.
        // Let's assume 'success' means it was accepted by the API.
        if (strpos(strtolower($response), 'success') !== false) {
            $final_status = 'sent'; // Or 'delivered' if the API confirms that
            // You might want to parse the response to get a message ID
            // e.g., $api_response = json_decode($response, true); $api_message_id = $api_response['message_id'];
            echo "Successfully sent SMS for queue ID {$queue_id}.\n";
        } else {
            $final_status = 'failed';
            echo "API Error for queue ID {$queue_id}: {$response}\n";
        }
    }

    // --- Update Queue Record ---
    $result_stmt = $mysqli->prepare("UPDATE sms_queue SET status = ?, api_message_id = ? WHERE id = ?");
    $result_stmt->bind_param('ssi', $final_status, $api_message_id, $queue_id);
    $result_stmt->execute();
}

echo "SMS cron run finished.\n";
$mysqli->close();
