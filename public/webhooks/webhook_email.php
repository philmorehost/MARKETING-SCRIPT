<?php
// --- webhook_email.php ---
// Endpoint for email service providers (e.g., SendGrid, Mailgun) to post event data.

require_once '../../config/db.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Get the raw POST data
$payload = file_get_contents('php://input');
$event_data = json_decode($payload, true);

// Example: Processing a SendGrid event
if (isset($event_data[0]['sg_message_id'])) {
    foreach ($event_data as $event) {
        $event_type = $event['event'];
        // You would need a way to map sg_message_id back to your campaign_queue id
        $queue_id = 0; // Logic to find queue_id based on a stored message_id

        // Log the event
        $stmt = $mysqli->prepare("INSERT INTO campaign_events (campaign_id, contact_id, event_type) VALUES (?, ?, ?)");
        // $stmt->bind_param('iis', $campaign_id, $contact_id, $event_type);
        // $stmt->execute();

        // Update campaign_queue status for bounces
        if ($event_type === 'bounce') {
            $mysqli->query("UPDATE campaign_queue SET status = 'bounced' WHERE id = $queue_id");
        }
    }
}

http_response_code(200); // Respond with OK
