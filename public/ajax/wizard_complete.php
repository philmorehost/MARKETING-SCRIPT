<?php
session_start();
require_once '../../src/config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$user_id = $_SESSION['user_id'];

// Update the user's status to show the wizard is complete
$stmt = $mysqli->prepare("UPDATE users SET first_login_wizard_complete = 1 WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();

http_response_code(200);
