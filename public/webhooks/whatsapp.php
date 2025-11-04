<?php
require_once '../../src/config/db.php';
require_once '../../src/lib/functions.php';

$input = @file_get_contents("php://input");
$event = json_decode($input, true);

// Gupshup DLR format
if (isset($event['status']) && isset($event['externalId'])) {
    $status_map = [
        'sent' => 'sent',
        'delivered' => 'delivered',
        'read' => 'read',
        'failed' => 'failed',
    ];
    $dlr_status = $status_map[$event['status']] ?? 'failed';
    $message_id = $event['externalId'];

    $stmt = $mysqli->prepare("UPDATE whatsapp_queue SET status = ? WHERE api_message_id = ?");
    $stmt->bind_param('ss', $dlr_status, $message_id);
    $stmt->execute();
}

// Meta DLR format
if (isset($event['entry'][0]['changes'][0]['value']['statuses'][0])) {
    $status_data = $event['entry'][0]['changes'][0]['value']['statuses'][0];
    $message_id = $status_data['id'];
    $status = $status_data['status'];

    $dlr_status = $status; // Meta uses 'sent', 'delivered', 'read', 'failed'

    $stmt = $mysqli->prepare("UPDATE whatsapp_queue SET status = ? WHERE api_message_id = ?");
    $stmt->bind_param('ss', $dlr_status, $message_id);
    $stmt->execute();
}

http_response_code(200);
