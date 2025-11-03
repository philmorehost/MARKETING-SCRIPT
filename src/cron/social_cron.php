<?php
// --- social_cron.php ---
// Runs every minute to publish scheduled social media posts.

require_once dirname(__FILE__) . '/../../config/db.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$batch_limit = 50;

// Fetch posts scheduled for now or in the past that are still queued
$stmt = $mysqli->prepare(
    "SELECT id, user_id, provider, account_id, message
     FROM social_posts_queue
     WHERE status = 'queued' AND scheduled_at <= NOW()
     ORDER BY scheduled_at ASC
     LIMIT ?"
);
$stmt->bind_param('i', $batch_limit);
$stmt->execute();
$posts = $stmt->get_result();

if ($posts->num_rows === 0) {
    echo "No due social posts to publish.\n";
    exit;
}

while ($post = $posts->fetch_assoc()) {
    $post_id = $post['id'];
    $provider = $post['provider'];

    // Mark as 'sending'
    $mysqli->query("UPDATE social_posts_queue SET status = 'sending' WHERE id = $post_id");

    // --- Placeholder API Call Logic ---
    // In a real app, you would fetch the user's encrypted access token from
    // the `social_accounts` table and use the respective platform's SDK.
    $success = false;
    $error_message = '';

    switch ($provider) {
        case 'facebook':
            // $fb_sdk->post('/' . $post['account_id'] . '/feed', ['message' => $post['message']], $access_token);
            $success = true;
            break;
        case 'twitter':
            // Use Twitter API to post a tweet
            $success = true;
            break;
        case 'linkedin':
            // Use LinkedIn API to share a post
            $success = true;
            break;
    }
    // --- End Placeholder ---

    // Update post status
    if ($success) {
        $mysqli->query("UPDATE social_posts_queue SET status = 'sent' WHERE id = $post_id");
        echo "Published post ID {$post_id} to {$provider}.\n";
    } else {
        $update_stmt = $mysqli->prepare("UPDATE social_posts_queue SET status = 'failed', error_message = ? WHERE id = ?");
        $error_message = "API call failed (placeholder).";
        $update_stmt->bind_param('si', $error_message, $post_id);
        $update_stmt->execute();
        echo "Failed to publish post ID {$post_id}.\n";
    }
}

echo "Social cron run finished.\n";
