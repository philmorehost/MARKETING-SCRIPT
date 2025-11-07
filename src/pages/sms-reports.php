<?php
// src/pages/sms-reports.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "SMS Campaign Reports";

// Fetch sent sms campaigns
$team_id_condition = $user['team_id'] ? "team_id = " . $user['team_id'] : "user_id = " . $user['id'];
$campaigns_query = $mysqli->query("
    SELECT sc.id, sc.message_body, sc.created_at,
           (SELECT COUNT(*) FROM sms_queue sq WHERE sq.sms_campaign_id = sc.id AND sq.status = 'delivered') as total_delivered
    FROM sms_campaigns sc
    WHERE sc.$team_id_condition
    ORDER BY sc.created_at DESC
");
$campaigns = $campaigns_query->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header_app.php';
?>
<div class="container app-content">
    <h1>SMS Campaign Reports</h1>
    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Delivered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($campaigns as $c): ?>
                <tr>
                    <td><?php echo htmlspecialchars(substr($c['message_body'], 0, 50)); ?>...</td>
                    <td><?php echo $c['total_delivered']; ?></td>
                    <td><a href="/view-sms-report?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-info">View Report</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
include __DIR__ . '/../includes/footer_app.php';
?>
