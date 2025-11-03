<?php
// --- verify_cron.php ---
// Runs every minute to process the email verification queue.

require_once dirname(__FILE__) . '/../../config/db.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$batch_limit = 200; // Emails to verify per run

$stmt = $mysqli->prepare("SELECT id, email_address FROM verification_queue WHERE status = 'pending' ORDER BY id ASC LIMIT ?");
$stmt->bind_param('i', $batch_limit);
$stmt->execute();
$queue = $stmt->get_result();

if ($queue->num_rows === 0) {
    echo "No emails to verify.\n";
    exit;
}

while ($item = $queue->fetch_assoc()) {
    $queue_id = $item['id'];
    $email = $item['email_address'];
    $status = 'unknown';

    // 1. Syntax Check (already done on input, but good to double-check)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $status = 'invalid';
    } else {
        $domain = substr(strrchr($email, "@"), 1);

        // 2. Domain/MX Record Check
        if (!checkdnsrr($domain, "MX")) {
            $status = 'invalid';
        } else {
            // 3. SMTP Verification (Placeholder)
            // This is a complex process and requires a dedicated library.
            // It involves connecting to the mail server and simulating sending an email.
            // For now, we'll just mark valid-looking emails as 'valid'.
            $status = 'valid';
        }
    }

    // Update the queue item
    $update_stmt = $mysqli->prepare("UPDATE verification_queue SET status = ?, processed_at = NOW() WHERE id = ?");
    $update_stmt->bind_param('si', $status, $queue_id);
    $update_stmt->execute();

    echo "Processed {$email}: {$status}\n";
}

// Here you could add logic to mark jobs in `verification_jobs` as 'completed'
// when all their associated queue items are processed.

echo "Verification cron run finished.\n";
