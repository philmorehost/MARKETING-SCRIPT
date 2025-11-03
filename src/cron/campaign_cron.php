<?php
// --- campaign_cron.php ---
// This script should be executed by a cron job every minute.

require_once dirname(__FILE__) . '/../../config/db.php';
require_once dirname(__FILE__) . '/../lib/functions.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// --- Settings ---
$max_emails_per_hour = (int)get_setting('max_emails_per_hour', $mysqli, 300);
$batch_limit = 50; // Emails to process per cron run

// --- Throttling Logic ---
$stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM hourly_email_log WHERE sent_at > (NOW() - INTERVAL 1 HOUR)");
$stmt->execute();
$sent_this_hour = $stmt->get_result()->fetch_assoc()['total'];

if ($sent_this_hour >= $max_emails_per_hour) {
    die("Hourly email limit of {$max_emails_per_hour} reached.\n");
}

$emails_to_send = min($batch_limit, $max_emails_per_hour - $sent_this_hour);

// --- Main Logic ---
$stmt = $mysqli->prepare(
    "SELECT q.id, q.email_address, c.subject, c.html_content
     FROM campaign_queue q
     JOIN campaigns c ON q.campaign_id = c.id
     WHERE q.status = 'queued'
     ORDER BY q.id ASC
     LIMIT ?"
);
$stmt->bind_param('i', $emails_to_send);
$stmt->execute();
$queue_items = $stmt->get_result();

if ($queue_items->num_rows === 0) {
    echo "No pending emails to send.\n";
    exit;
}

while ($item = $queue_items->fetch_assoc()) {
    $queue_id = $item['id'];

    // Mark as 'sending'
    $mysqli->query("UPDATE campaign_queue SET status = 'sending' WHERE id = $queue_id");

    // --- Email Sending Logic (PHPMailer would go here) ---
    // $mail = new PHPMailer(true);
    // try {
    //     $mail->isSMTP();
    //     // ... SMTP config from settings ...
    //     $mail->setFrom('no-reply@yourdomain.com', get_setting('site_name', $mysqli));
    //     $mail->addAddress($item['email_address']);
    //     $mail->isHTML(true);
    //     $mail->Subject = $item['subject'];
    //     $mail->Body    = $item['html_content'];
    //     $mail->send();

        // On success:
        $mysqli->query("UPDATE campaign_queue SET status = 'sent' WHERE id = $queue_id");
        $mysqli->query("INSERT INTO hourly_email_log (campaign_id) VALUES ({$item['campaign_id']})");
        echo "Successfully sent email to {$item['email_address']}\n";

    // } catch (Exception $e) {
    //     // On failure:
    //     $mysqli->query("UPDATE campaign_queue SET status = 'failed' WHERE id = $queue_id");
    //     echo "Failed to send email to {$item['email_address']}. Error: {$mail->ErrorInfo}\n";
    // }
}

echo "Campaign cron run finished.\n";
