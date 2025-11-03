<?php
// --- webhook_email.php ---
// Endpoint for email service providers (e.g., SendGrid, Mailgun) to post event data.

require_once '../../config/db.php';
$log_file = dirname(__FILE__) . '/../../logs/email_webhook.log';

// --- Security ---
// It is CRITICAL to verify webhook requests. This is a placeholder for signature verification.
// Example: $signature = $_SERVER['HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_SIGNATURE'];
// if (!verify_signature($signature, $payload)) { http_response_code(403); exit; }

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    file_put_contents($log_file, "Database connection error\n", FILE_APPEND);
    http_response_code(500); exit;
}

$raw_payload = file_get_contents('php://input');
file_put_contents($log_file, "Received payload: {$raw_payload}\n", FILE_APPEND);
$events = json_decode($raw_payload, true);

if (!$events) {
    file_put_contents($log_file, "Invalid JSON received.\n", FILE_APPEND);
    http_response_code(400); exit;
}

// Prepare statements for efficiency
$stmt_find = $mysqli->prepare("SELECT id, campaign_id, contact_id FROM campaign_queue WHERE api_message_id = ?");
$stmt_update = $mysqli->prepare("UPDATE campaign_queue SET status = ? WHERE id = ?");
$stmt_event = $mysqli->prepare("INSERT INTO campaign_events (campaign_id, contact_id, event_type, url_clicked) VALUES (?, ?, ?, ?)");

// Process each event in the payload
foreach ($events as $event) {
    // --- Adapt to your provider's payload structure ---
    // This example assumes a SendGrid-like structure.
    $message_id = $event['sg_message_id'] ?? null;
    $event_type = $event['event'] ?? null;

    if (!$message_id || !$event_type) continue;

    // The message ID might have provider-specific parts. Let's clean it.
    // Example SendGrid Message ID: "sendgrid.81_..._b.0@ismtpd0101p1lon1.sendgrid.net"
    // We only stored the part before the '@'.
    $message_id_parts = explode('@', $message_id);
    $clean_message_id = $message_id_parts[0];

    $stmt_find->bind_param('s', $clean_message_id);
    $stmt_find->execute();
    $queue_item = $stmt_find->get_result()->fetch_assoc();

    if ($queue_item) {
        $queue_id = $queue_item['id'];
        $campaign_id = $queue_item['campaign_id'];
        $contact_id = $queue_item['contact_id'];
        $url_clicked = $event['url'] ?? null;
        $internal_status = null;

        switch (strtolower($event_type)) {
            case 'bounce':
            case 'dropped':
                $internal_status = 'bounced';
                break;
            case 'open':
                $internal_status = 'opened';
                break;
            case 'click':
                $internal_status = 'clicked';
                break;
        }

        // Log the specific event
        $stmt_event->bind_param('iiss', $campaign_id, $contact_id, $event_type, $url_clicked);
        $stmt_event->execute();

        // Update the main queue item status if it's a "terminal" or more "advanced" state
        if ($internal_status === 'bounced' || $internal_status === 'opened' || $internal_status === 'clicked') {
            $stmt_update->bind_param('si', $internal_status, $queue_id);
            $stmt_update->execute();
        }
        file_put_contents($log_file, "Processed '{$event_type}' for message ID {$clean_message_id}\n", FILE_APPEND);
    } else {
        file_put_contents($log_file, "Could not find queue item for message ID {$clean_message_id}\n", FILE_APPEND);
    }
}

http_response_code(200);
echo "OK";
