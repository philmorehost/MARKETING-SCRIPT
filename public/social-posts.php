<?php
session_start();
require_once '../config/db.php';
require_once '../src/lib/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$message = '';

// Fetch cost from settings
$price_per_post = (float)get_setting('price_per_social_post', $mysqli, 10);

// Handle Post Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_post'])) {
    $message_body = trim($_POST['message'] ?? '');
    $accounts = $_POST['accounts'] ?? []; // e.g., ['facebook-123', 'twitter-456']
    $scheduled_at = $_POST['scheduled_at'] ?? date('Y-m-d H:i:s');

    $num_accounts = count($accounts);
    $total_cost = $num_accounts * $price_per_post;

    // Check user balance
    $stmt = $mysqli->prepare("SELECT credit_balance FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_balance = $stmt->get_result()->fetch_assoc()['credit_balance'];

    if ($user_balance >= $total_cost) {
        $mysqli->begin_transaction();
        try {
            $mysqli->query("UPDATE users SET credit_balance = credit_balance - $total_cost WHERE id = $user_id");

            $stmt = $mysqli->prepare("INSERT INTO social_posts_queue (user_id, provider, account_id, message, status, scheduled_at, cost_in_credits) VALUES (?, ?, ?, ?, 'queued', ?, ?)");

            foreach ($accounts as $account) {
                list($provider, $account_id) = explode('-', $account, 2);
                $cost_per_single_post = $price_per_post;
                $stmt->bind_param('issssd', $user_id, $provider, $account_id, $message_body, $scheduled_at, $cost_per_single_post);
                $stmt->execute();
            }

            $mysqli->commit();
            $message = "Social posts scheduled successfully!";

        } catch (Exception $e) {
            $mysqli->rollback();
            $message = "An error occurred: " . $e->getMessage();
        }
    } else {
        $message = "Insufficient credits.";
    }
}

// Fetch queued posts
$posts_result = $mysqli->prepare("SELECT provider, account_id, message, status, scheduled_at FROM social_posts_queue WHERE user_id = ? ORDER BY scheduled_at DESC");
$posts_result->bind_param('i', $user_id);
$posts_result->execute();
$posts = $posts_result->get_result();
?>

<!DOCTYPE html>
<html lang="en"><head><title>Social Media Scheduler</title></head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include 'includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Social Media Scheduler</h1>
            <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>

            <h2>Connect Accounts</h2>
            <p>Connect your social media accounts to get started. (OAuth flow placeholder)</p>
            <button>Connect Facebook</button>
            <button>Connect X (Twitter)</button>
            <button>Connect LinkedIn</button>

            <hr>
            <h2>Create a Post</h2>
            <form action="social-posts.php" method="post">
                <input type="hidden" name="schedule_post" value="1">
                <textarea name="message" rows="5" required placeholder="What's on your mind?"></textarea><br>

                <h3>Select Accounts to Post To</h3>
                <!-- This would be dynamically populated from the social_accounts table -->
                <label><input type="checkbox" name="accounts[]" value="facebook-12345"> Facebook Page: My Biz</label><br>
                <label><input type="checkbox" name="accounts[]" value="twitter-67890"> Twitter: @mybiz</label><br>

                <label for="scheduled_at">Schedule for (leave blank for now)</label>
                <input type="datetime-local" id="scheduled_at" name="scheduled_at"><br>

                <p>Cost: <?php echo $price_per_post; ?> credits per account selected.</p>
                <button type="submit">Schedule Post</button>
            </form>

            <hr>
            <h2>Scheduled Posts</h2>
            <table>
                <thead><tr><th>Platform</th><th>Message</th><th>Status</th><th>Date</th></tr></thead>
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
        </main>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
