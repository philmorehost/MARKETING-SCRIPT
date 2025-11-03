<?php
// --- webhook_sms.php ---
// Endpoint for PhilmoreSMS to post delivery reports (DLR).

require_once '../../config/db.php';
$log_file = dirname(__FILE__) . '/../../logs/sms_webhook.log';

// --- Security (Placeholder) ---
// In a real application, you should verify the request, e.g., by checking a shared secret
// or whitelisting the provider's IP addresses.
$allowed_ips = ['127.0.0.1', '197.210.65.10']; // Example: Add PhilmoreSMS IPs here
if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    http_response_code(403);
    file_put_contents($log_file, "Rejected request from untrusted IP: {$_SERVER['REMOTE_ADDR']}\n", FILE_APPEND);
    exit('Forbidden');
}

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    file_put_contents($log_file, "Database connection error\n", FILE_APPEND);
    http_response_code(500);
    exit;
}

$raw_payload = file_get_contents('php://input');
file_put_contents($log_file, "Received payload: {$raw_payload}\n", FILE_APPEND);

// Assuming PhilmoreSMS sends a POST request with JSON body like:
// { "message_id": "xyz", "status": "DELIVRD" }
// Note: This format is an assumption. It must be adapted to the provider's actual format.
$data = json_decode($raw_payload, true);

$message_id = $data['message_id'] ?? $_REQUEST['message_id'] ?? null;
$status = $data['status'] ?? $_REQUEST['status'] ?? null;

if ($message_id && $status) {
    // Map API status to our internal status enum
    $internal_status = 'sent'; // Default
    switch (strtoupper($status)) {
        case 'DELIVRD':
        case 'DELIVERED':
            $internal_status = 'delivered';
            break;
        case 'UNDELIV':
        case 'FAILED':
        case 'REJECTD':
            $internal_status = 'failed';
            break;
    }

    $stmt = $mysqli->prepare("UPDATE sms_queue SET status = ?, updated_at = NOW() WHERE api_message_id = ?");
    if ($stmt) {
        $stmt->bind_param('ss', $internal_status, $message_id);
        if($stmt->execute()) {
            file_put_contents($log_file, "Updated message {$message_id} to status {$internal_status}\n", FILE_APPEND);
        } else {
            file_put_contents($log_file, "Failed to execute statement for message {$message_id}\n", FILE_APPEND);
        }
    } else {
        file_put_contents($log_file, "Failed to prepare statement for message {$message_id}\n", FILE_APPEND);
    }
} else {
    file_put_contents($log_file, "Missing message_id or status in payload.\n", FILE_APPEND);
}

http_response_code(200);
echo "OK";
