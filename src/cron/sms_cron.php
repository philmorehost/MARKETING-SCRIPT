<?php
// This cron job should be run every minute.
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/functions.php';

$api_token = get_setting('philmorsms_api_key', $mysqli);
$sender_id = get_setting('philmorsms_sender_id', $mysqli);

if (empty($api_token) || empty($sender_id)) {
    // Log error: SMS API not configured
    exit;
}

// Fetch a batch of pending SMS messages
$limit = 100; // Process 100 messages per run
$stmt = $mysqli->prepare("
    SELECT q.id, q.phone_number, sc.message_body
    FROM sms_queue q
    JOIN sms_campaigns sc ON q.sms_campaign_id = sc.id
    WHERE q.status = 'pending'
    LIMIT ?
");
$stmt->bind_param('i', $limit);
$stmt->execute();
$messages_to_send = $stmt->get_result();

if ($messages_to_send->num_rows > 0) {
    $update_stmt = $mysqli->prepare("UPDATE sms_queue SET status = ?, api_message_id = ? WHERE id = ?");

    while ($msg = $messages_to_send->fetch_assoc()) {
        $postData = [
            'token' => $api_token,
            'senderID' => $sender_id,
            'recipients' => $msg['phone_number'],
            'message' => $msg['message_body'],
        ];

        $ch = curl_init('https://app.philmoresms.com/api/sms.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        $status = 'failed';
        $api_message_id = null;
        if (isset($result['status']) && $result['status'] === 'success') {
            $status = 'sent';
            $api_message_id = $result['message_id'] ?? null;
        }

        $update_stmt->bind_param('ssi', $status, $api_message_id, $msg['id']);
        $update_stmt->execute();
    }
}

// Mark campaigns as completed
$mysqli->query("
    UPDATE sms_campaigns sc
    SET status = 'Completed'
    WHERE sc.status = 'queued'
    AND NOT EXISTS (
        SELECT 1 FROM sms_queue sq WHERE sq.sms_campaign_id = sc.id AND sq.status = 'pending'
    )
");
