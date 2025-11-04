<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
$team_id = $_SESSION['team_id'];

// Fetch all notifications for the team
$stmt = $mysqli->prepare("SELECT message, link, is_read, created_at FROM notifications WHERE team_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $team_id);
$stmt->execute();
$notifications = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head><title>All Notifications</title><link rel="stylesheet" href="/public/css/dashboard_style.css"></head>
<body>
    <?php include APP_ROOT . '/public_html/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public_html/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>All Notifications</h1>
            <div class="card">
                <ul class="notification-list">
                    <?php if ($notifications->num_rows > 0): ?>
                        <?php while($notif = $notifications->fetch_assoc()): ?>
                        <li class="<?php echo $notif['is_read'] ? 'read' : 'unread'; ?>">
                            <a href="<?php echo htmlspecialchars($notif['link'] ?? '#'); ?>">
                                <p><?php echo htmlspecialchars($notif['message']); ?></p>
                                <span class="timestamp"><?php echo $notif['created_at']; ?></span>
                            </a>
                        </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li><p>You have no notifications.</p></li>
                    <?php endif; ?>
                </ul>
            </div>
        </main>
    </div>
    <?php include APP_ROOT . '/public_html/includes/footer.php'; ?>
</body>
</html>
