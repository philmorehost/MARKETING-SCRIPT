<?php
session_start();
require_once '../config/db.php';
require_once '../lib/functions.php';

$fb_app_id = get_setting('facebook_app_id', $mysqli);
$redirect_uri = urlencode("http://{$_SERVER['HTTP_HOST']}/public/facebook_callback.php");

$auth_url = "https://www.facebook.com/v12.0/dialog/oauth?client_id={$fb_app_id}&redirect_uri={$redirect_uri}&scope=pages_manage_posts,pages_read_engagement";
header('Location: ' . $auth_url);
exit;
