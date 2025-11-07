<?php
// src/pages/ajax/wizard_complete.php
require_once dirname(__DIR__, 2) . '/lib/functions.php';
require_once dirname(__DIR__, 2) . '/lib/auth.php';
check_login();

header('Content-Type: application/json');

// Mark wizard as complete in the database
$stmt = $mysqli->prepare("UPDATE users SET first_login_wizard_complete = 1 WHERE id = ?");
$stmt->bind_param("i", $user['id']);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update wizard status.']);
}
exit;
