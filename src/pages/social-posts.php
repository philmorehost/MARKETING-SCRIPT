<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];
$team_owner_id = $_SESSION['team_owner_id'];
$message = '';

// Fetch cost from settings
$price_per_post = (float)get_setting('price_per_social_post', $mysqli, 10);

// Fetch connected social accounts for this team
$accounts_result = $mysqli->prepare("SELECT id, provider, account_name FROM social_accounts WHERE team_id = ?");
$accounts_result->bind_param('i', $team_id);
$accounts_result->execute();
$connected_accounts = $accounts_result->get_result();

// Handle Post Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_post'])) {
    $message_body = trim($_POST['message'] ?? '');
    $account_ids = $_POST['accounts'] ?? []; // Array of IDs from social_accounts table
    $scheduled_at = $_POST['scheduled_at'] ?? date('Y-m-d H:i:s');
    $image = $_FILES['image'] ?? null;

    $num_accounts = count($account_ids);
    if (empty($message_body) || $num_accounts === 0) {
        $message = "Please write a message and select at least one account to post to.";
    } else {
        $total_cost = $num_accounts * $price_per_post;
        $stmt_balance = $mysqli->prepare("SELECT credit_balance FROM users WHERE id = ?");
        $stmt_balance->bind_param('i', $team_owner_id);
        $stmt_balance->execute();
        $user_balance = (float)$stmt_balance->get_result()->fetch_assoc()['credit_balance'];

        if ($user_balance >= $total_cost) {
            $mysqli->begin_transaction();
            try {
                $update_credits_stmt = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance - ? WHERE id = ?");
                $update_credits_stmt->bind_param('di', $total_cost, $team_owner_id);
                $update_credits_stmt->execute();

                $image_url = null;
                if ($image && $image['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = APP_ROOT . '/public_html/uploads/social_media/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $filename = uniqid('sm_', true) . '.' . pathinfo($image['name'], PATHINFO_EXTENSION);
                    if (move_uploaded_file($image['tmp_name'], $upload_dir . $filename)) {
                        $image_url = '/uploads/social_media/' . $filename;
                    }
                }

                $stmt_fetch_acc = $mysqli->prepare("SELECT provider, account_id FROM social_accounts WHERE id = ? AND team_id = ?");
                $stmt_insert_post = $mysqli->prepare("INSERT INTO social_posts_queue (user_id, team_id, provider, account_id, message, image_url, status, scheduled_at, cost_in_credits) VALUES (?, ?, ?, ?, ?, ?, 'queued', ?, ?)");

                foreach ($account_ids as $account_db_id) {
                    $stmt_fetch_acc->bind_param('ii', $account_db_id, $team_id);
                    $stmt_fetch_acc->execute();
                    $account = $stmt_fetch_acc->get_result()->fetch_assoc();
                    if ($account) {
                        $stmt_insert_post->bind_param('iisssssd', $user_id, $team_id, $account['provider'], $account['account_id'], $message_body, $image_url, $scheduled_at, $price_per_post);
                        $stmt_insert_post->execute();
                    }
                }
                $mysqli->commit();
                $message = "Social posts scheduled successfully!";
            } catch (Exception $e) {
                $mysqli->rollback();
                $message = "An error occurred: " . $e->getMessage();
            }
        } else {
            $message = "Insufficient credits. You need {$total_cost} credits to schedule these posts.";
        }
    }
}

// Fetch queued posts for the team
$posts_result = $mysqli->prepare("SELECT provider, message, status, scheduled_at FROM social_posts_queue WHERE team_id = ? ORDER BY scheduled_at DESC");
$posts_result->bind_param('i', $team_id);
$posts_result->execute();
$posts = $posts_result->get_result();
?>
<!DOCTYPE html>
<html lang="en"><head><title>Social Media Scheduler</title><link rel="stylesheet" href="/public/css/dashboard_style.css"></head>
<body>
    <?php include APP_ROOT . '/public_html/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public_html/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Social Media Scheduler</h1>
            <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>
            <div class="card">
                <h2>Connect Accounts</h2>
                <p>Connect your social media accounts to get started. (OAuth setup is handled by the admin).</p>
                <div class="social-buttons">
                    <button disabled>Connect Facebook Page</button>
                    <button disabled>Connect X (Twitter)</button>
                    <button disabled>Connect LinkedIn Page</button>
                </div>
            </div>
            <hr>
            <div class="card">
                <h2>Create a Post</h2>
                <form action="/public/social-posts" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="schedule_post" value="1">
                    <div class="form-group"><label for="message">Message</label><textarea id="message" name="message" rows="5" required></textarea></div>
                    <div class="form-group"><label for="image">Attach Image</label><input type="file" id="image" name="image" accept="image/*"></div>
                    <div class="form-group">
                        <h3>Select Accounts to Post To</h3>
                        <?php if($connected_accounts->num_rows > 0): ?>
                            <?php $connected_accounts->data_seek(0); while($account = $connected_accounts->fetch_assoc()): ?>
                            <label><input type="checkbox" name="accounts[]" value="<?php echo $account['id']; ?>"> <?php echo ucfirst($account['provider']); ?>: <?php echo htmlspecialchars($account['account_name']); ?></label><br>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p>No social accounts connected yet. Please ask your admin to configure them.</p>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="scheduled_at">Schedule for (leave blank for now)</label>
                        <input type="datetime-local" id="scheduled_at" name="scheduled_at">
                    </div>
                    <p>Cost: <strong><?php echo $price_per_post; ?> credits</strong> per account selected.</p>
                    <button type="submit" <?php if($connected_accounts->num_rows === 0) echo 'disabled'; ?>>Schedule Post</button>
                </form>
            </div>
            <hr>
            <h2>Scheduled Posts</h2>
            <div class="table-container">
                <table>
                    <thead><tr><th>Platform</th><th>Message</th><th>Status</th><th>Scheduled Date</th></tr></thead>
                    <tbody>
                    <?php while($post = $posts->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($post['provider']); ?></td>
                        <td><?php echo htmlspecialchars(substr($post['message'], 0, 50)); ?>...</td>
                        <td><?php echo htmlspecialchars($post['status']); ?></td>
                        <td><?php echo $post['scheduled_at']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <?php include APP_ROOT . '/public_html/includes/footer.php'; ?>
</body>
</html>
