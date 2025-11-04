<?php
// This cron job should be run every minute.
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/functions.php';

$max_emails_per_hour = (int)get_setting('max_emails_per_hour', $mysqli, 300);
$emails_per_run = $max_emails_per_hour / 60;

// Fetch a batch of pending emails
$stmt = $mysqli->prepare("
    SELECT cq.id, cq.email_address, c.subject, c.html_content
    FROM campaign_queue cq
    JOIN campaigns c ON cq.campaign_id = c.id
    WHERE cq.status = 'pending'
    LIMIT ?
");
$stmt->bind_param('i', $emails_per_run);
$stmt->execute();
$emails_to_send = $stmt->get_result();

if ($emails_to_send->num_rows > 0) {
    $mail = new PHPMailer(true);
    $update_stmt = $mysqli->prepare("UPDATE campaign_queue SET status = ? WHERE id = ?");

    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host       = get_setting('smtp_host', $mysqli);
        $mail->SMTPAuth   = true;
        $mail->Username   = get_setting('smtp_user', $mysqli);
        $mail->Password   = get_setting('smtp_pass', $mysqli);
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        //Recipients
        $mail->setFrom(get_setting('smtp_from_email', $mysqli), get_setting('smtp_from_name', $mysqli));

        while ($email = $emails_to_send->fetch_assoc()) {
            $mail->addAddress($email['email_address']);
            $mail->isHTML(true);
            $mail->Subject = $email['subject'];
            $mail->Body    = $email['html_content'];

            $mail->send();

            $status = 'sent';
            $update_stmt->bind_param('si', $status, $email['id']);
            $update_stmt->execute();

            $mail->clearAddresses();
        }
    } catch (Exception $e) {
        // Log error
    }
}

// Mark campaigns as completed
$mysqli->query("
    UPDATE campaigns c
    SET status = 'Completed'
    WHERE c.status = 'queued'
    AND NOT EXISTS (
        SELECT 1 FROM campaign_queue cq WHERE cq.campaign_id = c.id AND cq.status = 'pending'
    )
");
