<?php
// src/pages/social-posts.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "Social Media Scheduler";
$cost_per_post = get_setting('price_per_social_post', 2);

// Fetch connected accounts
$team_id_condition = $user['team_id'] ? "team_id = " . $user['team_id'] : "user_id = " . $user['id'];
$accounts_q = $mysqli->query("SELECT * FROM social_accounts WHERE $team_id_condition");
$accounts = $accounts_q->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header_app.php';
?>

<div class="container app-content">
    <h1>Social Media Scheduler</h1>
    <div class="card">
        <form action="/schedule-social-post" method="POST">
            <div class="form-group">
                <label>Select Accounts to Post to</label>
                <?php if(empty($accounts)): ?>
                    <p>No social accounts connected. <a href="/social-accounts">Connect one now</a>.</p>
                <?php else: foreach($accounts as $account): ?>
                    <div class="form-check">
                        <input type="checkbox" name="accounts[]" value="<?php echo $account['id']; ?>" id="acc_<?php echo $account['id']; ?>">
                        <label for="acc_<?php echo $account['id']; ?>"><?php echo htmlspecialchars($account['account_name']); ?> (<?php echo ucfirst($account['provider']); ?>)</label>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <div class="form-group">
                <label for="message">Message</label>
                <textarea name="message" id="message" rows="7" class="form-control" required></textarea>
            </div>

            <div class="form-group">
                <label for="schedule_time">Schedule Time (Optional)</label>
                <input type="datetime-local" name="schedule_time" id="schedule_time" class="form-control">
                <small>Leave blank to post now.</small>
            </div>

            <p>Cost: <?php echo $cost_per_post; ?> credits per account selected.</p>
            <button type="submit" class="btn btn-primary" <?php if(empty($accounts)) echo 'disabled'; ?>>Schedule Post</button>
        </form>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer_app.php';
?>
