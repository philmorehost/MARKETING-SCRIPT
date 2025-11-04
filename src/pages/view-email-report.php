<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
$user_id = $_SESSION['user_id'];
$campaign_id = (int)($_GET['id'] ?? 0);
$team_id = $_SESSION['team_id'];

// Fetch campaign details and verify ownership
$stmt_campaign = $mysqli->prepare("SELECT subject FROM campaigns WHERE id = ? AND team_id = ?");
$stmt_campaign->bind_param('ii', $campaign_id, $team_id);
$stmt_campaign->execute();
$campaign = $stmt_campaign->get_result()->fetch_assoc();
if (!$campaign) { header('Location: /email-reports'); exit; }

// --- Fetch Aggregated Analytics Data ---
$stmt_stats = $mysqli->prepare("
    SELECT
        (SELECT COUNT(*) FROM campaign_queue WHERE campaign_id = ?) as total_recipients,
        (SELECT COUNT(DISTINCT contact_id) FROM campaign_events WHERE campaign_id = ? AND event_type = 'open') as opens,
        (SELECT COUNT(DISTINCT contact_id) FROM campaign_events WHERE campaign_id = ? AND event_type = 'click') as clicks,
        (SELECT COUNT(*) FROM campaign_queue WHERE campaign_id = ? AND status = 'bounced') as bounces
");
$stmt_stats->bind_param('iiii', $campaign_id, $campaign_id, $campaign_id, $campaign_id);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();
$open_rate = ($stats['total_recipients'] > 0) ? ($stats['opens'] / $stats['total_recipients']) * 100 : 0;
$ctr = ($stats['opens'] > 0) ? ($stats['clicks'] / $stats['opens']) * 100 : 0; // Click-through Rate

// --- Fetch Data for Chart (events per hour for the first 24 hours) ---
$stmt_chart = $mysqli->prepare("
    SELECT HOUR(created_at) as hour, event_type, COUNT(*) as count
    FROM campaign_events
    WHERE campaign_id = ? AND created_at <= (SELECT created_at FROM campaigns WHERE id = ?) + INTERVAL 24 HOUR
    GROUP BY HOUR(created_at), event_type
");
$stmt_chart->bind_param('ii', $campaign_id, $campaign_id);
$stmt_chart->execute();
$chart_data_raw = $stmt_chart->get_result();
$chart_data = ['labels' => [], 'opens' => [], 'clicks' => []];
for ($i = 0; $i < 24; $i++) { $chart_data['labels'][] = "Hour {$i}"; $chart_data['opens'][$i] = 0; $chart_data['clicks'][$i] = 0; }
while ($row = $chart_data_raw->fetch_assoc()) {
    if ($row['event_type'] == 'open') $chart_data['opens'][(int)$row['hour']] = $row['count'];
    if ($row['event_type'] == 'click') $chart_data['clicks'][(int)$row['hour']] = $row['count'];
}


// --- Fetch Recent Events ---
$stmt_events = $mysqli->prepare("
    SELECT e.event_type, e.url_clicked, e.created_at, c.email
    FROM campaign_events e
    JOIN contacts c ON e.contact_id = c.id
    WHERE e.campaign_id = ?
    ORDER BY e.created_at DESC LIMIT 20
");
$stmt_events->bind_param('i', $campaign_id);
$stmt_events->execute();
$recent_events = $stmt_events->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Report: <?php echo htmlspecialchars($campaign['subject']); ?></title>
    <link rel="stylesheet" href="/public/css/dashboard_style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include APP_ROOT . '/public_html/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public_html/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <a href="/public/email-reports" class="back-link">&larr; Back to all reports</a>
            <h1><?php echo htmlspecialchars($campaign['subject']); ?></h1>
            <div class="stats-grid">
                <div class="card"><h3>Total Recipients</h3><p><?php echo $stats['total_recipients']; ?></p></div>
                <div class="card"><h3>Opens</h3><p><?php echo $stats['opens']; ?> (<?php echo number_format($open_rate, 2); ?>%)</p></div>
                <div class="card"><h3>Clicks (CTR)</h3><p><?php echo $stats['clicks']; ?> (<?php echo number_format($ctr, 2); ?>%)</p></div>
                <div class="card"><h3>Bounces</h3><p><?php echo $stats['bounces']; ?></p></div>
            </div>
            <div class="card"><canvas id="engagementChart"></canvas></div>
            <hr>
            <h2>Recent Activity</h2>
            <div class="table-container">
                <table>
                    <thead><tr><th>Contact</th><th>Event</th><th>Details</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php while ($event = $recent_events->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($event['email']); ?></td>
                        <td><?php echo ucfirst(htmlspecialchars($event['event_type'])); ?></td>
                        <td><?php if($event['event_type'] == 'click') echo htmlspecialchars($event['url_clicked']); ?></td>
                        <td><?php echo $event['created_at']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <script>
        const ctx = document.getElementById('engagementChart').getContext('2d');
        const engagementChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_data['labels']); ?>,
                datasets: [{
                    label: 'Opens',
                    data: <?php echo json_encode($chart_data['opens']); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }, {
                    label: 'Clicks',
                    data: <?php echo json_encode($chart_data['clicks']); ?>,
                    borderColor: 'rgb(255, 99, 132)',
                    tension: 0.1
                }]
            },
            options: { scales: { y: { beginAtZero: true } }, responsive: true, plugins: { title: { display: true, text: 'Engagement Over First 24 Hours' } } }
        });
    </script>
    <?php include APP_ROOT . '/public_html/includes/footer.php'; ?>
</body>
</html>
