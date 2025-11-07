<?php
// src/pages/schedule-social-post.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /social-posts');
    exit;
}

$accounts = $_POST['accounts'] ?? [];
$message = trim($_POST['message'] ?? '');
$schedule_time = trim($_POST['schedule_time'] ?? '');

$total_accounts = count($accounts);
$cost_per_post = get_setting('price_per_social_post', 2);
$total_cost = $total_accounts * $cost_per_post;

if ($user['credit_balance'] < $total_cost) {
    // Redirect with error
    exit;
}

$mysqli->begin_transaction();
try {
    $mysqli->query("UPDATE users SET credit_balance = credit_balance - $total_cost WHERE id = {$user['id']}");

    $scheduled_at = !empty($schedule_time) ? date('Y-m-d H:i:s', strtotime($schedule_time)) : date('Y-m-d H:i:s');

    $stmt = $mysqli->prepare("INSERT INTO social_posts_queue (user_id, team_id, provider, account_id, message, scheduled_at, cost_in_credits) SELECT ?, ?, provider, account_id, ?, ?, ? FROM social_accounts WHERE id = ?");

    foreach ($accounts as $account_id) {
        $stmt->bind_param("iisssi", $user['id'], $user['team_id'], $message, $scheduled_at, $cost_per_post, $account_id);
        $stmt->execute();
    }

    $mysqli->query("INSERT INTO transactions (user_id, type, description, amount_credits, status) VALUES ({$user['id']}, 'spend_social_post', 'Social media post', $total_cost, 'completed')");

    $mysqli->commit();
    header('Location: /social-posts');
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    // Redirect with error
    exit;
}
