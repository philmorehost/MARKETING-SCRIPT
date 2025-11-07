<?php
// src/cron/campaign_cron.php
// This script should be run by a cron job every minute.

if (PHP_SAPI !== 'cli') {
    die('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/src/lib/functions.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    echo "Database connection failed: " . $mysqli->connect_error . "\n";
    exit(1);
}

echo "Running Email Campaign Cron Job...\n";

// --- Fetch a batch of emails to send ---
$batch_size = get_setting('max_emails_per_hour', 100) / 60; // Per-minute batch size
$stmt = $mysqli->prepare("
    SELECT cq.id, cq.email_address, c.subject, c.html_content
    FROM campaign_queue cq
    JOIN campaigns c ON cq.campaign_id = c.id
    WHERE cq.status = 'queued' AND c.status IN ('queued', 'sending')
    ORDER BY c.created_at ASC
    LIMIT ?
");
$stmt->bind_param("i", $batch_size);
$stmt->execute();
$result = $stmt->get_result();
$emails_to_send = $result->fetch_all(MYSQLI_ASSOC);

if (empty($emails_to_send)) {
    echo "No emails in queue. Exiting.\n";
    exit(0);
}

echo "Found " . count($emails_to_send) . " emails to send.\n";

$mail = new PHPMailer(true);
try {
    // --- Configure PHPMailer ---
    $mail->isSMTP();
    $mail->Host = get_setting('smtp_host', 'localhost');
    $mail->SMTPAuth = false;
    $mail->Port = get_setting('smtp_port', 1025);
    $mail->setFrom(get_setting('site_email', 'noreply@example.com'), get_setting('site_name'));

    $update_stmt = $mysqli->prepare("UPDATE campaign_queue SET status = ?, api_message_id = ? WHERE id = ?");

    foreach ($emails_to_send as $email_item) {
        try {
            $mail->clearAddresses();
            $mail->addAddress($email_item['email_address']);
            $mail->Subject = $email_item['subject'];
            $mail->Body    = $email_item['html_content'];
            $mail->isHTML(true);

            $mail->send();

            $status = 'sent';
            $message_id = $mail->getLastMessageID();
            echo "  - Sent to {$email_item['email_address']}\n";

        } catch (Exception $e) {
            $status = 'failed';
            $message_id = $mail->ErrorInfo;
            echo "  - FAILED to send to {$email_item['email_address']}: {$mail->ErrorInfo}\n";
        }

        $update_stmt->bind_param("ssi", $status, $message_id, $email_item['id']);
        $update_stmt->execute();
    }

} catch (Exception $e) {
    echo "Mailer configuration error: {$mail->ErrorInfo}\n";
}

echo "Batch processing complete.\n";
$mysqli->close();
exit(0);
