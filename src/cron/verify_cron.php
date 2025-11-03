<?php
// --- verify_cron.php ---
// Runs every minute to process the email verification queue.

define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/config/db.php';
require_once APP_ROOT . '/src/lib/functions.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) { die("DB connection error"); }

$batch_limit = 200; // Emails to verify per run
$processed_job_ids = [];

// Fetch engine type
$engine_type = get_setting('verification_engine', $mysqli, 'built-in');

$stmt = $mysqli->prepare("SELECT id, job_id, email_address FROM verification_queue WHERE status = 'pending' ORDER BY id ASC LIMIT ?");
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
    $job_id = $item['job_id'];
    $processed_job_ids[$job_id] = true;
    $status = 'unknown';

    if ($engine_type === 'built-in') {
        // 1. Syntax Check
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $status = 'invalid';
        } else {
            $domain = substr(strrchr($email, "@"), 1);
            // 2. Domain/MX Record Check
            if (checkdnsrr($domain, "MX")) {
                // 3. SMTP Verification (Placeholder for a more advanced library)
                // For this built-in check, we'll consider it valid if MX records exist.
                $status = 'valid';
            } else {
                $status = 'invalid';
            }
        }
    } else {
        // Placeholder for an external API call
        // $result = call_external_api($email);
        // $status = $result['status'];
        $status = 'unknown'; // Default for non-built-in for now
        echo "External verification engine not implemented for email {$email}.\n";
    }

    $update_stmt = $mysqli->prepare("UPDATE verification_queue SET status = ?, processed_at = NOW() WHERE id = ?");
    $update_stmt->bind_param('si', $status, $queue_id);
    $update_stmt->execute();

    echo "Processed {$email}: {$status}\n";
}

// --- Update Job Statuses ---
if (!empty($processed_job_ids)) {
    $job_ids_to_check = array_keys($processed_job_ids);
    $job_id_placeholders = implode(',', array_fill(0, count($job_ids_to_check), '?'));

    $stmt_check = $mysqli->prepare(
        "SELECT j.id, (SELECT COUNT(*) FROM verification_queue WHERE job_id = j.id AND status = 'pending') as pending_count
         FROM verification_jobs j
         WHERE j.id IN ({$job_id_placeholders})"
    );
    $stmt_check->bind_param(str_repeat('i', count($job_ids_to_check)), ...$job_ids_to_check);
    $stmt_check->execute();
    $jobs_to_update = $stmt_check->get_result();

    while ($job = $jobs_to_update->fetch_assoc()) {
        if ($job['pending_count'] == 0) {
            $update_job_stmt = $mysqli->prepare("UPDATE verification_jobs SET status = 'completed' WHERE id = ?");
            $update_job_stmt->bind_param('i', $job['id']);
            $update_job_stmt->execute();
            echo "Marked job ID {$job['id']} as completed.\n";
        }
    }
}

echo "Verification cron run finished.\n";
$mysqli->close();
