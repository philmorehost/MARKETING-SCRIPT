<?php
session_start();
require_once '../../src/config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$list_name = $_POST['list_name'] ?? '';

if (empty($list_name)) {
    echo json_encode(['success' => false, 'error' => 'List name cannot be empty.']);
    exit;
}

// Get team_id
$stmt = $mysqli->prepare("SELECT team_id FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$team_id = $stmt->get_result()->fetch_assoc()['team_id'];

// Insert into contact_lists
$stmt = $mysqli->prepare("INSERT INTO contact_lists (user_id, team_id, list_name) VALUES (?, ?, ?)");
$stmt->bind_param('iis', $user_id, $team_id, $list_name);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'list_id' => $mysqli->insert_id]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
