<?php
session_start();
require_once '../config/db.php';
require_once '../lib/functions.php';

$code = $_GET['code'] ?? '';
if (empty($code)) {
    die('Error: No code provided.');
}

$fb_app_id = get_setting('facebook_app_id', $mysqli);
$fb_app_secret = get_setting('facebook_app_secret', $mysqli);
$redirect_uri = "http://{$_SERVER['HTTP_HOST']}/public/facebook_callback.php";

// Exchange code for access token
$token_url = "https://graph.facebook.com/v12.0/oauth/access_token?client_id={$fb_app_id}&redirect_uri={$redirect_uri}&client_secret={$fb_app_secret}&code={$code}";
$response = file_get_contents($token_url);
$params = json_decode($response, true);
$access_token = $params['access_token'] ?? '';

if (empty($access_token)) {
    die('Error getting access token.');
}

// Get user's pages
$pages_url = "https://graph.facebook.com/me/accounts?access_token={$access_token}";
$pages_response = file_get_contents($pages_url);
$pages = json_decode($pages_response, true)['data'] ?? [];

// In a real app, you would show a list of pages for the user to choose from.
// For simplicity, we'll just save the first one.
if (!empty($pages)) {
    $page = $pages[0];
    $page_id = $page['id'];
    $page_name = $page['name'];
    $page_access_token = $page['access_token'];

    $stmt = $mysqli->prepare("INSERT INTO social_accounts (user_id, team_id, provider, account_id, account_name, access_token) VALUES (?, ?, 'facebook', ?, ?, ?) ON DUPLICATE KEY UPDATE account_name = ?, access_token = ?");
    $stmt->bind_param('iisssss', $_SESSION['user_id'], $_SESSION['team_id'], $page_id, $page_name, $page_access_token, $page_name, $page_access_token);
    $stmt->execute();
}

header('Location: /public/social-posts.php');
exit;
