<?php
require_once '../../src/config/db.php';

// PhilmoreSMS sends DLRs as GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $message_id = $_GET['message_id'] ?? null;
    $status = $_GET['status'] ?? null; // e.g., 'DELIVRD', 'UNDELIV'

    if ($message_id && $status) {
        $dlr_status = 'failed';
        if ($status === 'DELIVRD') {
            $dlr_status = 'delivered';
        }

        $stmt = $mysqli->prepare("UPDATE sms_queue SET status = ? WHERE api_message_id = ?");
        $stmt->bind_param('ss', $dlr_status, $message_id);
        $stmt->execute();
    }
}
http_response_code(200);
