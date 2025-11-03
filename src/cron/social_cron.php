<?php
// --- social_cron.php ---
// Runs every minute to publish scheduled social media posts.

define('APP_ROOT', dirname(__DIR__, 2)); require_once APP_ROOT . '/config/db.php';
require_once APP_ROOT . '/src/lib/functions.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) { die("DB connection error"); }

$batch_limit = 50;
$stmt = $mysqli->prepare(
    "SELECT id, user_id, team_id, provider, account_id, message, image_url
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

// Fetch relevant API keys once
$facebook_app_id = get_setting('facebook_app_id', $mysqli);
$facebook_app_secret = get_setting('facebook_app_secret', $mysqli);


while ($post = $posts->fetch_assoc()) {
    $post_id = $post['id'];
    $provider = $post['provider'];

    // Mark as 'sending' to prevent reprocessing in the next run
    $mysqli->query("UPDATE social_posts_queue SET status = 'sending' WHERE id = $post_id");

    $success = false;
    $error_message = '';

    // Fetch the user's access token for this specific account
    $token_stmt = $mysqli->prepare("SELECT access_token FROM social_accounts WHERE team_id = ? AND provider = ? AND account_id = ?");
    $token_stmt->bind_param('iss', $post['team_id'], $post['provider'], $post['account_id']);
    $token_stmt->execute();
    $token_result = $token_stmt->get_result();

    if ($token_result->num_rows === 0) {
        $error_message = "Could not find a valid access token for this account.";
    } else {
        $access_token = $token_result->fetch_assoc()['access_token']; // Note: Assumes token is stored unencrypted for this example. A real app MUST encrypt this.

        switch ($provider) {
            case 'facebook':
                // For Facebook, you post to a page's feed using the Page's access token
                $endpoint = "https://graph.facebook.com/v12.0/{$post['account_id']}/feed";
                $params = ['message' => $post['message'], 'access_token' => $access_token];

                // Add image if exists
                if (!empty($post['image_url'])) {
                   $endpoint = "https://graph.facebook.com/v12.0/{$post['account_id']}/photos";
                   $params['url'] = "http://{$_SERVER['HTTP_HOST']}/public{$post['image_url']}"; // URL must be publicly accessible
                }

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $endpoint . '?' . http_build_query($params));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                $response = curl_exec($ch);
                $err = curl_error($ch);
                curl_close($ch);

                if ($err) {
                    $error_message = "cURL Error: " . $err;
                } else {
                    $response_data = json_decode($response, true);
                    if (isset($response_data['id'])) {
                        $success = true;
                    } else {
                        $error_message = "Facebook API Error: " . ($response_data['error']['message'] ?? 'Unknown error');
                    }
                }
                break;
            case 'twitter':
                // TODO: Implement Twitter API integration
                $error_message = "Twitter API not implemented.";
                break;
            case 'linkedin':
                // TODO: Implement LinkedIn API integration
                $error_message = "LinkedIn API not implemented.";
                break;
        }
    }

    // --- Update post status ---
    if ($success) {
        $mysqli->query("UPDATE social_posts_queue SET status = 'sent' WHERE id = $post_id");
        echo "Published post ID {$post_id} to {$provider}.\n";
    } else {
        $update_stmt = $mysqli->prepare("UPDATE social_posts_queue SET status = 'failed', error_message = ? WHERE id = ?");
        $update_stmt->bind_param('si', $error_message, $post_id);
        $update_stmt->execute();
        echo "Failed to publish post ID {$post_id}: {$error_message}\n";
    }
}

echo "Social cron run finished.\n";
$mysqli->close();
