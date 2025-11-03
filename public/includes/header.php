<?php
// Note: Session is already started and $mysqli is available from the front controller.

if (!isset($_SESSION['user_id'])) {
    exit('User not authenticated.');
}
$header_user_id = $_SESSION['user_id'];
$header_team_id = $_SESSION['team_id'];

// Fetch credit balance for header
$header_stmt_credits = $mysqli->prepare("SELECT credit_balance FROM users WHERE id = ?");
$header_stmt_credits->bind_param('i', $_SESSION['team_owner_id']);
$header_stmt_credits->execute();
$user_credit_balance = $header_stmt_credits->get_result()->fetch_assoc()['credit_balance'] ?? 0;

// Fetch unread notifications
$stmt_notif = $mysqli->prepare("SELECT id, message, link, created_at FROM notifications WHERE team_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
$stmt_notif->bind_param('i', $header_team_id);
$stmt_notif->execute();
$notifications = $stmt_notif->get_result();
$unread_count = $notifications->num_rows;
?>
<header class="user-header">
    <div class="logo"><a href="/public/dashboard">My Dashboard</a></div>
    <div class="header-right">
         <a href="/public/buy-credits" class="button-small">Buy Credits</a>
         <div class="notification-area">
            <div class="notification-bell" onclick="toggleNotifications()">
                <i class="fa fa-bell"></i>
                <?php if ($unread_count > 0): ?><span class="badge"><?php echo $unread_count; ?></span><?php endif; ?>
            </div>
            <div id="notification-panel" class="notification-panel" style="display: none;">
                <h3>Notifications</h3>
                <?php if ($notifications->num_rows > 0): ?>
                    <ul>
                        <?php while($notif = $notifications->fetch_assoc()): ?>
                        <li><a href="<?php echo htmlspecialchars($notif['link'] ?? '#'); ?>"><?php echo htmlspecialchars($notif['message']); ?></a></li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p>No new notifications.</p>
                <?php endif; ?>
                <div class="panel-footer">
                    <a href="/public/notifications">View All</a>
                    <a href="/public/notifications/mark-all-read">Mark all as read</a>
                </div>
            </div>
        </div>
        <div class="user-info">
            <span class="credit-balance">Credits: <?php echo number_format($user_credit_balance, 4); ?></span>
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <a href="/public/logout">Logout</a>
        </div>
    </div>
</header>
<script>
function toggleNotifications() {
    var panel = document.getElementById('notification-panel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}
// Close panel if clicking outside
document.addEventListener('click', function(event) {
    var panel = document.getElementById('notification-panel');
    var bell = document.querySelector('.notification-bell');
    if (panel && !panel.contains(event.target) && !bell.contains(event.target)) {
        panel.style.display = 'none';
    }
});
</script>
