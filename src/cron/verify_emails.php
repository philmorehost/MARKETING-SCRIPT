<?php
// This cron job should be run every minute.
require_once dirname(__DIR__) . '/config/db.php';

// Fetch a batch of pending emails
$limit = 100; // Process 100 emails per run
$stmt = $mysqli->prepare("SELECT id, email_address FROM verification_queue WHERE status = 'pending' LIMIT ?");
$stmt->bind_param('i', $limit);
$stmt->execute();
$emails_to_verify = $stmt->get_result();

if ($emails_to_verify->num_rows > 0) {
    $update_stmt = $mysqli->prepare("UPDATE verification_queue SET status = ?, details = ? WHERE id = ?");

    while ($email = $emails_to_verify->fetch_assoc()) {
        // Mock verification logic
        $status = 'valid';
        $details = 'Mock verification successful.';
        if (strpos($email['email_address'], 'invalid') !== false) {
            $status = 'invalid';
            $details = 'Domain does not exist.';
        } elseif (strpos($email['email_address'], 'risky') !== false) {
            $status = 'risky';
            $details = 'Mailbox is a catch-all.';
        }

        $update_stmt->bind_param('ssi', $status, $details, $email['id']);
        $update_stmt->execute();
    }
}

// Mark jobs as completed
$mysqli->query("UPDATE verification_jobs SET status = 'Completed' WHERE status = 'Processing' AND (SELECT COUNT(*) FROM verification_queue WHERE job_id = verification_jobs.id AND status = 'pending') = 0");
