<?php
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];

// Fetch sent email campaigns for the team
$team_id = $_SESSION['team_id'];
$search = $_GET['search'] ?? '';
if ($search) {
    $stmt = $mysqli->prepare("SELECT id, subject, cost_in_credits, status, created_at FROM campaigns WHERE team_id = ? AND (status = 'sent' OR status = 'sending') AND subject LIKE ? ORDER BY created_at DESC");
    $search_param = "%{$search}%";
    $stmt->bind_param('is', $team_id, $search_param);
} else {
    $stmt = $mysqli->prepare("SELECT id, subject, cost_in_credits, status, created_at FROM campaigns WHERE team_id = ? AND (status = 'sent' OR status = 'sending') ORDER BY created_at DESC");
    $stmt->bind_param('i', $team_id);
}
$stmt->execute();
$campaigns = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head><title>Email Campaign Reports</title><link rel="stylesheet" href="css/dashboard_style.css"></head>
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

            <table>
                <thead><tr><th>Subject</th><th>Cost (Credits)</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                <?php while ($campaign = $campaigns->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($campaign['subject']); ?></td>
                        <td><?php echo number_format($campaign['cost_in_credits'], 4); ?></td>
                        <td><?php echo htmlspecialchars($campaign['status']); ?></td>
                        <td><?php echo $campaign['created_at']; ?></td>
                        <td><a href="view-email-report.php?id=<?php echo $campaign['id']; ?>">View Details</a></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </main>
    </div>
    <?php include APP_ROOT . '/public/includes/footer.php'; ?>
</body>
</html>
