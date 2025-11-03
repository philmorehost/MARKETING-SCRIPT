<?php
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];
$campaign_id = (int)($_GET['id'] ?? 0);

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Fetch campaign details and verify ownership
$stmt = $mysqli->prepare("SELECT sender_id, message_body FROM sms_campaigns WHERE id = ? AND team_id = ?");
$stmt->bind_param('ii', $campaign_id, $team_id);
$stmt->execute();
$campaign = $stmt->get_result()->fetch_assoc();
if (!$campaign) {
    header('Location: sms-reports.php');
    exit;
}

// Fetch message statuses
$statuses_result = $mysqli->query("SELECT phone_number, status FROM sms_queue WHERE sms_campaign_id = $campaign_id");
?>
<!DOCTYPE html>
<html lang="en">
<head><title>SMS Report</title><link rel="stylesheet" href="css/dashboard_style.css"></head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include 'includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>SMS Report for: "<?php echo htmlspecialchars($campaign['sender_id']); ?>"</h1>
            <p><?php echo htmlspecialchars($campaign['message_body']); ?></p>
            <a href="sms-reports.php">&larr; Back to all reports</a>

            <table>
                <thead><tr><th>Phone Number</th><th>Delivery Status</th></tr></thead>
                <tbody>
                <?php while ($status = $statuses_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($status['phone_number']); ?></td>
                        <td><?php echo htmlspecialchars($status['status']); ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </main>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
