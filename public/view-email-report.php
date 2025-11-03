<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$campaign_id = (int)($_GET['id'] ?? 0);

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$team_id = $_SESSION['team_id'];

// Fetch campaign details and verify ownership
$stmt = $mysqli->prepare("SELECT subject FROM campaigns WHERE id = ? AND team_id = ?");
$stmt->bind_param('ii', $campaign_id, $team_id);
$stmt->execute();
$campaign = $stmt->get_result()->fetch_assoc();
if (!$campaign) {
    header('Location: email-reports.php');
    exit;
}

// --- Simulated Analytics Data ---
// In a real system, this data would be aggregated from the `campaign_events` table,
// which would be populated by a webhook listener.
$total_recipients = $mysqli->query("SELECT COUNT(*) FROM campaign_queue WHERE campaign_id = $campaign_id")->fetch_row()[0];
$opens = rand( (int)($total_recipients * 0.2), (int)($total_recipients * 0.8) );
$clicks = rand( (int)($opens * 0.1), (int)($opens * 0.5) );
$bounces = rand(0, (int)($total_recipients * 0.05));
$open_rate = ($total_recipients > 0) ? ($opens / $total_recipients) * 100 : 0;
// --- End Simulation ---

?>
<!DOCTYPE html>
<html lang="en">
<head><title>Report for: <?php echo htmlspecialchars($campaign['subject']); ?></title><link rel="stylesheet" href="css/dashboard_style.css"></head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include 'includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Report for: "<?php echo htmlspecialchars($campaign['subject']); ?>"</h1>
            <a href="email-reports.php">&larr; Back to all reports</a>

            <div class="stats-grid">
                <div class="card"><h3>Total Recipients</h3><p><?php echo $total_recipients; ?></p></div>
                <div class="card"><h3>Opens</h3><p><?php echo $opens; ?> (<?php echo number_format($open_rate, 2); ?>%)</p></div>
                <div class="card"><h3>Clicks</h3><p><?php echo $clicks; ?></p></div>
                <div class="card"><h3>Bounces</h3><p><?php echo $bounces; ?></p></div>
            </div>

            <!-- A list of recent events would go here -->
        </main>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
