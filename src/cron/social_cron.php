<?php
// This cron job should be run every minute.
require_once dirname(__DIR__) . '/config/db.php';

$limit = 100; // Process 100 posts per run
$stmt = $mysqli->prepare("
    SELECT spq.id, sa.access_token, spq.account_id, spq.message, spq.image_url
    FROM social_posts_queue spq
    JOIN social_accounts sa ON spq.account_id = sa.account_id AND spq.team_id = sa.team_id
    WHERE spq.status = 'queued' AND spq.scheduled_at <= NOW()
    LIMIT ?
");
$stmt->bind_param('i', $limit);
$stmt->execute();
$posts_to_send = $stmt->get_result();

if ($posts_to_send->num_rows > 0) {
    $update_stmt = $mysqli->prepare("UPDATE social_posts_queue SET status = ?, error_message = ? WHERE id = ?");

    while ($post = $posts_to_send->fetch_assoc()) {
        $page_access_token = $post['access_token'];
        $page_id = $post['account_id'];
        $message = $post['message'];
        $image_url = $post['image_url'];

        $status = 'failed';
        $error_message = '';

        if ($post['provider'] === 'facebook') {
            if ($image_url) {
                // Post with image
                $url = "https://graph.facebook.com/{$page_id}/photos";
                $data = [
                    'caption' => $message,
                    'url' => "http://{$_SERVER['HTTP_HOST']}/public{$image_url}",
                    'access_token' => $page_access_token
                ];
            } else {
                // Post without image
                $url = "https://graph.facebook.com/{$page_id}/feed";
                $data = [
                    'message' => $message,
                    'access_token' => $page_access_token
                ];
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);
            $result = json_decode($response, true);

            if (isset($result['id'])) {
                $status = 'sent';
            } else {
                $error_message = $result['error']['message'] ?? 'Unknown error.';
            }
        } // Add handlers for 'twitter' and 'linkedin' here

        $update_stmt->bind_param('ssi', $status, $error_message, $post['id']);
        $update_stmt->execute();
    }
}
