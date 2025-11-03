<?php
// --- webhook_whatsapp.php ---
// Endpoint for Gupshup/Meta to post message status updates.

require_once '../../config/db.php';
$log_file = dirname(__FILE__) . '/../../logs/whatsapp_webhook.log';

// --- Security ---
// Meta uses a Hub Verify Token for setup and a signature for ongoing requests.
// Gupshup also has its own verification methods. This is a placeholder.
$hub_verify_token = null;
if (isset($_REQUEST['hub_challenge'])) {
    // Handle Meta's verification challenge
    if ($_REQUEST['hub_verify_token'] === $hub_verify_token) {
        echo $_REQUEST['hub_challenge'];
    }
    exit;
}

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    file_put_contents($log_file, "Database connection error\n", FILE_APPEND);
    http_response_code(500); exit;
}

$raw_payload = file_get_contents('php://input');
file_put_contents($log_file, "Received payload: {$raw_payload}\n", FILE_APPEND);
$data = json_decode($raw_payload, true);

if (!$data) {
    file_put_contents($log_file, "Invalid JSON received.\n", FILE_APPEND);
    http_response_code(400); exit;
}

$message_id = null;
$status = null;

// --- Detect format and extract data ---

// Meta Cloud API format
if (isset($data['entry'][0]['changes'][0]['value']['statuses'])) {
    $status_data = $data['entry'][0]['changes'][0]['value']['statuses'][0];
    $message_id = $status_data['id'];
    $status = $status_data['status']; // e.g., 'sent', 'delivered', 'read', 'failed'
    file_put_contents($log_file, "Detected Meta format. Message ID: {$message_id}, Status: {$status}\n", FILE_APPEND);
}
// Gupshup format (this is an assumed structure)
elseif (isset($data['payload']['id']) && isset($data['payload']['type'])) {
    $payload = $data['payload'];
    $message_id = $payload['id'];
    $status = $payload['type']; // e.g., 'enqueued', 'sent', 'delivered', 'read', 'failed'
    file_put_contents($log_file, "Detected Gupshup format. Message ID: {$message_id}, Status: {$status}\n", FILE_APPEND);
}


if ($message_id && $status) {
    // Map API status to our internal enum
    $internal_status = 'sent'; // Default
    switch (strtolower($status)) {
        case 'delivered': $internal_status = 'delivered'; break;
        case 'read': $internal_status = 'read'; break;
        case 'failed': $internal_status = 'failed'; break;
    }

    $stmt = $mysqli->prepare("UPDATE whatsapp_queue SET status = ?, updated_at = NOW() WHERE api_message_id = ?");
    if ($stmt) {
        $stmt->bind_param('ss', $internal_status, $message_id);
        if ($stmt->execute()) {
            file_put_contents($log_file, "Updated message {$message_id} to status {$internal_status}\n", FILE_APPEND);
        } else {
             file_put_contents($log_file, "Failed to execute statement for message {$message_id}\n", FILE_APPEND);
        }
    } else {
        file_put_contents($log_file, "Failed to prepare statement for message {$message_id}\n", FILE_APPEND);
    }
} else {
    file_put_contents($log_file, "Could not extract message_id or status from payload.\n", FILE_APPEND);
}

http_response_code(200);
echo "OK";
