<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];
$campaign_id = (int)($_GET['id'] ?? 0);

// Fetch campaign details and verify ownership
$stmt_campaign = $mysqli->prepare("SELECT sender_id, message_body FROM sms_campaigns WHERE id = ? AND team_id = ?");
$stmt_campaign->bind_param('ii', $campaign_id, $team_id);
$stmt_campaign->execute();
$campaign = $stmt_campaign->get_result()->fetch_assoc();
if (!$campaign) { header('Location: /sms-reports'); exit; }

// --- Fetch Aggregated Stats ---
$stmt_stats = $mysqli->prepare("
    SELECT status, COUNT(*) as count
    FROM sms_queue
    WHERE sms_campaign_id = ?
    GROUP BY status
");
$stmt_stats->bind_param('i', $campaign_id);
$stmt_stats->execute();
$stats_raw = $stmt_stats->get_result();
$stats = ['sent' => 0, 'delivered' => 0, 'failed' => 0, 'queued' => 0];
while ($row = $stats_raw->fetch_assoc()) {
    $stats[$row['status']] = $row['count'];
}
$total_sends = array_sum($stats);

// --- Fetch Individual Statuses with Pagination ---
$page = (int)($_GET['page'] ?? 1);
$limit = 50;
$offset = ($page - 1) * $limit;
$stmt_statuses = $mysqli->prepare("SELECT phone_number, status, updated_at FROM sms_queue WHERE sms_campaign_id = ? ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt_statuses->bind_param('iii', $campaign_id, $limit, $offset);
$stmt_statuses->execute();
$statuses_result = $stmt_statuses->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>SMS Report</title>
    <link rel="stylesheet" href="/public/css/dashboard_style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include APP_ROOT . '/public_html/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public_html/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <a href="/public/sms-reports" class="back-link">&larr; Back to all reports</a>
            <h1>SMS Report for: "<?php echo htmlspecialchars($campaign['sender_id']); ?>"</h1>
            <p><strong>Message:</strong> <?php echo htmlspecialchars($campaign['message_body']); ?></p>

            <div class="stats-grid">
                <div class="card"><h3>Total Recipients</h3><p><?php echo $total_sends; ?></p></div>
                <div class="card"><h3>Delivered</h3><p><?php echo $stats['delivered']; ?></p></div>
                <div class="card"><h3>Failed</h3><p><?php echo $stats['failed']; ?></p></div>
                <div class="card"><h3>Queued/Sending</h3><p><?php echo $stats['queued'] + $stats['sent']; ?></p></div>
            </div>
            <div class="card" style="max-width: 400px; margin: 20px auto;"><canvas id="deliveryChart"></canvas></div>
            <hr>
            <h2>Individual Message Statuses</h2>
            <div class="table-container">
                <table>
                    <thead><tr><th>Phone Number</th><th>Delivery Status</th><th>Last Updated</th></tr></thead>
                    <tbody>
                    <?php while ($status = $statuses_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($status['phone_number']); ?></td>
                            <td><?php echo htmlspecialchars($status['status']); ?></td>
                            <td><?php echo $status['updated_at']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination would go here -->
        </main>
    </div>
    <script>
        const ctx = document.getElementById('deliveryChart').getContext('2d');
        const deliveryChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Delivered', 'Failed', 'Queued/Sent'],
                datasets: [{
                    data: [<?php echo $stats['delivered']; ?>, <?php echo $stats['failed']; ?>, <?php echo $stats['queued'] + $stats['sent']; ?>],
                    backgroundColor: ['rgb(75, 192, 192)', 'rgb(255, 99, 132)', 'rgb(201, 203, 207)'],
                }]
            },
            options: { responsive: true, plugins: { title: { display: true, text: 'Delivery Status Breakdown' } } }
        });
    </script>
    <?php include APP_ROOT . '/public_html/includes/footer.php'; ?>
</body>
</html>
