<?php
// src/pages/social-accounts.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "Social Accounts";

// Fetch connected accounts
$team_id_condition = $user['team_id'] ? "team_id = " . $user['team_id'] : "user_id = " . $user['id'];
$accounts_q = $mysqli->query("SELECT * FROM social_accounts WHERE $team_id_condition");
$accounts = $accounts_q->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header_app.php';
?>

<div class="container app-content">
    <h1>Manage Social Accounts</h1>
    <p>Connect your social media accounts to start scheduling posts.</p>

    <div class="card">
        <h3>Connect New Account</h3>
        <div class="social-connect-buttons">
            <a href="/connect-facebook" class="btn btn-social facebook"><i class="fab fa-facebook-f"></i> Connect Facebook</a>
            <a href="/connect-twitter" class="btn btn-social twitter"><i class="fab fa-twitter"></i> Connect Twitter</a>
            <a href="/connect-linkedin" class="btn btn-social linkedin"><i class="fab fa-linkedin-in"></i> Connect LinkedIn</a>
        </div>
    </div>

    <div class="card">
        <h3>Connected Accounts</h3>
        <table class="table">
            <thead>
                <tr><th>Provider</th><th>Account Name</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if(empty($accounts)): ?>
                    <tr><td colspan="3">No accounts connected.</td></tr>
                <?php else: foreach($accounts as $account): ?>
                    <tr>
                        <td><?php echo ucfirst($account['provider']); ?></td>
                        <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                        <td><a href="/disconnect-social?id=<?php echo $account['id']; ?>" class="btn btn-sm btn-danger">Disconnect</a></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer_app.php';
?>
