<?php
// src/pages/email-campaigns.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "Email Campaigns";

// Fetch user's email campaigns
$team_id_condition = $user['team_id'] ? "team_id = " . $user['team_id'] : "user_id = " . $user['id'];
$campaigns_query = $mysqli->query("
    SELECT *
    FROM campaigns
    WHERE $team_id_condition
    ORDER BY created_at DESC
");
$campaigns = $campaigns_query->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header_app.php';
?>

<div class="container app-content">
    <div class="page-header">
        <h1>Email Campaigns</h1>
        <a href="/create-email-campaign" class="btn btn-primary">Create New Campaign</a>
    </div>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>Campaign Subject</th>
                    <th>Status</th>
                    <th>Created On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($campaigns)): ?>
                    <tr><td colspan="4">You haven't created any email campaigns yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($campaigns as $campaign): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($campaign['subject']); ?></strong></td>
                        <td><span class="status-badge <?php echo $campaign['status']; ?>"><?php echo ucfirst($campaign['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($campaign['created_at'])); ?></td>
                        <td>
                            <a href="/view-email-report?id=<?php echo $campaign['id']; ?>" class="btn btn-sm btn-info">Report</a>
                            <a href="/create-email-campaign?id=<?php echo $campaign['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer_app.php';
?>
