<?php
// --- webhook_whatsapp.php ---
// Endpoint for Gupshup/Meta to post message status updates.

require_once '../../config/db.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Example: Processing a Meta Cloud API status update
if (isset($data['entry'][0]['changes'][0]['value']['statuses'])) {
    $status_data = $data['entry'][0]['changes'][0]['value']['statuses'][0];
    $message_id = $status_data['id'];
    $status = $status_data['status']; // e.g., 'sent', 'delivered', 'read'

    // Map to your internal statuses
    $internal_status = 'sent';
    if ($status === 'delivered') $internal_status = 'delivered';
    if ($status === 'read') $internal_status = 'read';
    if ($status === 'failed') $internal_status = 'failed';

    $stmt = $mysqli->prepare("UPDATE whatsapp_queue SET status = ? WHERE api_message_id = ?");
    $stmt->bind_param('ss', $internal_status, $message_id);
    $stmt->execute();
}

http_response_code(200);
