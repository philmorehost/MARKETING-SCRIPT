<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];
$job_id = (int)($_GET['job_id'] ?? 0);

if ($job_id === 0) {
    exit;
}

// Verify job ownership
$stmt = $mysqli->prepare("SELECT job_name FROM verification_jobs WHERE id = ? AND team_id = ? AND status = 'Completed'");
$stmt->bind_param('ii', $job_id, $team_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    exit;
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="verification_results_' . $job_id . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['email_address', 'status', 'details']);

$results_stmt = $mysqli->prepare("SELECT email_address, status, details FROM verification_queue WHERE job_id = ?");
$results_stmt->bind_param('i', $job_id);
$results_stmt->execute();
$results = $results_stmt->get_result();

while ($row = $results->fetch_assoc()) {
    fputcsv($output, $row);
}
fclose($output);
