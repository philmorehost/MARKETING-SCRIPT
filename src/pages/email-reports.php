<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /public/login');
    exit;
}
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];

// Fetch sent email campaigns for the team with aggregated stats
$search = $_GET['search'] ?? '';
$base_query = "
    SELECT
        c.id, c.subject, c.cost_in_credits, c.status, c.created_at,
        (SELECT COUNT(*) FROM campaign_queue WHERE campaign_id = c.id) as total_sends,
        (SELECT COUNT(*) FROM campaign_queue WHERE campaign_id = c.id AND status = 'bounced') as total_bounces,
        (SELECT COUNT(*) FROM campaign_events WHERE campaign_id = c.id AND event_type = 'open') as total_opens,
        (SELECT COUNT(*) FROM campaign_events WHERE campaign_id = c.id AND event_type = 'click') as total_clicks
    FROM campaigns c
    WHERE c.team_id = ? AND (c.status = 'sent' OR c.status = 'sending')";

if ($search) {
    $sql = $base_query . " AND c.subject LIKE ? ORDER BY c.created_at DESC";
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
<head><title>Email Campaign Reports</title><link rel="stylesheet" href="/public/css/dashboard_style.css"></head>
<body>
    <?php include APP_ROOT . '/public/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Email Campaign Reports</h1>
            <form method="get" action="/public/email-reports">
                <input type="text" name="search" placeholder="Search by subject..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">Search</button>
            </form>
            <div class="table-container">
                <table>
                    <thead><tr><th>Subject</th><th>Sends</th><th>Bounces</th><th>Opens</th><th>Clicks</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if ($campaigns->num_rows > 0): ?>
                        <?php while ($campaign = $campaigns->fetch_assoc()):
                            $open_rate = $campaign['total_sends'] > 0 ? ($campaign['total_opens'] / $campaign['total_sends']) * 100 : 0;
                            $click_rate = $campaign['total_opens'] > 0 ? ($campaign['total_clicks'] / $campaign['total_opens']) * 100 : 0; // Click-through rate based on opens
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($campaign['subject']); ?></td>
                            <td><?php echo $campaign['total_sends']; ?></td>
                            <td><?php echo $campaign['total_bounces']; ?></td>
                            <td><?php echo $campaign['total_opens']; ?> (<?php echo round($open_rate, 2); ?>%)</td>
                            <td><?php echo $campaign['total_clicks']; ?> (<?php echo round($click_rate, 2); ?>%)</td>
                            <td><?php echo htmlspecialchars($campaign['status']); ?></td>
                            <td><?php echo $campaign['created_at']; ?></td>
                            <td><a href="/public/view-email-report?id=<?php echo $campaign['id']; ?>">View Details</a></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8">No email campaigns have been sent yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <?php include APP_ROOT . '/public/includes/footer.php'; ?>
</body>
</html>
