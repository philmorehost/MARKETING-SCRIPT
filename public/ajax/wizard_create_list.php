<?php
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once '../../config/db.php';
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];
$list_name = trim($_POST['list_name'] ?? '');

if (empty($list_name)) {
    echo json_encode(['success' => false, 'error' => 'List name cannot be empty.']);
    exit;
}

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$stmt = $mysqli->prepare("INSERT INTO contact_lists (user_id, team_id, list_name) VALUES (?, ?, ?)");
$stmt->bind_param('iis', $user_id, $team_id, $list_name);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
