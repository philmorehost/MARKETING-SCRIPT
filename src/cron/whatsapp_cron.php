<?php
// --- whatsapp_cron.php ---
// This script should be executed by a cron job every minute.

require_once dirname(__FILE__) . '/../../config/db.php';
require_once dirname(__FILE__) . '/../lib/functions.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// --- Settings ---
$provider = get_setting('whatsapp_provider', $mysqli, 'none');
$batch_limit = 100;

if ($provider === 'none') {
    die("No WhatsApp provider configured.\n");
}

// --- Main Logic ---
$stmt = $mysqli->prepare(
    "SELECT q.id, q.phone_number, c.template_name, c.template_params_json, ct.first_name, ct.custom_fields_json
     FROM whatsapp_queue q
     JOIN whatsapp_campaigns c ON q.campaign_id = c.id
     JOIN contacts ct ON q.contact_id = ct.id
     WHERE q.status = 'queued'
     ORDER BY q.id ASC
     LIMIT ?"
);
$stmt->bind_param('i', $batch_limit);
$stmt->execute();
$queue_items = $stmt->get_result();

if ($queue_items->num_rows === 0) {
    echo "No pending WhatsApp messages.\n";
    exit;
}

while ($item = $queue_items->fetch_assoc()) {
    $queue_id = $item['id'];
    $mysqli->query("UPDATE whatsapp_queue SET status = 'sending' WHERE id = $queue_id");

    // --- API Call Logic ---
    $success = false;
    if ($provider === 'gupshup') {
        // Gupshup API logic would go here
        // $apiKey = get_setting('gupshup_api_key', $mysqli);
        // ... build API request ...
        $success = true; // Placeholder
    } elseif ($provider === 'meta') {
        // Meta Official API logic would go here
        // $apiToken = get_setting('meta_api_token', $mysqli);
        // ... build API request ...
        $success = true; // Placeholder
    }

    // --- Update Queue ---
    if ($success) {
        $mysqli->query("UPDATE whatsapp_queue SET status = 'sent' WHERE id = $queue_id");
        echo "Successfully sent WhatsApp to {$item['phone_number']}\n";
    } else {
        $mysqli->query("UPDATE whatsapp_queue SET status = 'failed' WHERE id = $queue_id");
        echo "Failed to send WhatsApp to {$item['phone_number']}\n";
    }
}

echo "WhatsApp cron run finished.\n";
