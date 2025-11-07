<?php
// src/pages/email-reports.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "Email Campaign Reports";

// Fetch sent email campaigns
$team_id_condition = $user['team_id'] ? "team_id = " . $user['team_id'] : "user_id = " . $user['id'];
$campaigns_query = $mysqli->query("
    SELECT c.id, c.subject, c.created_at,
           (SELECT COUNT(*) FROM campaign_queue cq WHERE cq.campaign_id = c.id) as total_sent,
           (SELECT COUNT(*) FROM campaign_events ce WHERE ce.campaign_id = c.id AND ce.event_type = 'open') as total_opens,
           (SELECT COUNT(*) FROM campaign_events ce WHERE ce.campaign_id = c.id AND ce.event_type = 'click') as total_clicks
    FROM campaigns c
    WHERE c.$team_id_condition AND c.status IN ('sent', 'sending')
    ORDER BY c.created_at DESC
");
$campaigns = $campaigns_query->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header_app.php';
?>
<div class="container app-content">
    <h1>Email Campaign Reports</h1>
    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Sent</th>
                    <th>Opens</th>
                    <th>Clicks</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($campaigns as $c): ?>
                <tr>
                    <td><?php echo htmlspecialchars($c['subject']); ?></td>
                    <td><?php echo $c['total_sent']; ?></td>
                    <td><?php echo $c['total_opens']; ?></td>
                    <td><?php echo $c['total_clicks']; ?></td>
                    <td><a href="/view-email-report?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-info">View Report</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
include __DIR__ . '/../includes/footer_app.php';
?>
