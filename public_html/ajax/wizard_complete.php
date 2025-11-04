<?php
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    exit;
}

require_once '../../config/db.php';
$user_id = $_SESSION['user_id'];

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$stmt = $mysqli->prepare("UPDATE users SET first_login_wizard_complete = 1 WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();

echo json_encode(['success' => true]);
