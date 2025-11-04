<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];

// Fetch sent SMS campaigns for the team with aggregated stats
$search = $_GET['search'] ?? '';
$base_query = "
    SELECT
        c.id, c.sender_id, c.message_body, c.cost_in_credits, c.status, c.created_at,
        (SELECT COUNT(*) FROM sms_queue WHERE sms_campaign_id = c.id) as total_sends,
        (SELECT COUNT(*) FROM sms_queue WHERE sms_campaign_id = c.id AND status = 'delivered') as total_delivered,
        (SELECT COUNT(*) FROM sms_queue WHERE sms_campaign_id = c.id AND status = 'failed') as total_failed
    FROM sms_campaigns c
    WHERE c.team_id = ? AND (c.status = 'sent' OR c.status = 'sending')";

if ($search) {
    $sql = $base_query . " AND c.message_body LIKE ? ORDER BY c.created_at DESC";
    $stmt = $mysqli->prepare($sql);
    $search_param = "%{$search}%";
    $stmt->bind_param('is', $team_id, $search_param);
} else {
    $sql = $base_query . " ORDER BY c.created_at DESC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $team_id);
}
$stmt->execute();
$campaigns = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head><title>SMS Campaign Reports</title><link rel="stylesheet" href="/css/dashboard_style.css"></head>
<body>
    <?php include APP_ROOT . '/public/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>SMS Campaign Reports</h1>
            <form method="get" action="/sms-reports">
                <input type="text" name="search" placeholder="Search by message content..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">Search</button>
            </form>
            <div class="table-container">
                <table>
                    <thead><tr><th>Sender ID</th><th>Message</th><th>Sends</th><th>Delivered</th><th>Failed</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if ($campaigns->num_rows > 0): ?>
                        <?php while ($campaign = $campaigns->fetch_assoc()):
                            $delivery_rate = $campaign['total_sends'] > 0 ? ($campaign['total_delivered'] / $campaign['total_sends']) * 100 : 0;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($campaign['sender_id']); ?></td>
                            <td><?php echo htmlspecialchars(substr($campaign['message_body'], 0, 30)); ?>...</td>
                            <td><?php echo $campaign['total_sends']; ?></td>
                            <td><?php echo $campaign['total_delivered']; ?> (<?php echo round($delivery_rate, 2); ?>%)</td>
                            <td><?php echo $campaign['total_failed']; ?></td>
                            <td><?php echo htmlspecialchars($campaign['status']); ?></td>
                            <td><?php echo $campaign['created_at']; ?></td>
                            <td><a href="/view-sms-report?id=<?php echo $campaign['id']; ?>">View Details</a></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8">No SMS campaigns have been sent yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <?php include APP_ROOT . '/public/includes/footer.php'; ?>
</body>
</html>
