<?php
// src/pages/notifications.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "Notifications";

// Mark all as read action
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    $mysqli->query("UPDATE notifications SET is_read = 1 WHERE user_id = {$user['id']}");
    header('Location: /notifications');
    exit;
}

// Fetch notifications
$notifications_q = $mysqli->query("SELECT * FROM notifications WHERE user_id = {$user['id']} ORDER BY created_at DESC");
$notifications = $notifications_q->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header_app.php';
?>
<div class="container app-content">
    <div class="page-header">
        <h1>Notifications</h1>
        <a href="/notifications?action=mark_all_read" class="btn btn-secondary">Mark All as Read</a>
    </div>

    <div class="card">
        <div class="notification-list">
            <?php if(empty($notifications)): ?>
                <p>You have no new notifications.</p>
            <?php else: foreach($notifications as $notif): ?>
                <div class="notification-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                    <p><?php echo htmlspecialchars($notif['message']); ?></p>
                    <small><?php echo date('M d, Y H:i', strtotime($notif['created_at'])); ?></small>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
<?php
include __DIR__ . '/../includes/footer_app.php';
?>
