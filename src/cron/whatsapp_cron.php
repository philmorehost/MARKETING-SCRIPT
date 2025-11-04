<?php
// This cron job should be run every minute.
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/functions.php';

$provider = get_setting('whatsapp_provider', $mysqli);
if ($provider === 'none' || empty($provider)) {
    exit;
}

$limit = 100; // Process 100 messages per run
$stmt = $mysqli->prepare("
    SELECT wq.id, wq.phone_number, wc.template_name, wc.template_params_json, c.first_name, c.last_name, c.email
    FROM whatsapp_queue wq
    JOIN whatsapp_campaigns wc ON wq.campaign_id = wc.id
    JOIN contacts c ON wq.contact_id = c.id
    WHERE wq.status = 'pending'
    LIMIT ?
");
$stmt->bind_param('i', $limit);
$stmt->execute();
$messages_to_send = $stmt->get_result();

if ($messages_to_send->num_rows > 0) {
    $update_stmt = $mysqli->prepare("UPDATE whatsapp_queue SET status = ?, api_message_id = ? WHERE id = ?");

    while ($msg = $messages_to_send->fetch_assoc()) {
        $params_map = json_decode($msg['template_params_json'], true);
        $template_params = [];
        foreach($params_map as $index => $field_name) {
            $template_params[] = $msg[$field_name] ?? '';
        }

        $status = 'failed';
        $api_message_id = null;

        if ($provider === 'gupshup') {
            $api_key = get_setting('gupshup_api_key', $mysqli);
            $source_number = get_setting('gupshup_source_number', $mysqli);

            $postData = [
                'channel' => 'whatsapp',
                'source' => $source_number,
                'destination' => $msg['phone_number'],
                'message' => json_encode([
                    'type' => 'template',
                    'template' => json_encode([
                        'id' => $msg['template_name'],
                        'params' => $template_params
                    ])
                ]),
                'src.name' => 'YourAppName' // You might want to make this a setting
            ];

            $ch = curl_init('https://api.gupshup.io/wa/api/v1/msg');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Cache-Control: no-cache',
                'Content-Type: application/x-www-form-urlencoded',
                'apikey: ' . $api_key,
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            $response = curl_exec($ch);
            curl_close($ch);
            $result = json_decode($response, true);

            if (isset($result['status']) && $result['status'] === 'submitted') {
                $status = 'sent';
                $api_message_id = $result['messageId'] ?? null;
            }

        } // Add else if for 'meta' provider here

        $update_stmt->bind_param('ssi', $status, $api_message_id, $msg['id']);
        $update_stmt->execute();
    }
}

// Mark campaigns as completed
$mysqli->query("UPDATE whatsapp_campaigns SET status = 'Completed' WHERE status = 'queued' AND (SELECT COUNT(*) FROM whatsapp_queue WHERE campaign_id = whatsapp_campaigns.id AND status = 'pending') = 0");
