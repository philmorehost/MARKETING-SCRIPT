<?php
// --- src/pages/notifications_mark_read.php ---

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
$team_id = $_SESSION['team_id'];

// Update all unread notifications for the team to be read
$stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE team_id = ? AND is_read = 0");
$stmt->bind_param('i', $team_id);
$stmt->execute();

// Redirect back to the notifications page (or wherever is appropriate)
header('Location: /notifications');
exit;
