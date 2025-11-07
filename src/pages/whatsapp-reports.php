<?php
// src/pages/whatsapp-reports.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "WhatsApp Campaign Reports";

// Fetch sent whatsapp campaigns
$team_id_condition = $user['team_id'] ? "team_id = " . $user['team_id'] : "user_id = " . $user['id'];
$campaigns_query = $mysqli->query("
    SELECT wc.id, wc.template_name, wc.created_at,
           (SELECT COUNT(*) FROM whatsapp_queue wq WHERE wq.campaign_id = wc.id AND wq.status = 'delivered') as total_delivered,
           (SELECT COUNT(*) FROM whatsapp_queue wq WHERE wq.campaign_id = wc.id AND wq.status = 'read') as total_read
    FROM whatsapp_campaigns wc
    WHERE wc.$team_id_condition
    ORDER BY wc.created_at DESC
");
$campaigns = $campaigns_query->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header_app.php';
?>
<div class="container app-content">
    <h1>WhatsApp Campaign Reports</h1>
    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Delivered</th>
                    <th>Read</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($campaigns as $c): ?>
                <tr>
                    <td><?php echo htmlspecialchars($c['template_name']); ?></td>
                    <td><?php echo $c['total_delivered']; ?></td>
                    <td><?php echo $c['total_read']; ?></td>
                    <td><a href="/view-whatsapp-report?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-info">View Report</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
include __DIR__ . '/../includes/footer_app.php';
?>
