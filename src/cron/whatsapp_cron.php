<?php
// This script should be run every minute via a cron job.
// Example: * * * * * /usr/bin/php /path/to/your/project/src/cron/whatsapp_cron.php >> /path/to/your/project/logs/whatsapp.log 2>&1

require_once dirname(__FILE__) . '/../../config/db.php';
require_once dirname(__FILE__) . '/../lib/functions.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// --- Fetch Provider Settings ---
$provider = get_setting('whatsapp_provider', $mysqli);

if (empty($provider) || $provider === 'none') {
    echo "WhatsApp provider is not configured. Exiting.\n";
    exit;
}

// --- Fetch a batch of pending messages ---
$limit = 100;
$stmt = $mysqli->prepare(
    "SELECT
        q.id as queue_id,
        q.phone_number,
        c.template_name,
        c.template_params_json,
        ct.first_name, ct.last_name, ct.email
     FROM whatsapp_queue q
     JOIN whatsapp_campaigns c ON q.campaign_id = c.id
     JOIN contacts ct ON q.contact_id = ct.id
     WHERE q.status = 'queued'
     ORDER BY q.id ASC
     LIMIT ?"
);
$stmt->bind_param('i', $limit);
$stmt->execute();
$pending_messages = $stmt->get_result();

if ($pending_messages->num_rows === 0) {
    echo "No pending WhatsApp messages to send.\n";
    exit;
}

// --- Process based on provider ---
while ($msg = $pending_messages->fetch_assoc()) {
    $queue_id = $msg['queue_id'];

    // Mark as 'sending' to prevent re-processing
    $update_stmt = $mysqli->prepare("UPDATE whatsapp_queue SET status = 'sending' WHERE id = ?");
    $update_stmt->bind_param('i', $queue_id);
    $update_stmt->execute();

    $params = json_decode($msg['template_params_json'], true);
    $resolved_params = [];
    foreach ($params as $index => $field_name) {
        $resolved_params[] = $msg[$field_name] ?? ''; // Get value from contact record
    }

    $response = null;
    if ($provider === 'gupshup') {
        $response = send_via_gupshup($msg['phone_number'], $msg['template_name'], $resolved_params, $mysqli);
    } elseif ($provider === 'meta') {
        // $response = send_via_meta(...);
        echo "Meta provider not yet implemented for queue ID {$queue_id}.\n";
        // For now, we'll mark as failed
        $response = ['status' => 'failed', 'message_id' => null, 'error' => 'Meta provider not implemented'];
    }

    // --- Update Queue Record ---
    $final_status = $response['status'] ?? 'failed';
    $api_message_id = $response['message_id'] ?? null;

    $result_stmt = $mysqli->prepare("UPDATE whatsapp_queue SET status = ?, api_message_id = ? WHERE id = ?");
    $result_stmt->bind_param('ssi', $final_status, $api_message_id, $queue_id);
    $result_stmt->execute();
}


function send_via_gupshup($phone, $template_name, $params, $db) {
    $api_key = get_setting('gupshup_api_key', $db);
    $source_number = get_setting('gupshup_source_number', $db);
    // This is a simplified example. Gupshup's API can be more complex.
    // This assumes a simple template message API endpoint.
    $api_url = "https://api.gupshup.io/wa/api/v1/msg";

    $post_data = [
        'channel' => 'whatsapp',
        'source' => $source_number,
        'destination' => $phone,
        'message' => json_encode([
            "isHSM" => "true",
            "type" => "text", // Or whatever the template type is
            "text" => "This is fallback text if template fails. Params: " . implode(', ', $params)
             // In a real scenario, you'd format the template correctly
        ]),
        'src.name' => get_setting('site_name', $db)
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'apikey: ' . $api_key
    ]);

    $response_body = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        echo "Gupshup cURL Error: " . $err . "\n";
        return ['status' => 'failed'];
    }

    $response_data = json_decode($response_body, true);

    if (isset($response_data['status']) && $response_data['status'] === 'submitted') {
        echo "Gupshup accepted message to {$phone}. Message ID: " . ($response_data['messageId'] ?? 'N/A') . "\n";
        return ['status' => 'sent', 'message_id' => $response_data['messageId'] ?? null];
    } else {
        echo "Gupshup API Error for {$phone}: " . $response_body . "\n";
        return ['status' => 'failed'];
    }
}

echo "WhatsApp cron run finished.\n";
$mysqli->close();
