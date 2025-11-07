<?php
// src/cron/verify_cron.php
// This script should be run by a cron job every minute.

// Prevent public access
if (PHP_SAPI !== 'cli') {
    die('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/src/lib/functions.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    echo "Database connection failed: " . $mysqli->connect_error . "\n";
    exit(1);
}

echo "Running Email Verification Cron Job...\n";

// --- Fetch a batch of emails to verify ---
$batch_size = 100; // Process 100 emails per run
$stmt = $mysqli->prepare("SELECT id, email_address FROM verification_queue WHERE status = 'pending' LIMIT ?");
$stmt->bind_param("i", $batch_size);
$stmt->execute();
$result = $stmt->get_result();
$emails_to_verify = $result->fetch_all(MYSQLI_ASSOC);

if (empty($emails_to_verify)) {
    echo "No emails to verify. Exiting.\n";
    exit(0);
}

echo "Found " . count($emails_to_verify) . " emails to process.\n";
$update_stmt = $mysqli->prepare("UPDATE verification_queue SET status = ?, processed_at = NOW() WHERE id = ?");

foreach ($emails_to_verify as $email_item) {
    $id = $email_item['id'];
    $email = $email_item['email_address'];

    // --- Simple Verification Logic (Built-in) ---
    // In a real application, this would involve more complex checks like:
    // 1. Syntax check (already done by filter_var)
    // 2. DNS check for MX records
    // 3. SMTP check (connect to mail server)
    // 4. Using a third-party API for deeper checks (disposable, role-based, etc.)

    $status = 'unknown'; // Default status

    // 1. Syntax Check
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $status = 'invalid';
    } else {
        // 2. MX Record Check
        $domain = substr(strrchr($email, "@"), 1);
        if (checkdnsrr($domain, "MX")) {
             // For this simulation, we'll randomly assign valid/invalid to emails that pass the MX check
            if (rand(0, 10) > 2) { // 80% chance of being valid
                 $status = 'valid';
            } else {
                 $status = 'invalid';
            }
        } else {
            $status = 'invalid';
        }
    }

    // --- Update the record ---
    $update_stmt->bind_param("si", $status, $id);
    $update_stmt->execute();
    echo "  - Processed {$email}: {$status}\n";
}

echo "Batch processing complete.\n";

// --- Check for and mark completed jobs ---
// A job is complete if it has no more 'pending' emails in the queue.
$mysqli->query("
    UPDATE verification_jobs j
    LEFT JOIN verification_queue q ON j.id = q.job_id AND q.status = 'pending'
    SET j.status = 'completed'
    WHERE j.status = 'processing' AND q.id IS NULL
");


$mysqli->close();
exit(0);
