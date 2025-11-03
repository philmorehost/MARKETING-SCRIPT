<?php
// --- webhook_sms.php ---
// Endpoint for PhilmoreSMS to post delivery reports (DLR).

require_once '../../config/db.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// PhilmoreSMS typically sends DLR data via GET or POST parameters
$message_id = $_REQUEST['message_id'] ?? null;
$status = $_REQUEST['status'] ?? null;

if ($message_id && $status) {
    // Map the API's status to your internal status
    $internal_status = 'sent'; // Default
    if (strtolower($status) === 'delivered') {
        $internal_status = 'delivered';
    } elseif (strtolower($status) === 'failed' || strtolower($status) === 'undelivered') {
        $internal_status = 'failed';
    }

    // Update the corresponding message in the sms_queue
    $stmt = $mysqli->prepare("UPDATE sms_queue SET status = ? WHERE api_message_id = ?");
    $stmt->bind_param('ss', $internal_status, $message_id);
    $stmt->execute();
}

http_response_code(200);
