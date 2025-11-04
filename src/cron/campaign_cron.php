<?php
// --- campaign_cron.php ---
// This script should be executed by a cron job every minute.

define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/config/db.php';
require_once APP_ROOT . '/src/lib/functions.php';
require_once APP_ROOT . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) { die("DB connection error"); }

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
    "SELECT q.id, q.email_address, c.subject, c.html_content, q.campaign_id
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

$update_stmt = $mysqli->prepare("UPDATE campaign_queue SET status = ?, api_message_id = ? WHERE id = ?");

while ($item = $queue_items->fetch_assoc()) {
    $queue_id = $item['id'];

    // Mark as 'sending' first
    $mysqli->query("UPDATE campaign_queue SET status = 'sending' WHERE id = $queue_id");

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = get_setting('smtp_host', $mysqli);
        $mail->SMTPAuth = true;
        $mail->Username = get_setting('smtp_user', $mysqli);
        $mail->Password = get_setting('smtp_pass', $mysqli);
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('no-reply@yourdomain.com', get_setting('site_name', $mysqli));
        $mail->addAddress($item['email_address']);
        $mail->isHTML(true);
        $mail->Subject = $item['subject'];
        $mail->Body    = $item['html_content'];

        if ($mail->send()) {
            $status = 'sent';
            $message_id = trim($mail->getLastMessageID(), '<>');

            $update_stmt->bind_param('ssi', $status, $message_id, $queue_id);
            $update_stmt->execute();

            $mysqli->query("INSERT INTO hourly_email_log (campaign_id) VALUES ({$item['campaign_id']})");
            echo "Successfully sent email to {$item['email_address']} (MsgID: {$message_id})\n";
        } else {
            throw new Exception($mail->ErrorInfo);
        }
    } catch (Exception $e) {
        $status = 'failed';
        $message_id = null;
        $update_stmt->bind_param('ssi', $status, $message_id, $queue_id);
        $update_stmt->execute();
        echo "Failed to send email to {$item['email_address']}. Error: {$e->getMessage()}\n";
    }
}

echo "Campaign cron run finished.\n";
$mysqli->close();
