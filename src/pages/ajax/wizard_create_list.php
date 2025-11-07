<?php
// src/pages/ajax/wizard_create_list.php
require_once dirname(__DIR__, 2) . '/lib/functions.php';
require_once dirname(__DIR__, 2) . '/lib/auth.php';
check_login();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$list_name = trim($data['list_name'] ?? '');

if (empty($list_name)) {
    echo json_encode(['success' => false, 'message' => 'List name is required.']);
    exit;
}

// Create the contact list
$stmt = $mysqli->prepare("INSERT INTO contact_lists (user_id, team_id, list_name) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $user['id'], $user['team_id'], $list_name);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create list.']);
}
exit;
