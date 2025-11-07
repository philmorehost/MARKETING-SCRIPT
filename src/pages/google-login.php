<?php
// src/pages/google-login.php

require_once APP_ROOT . '/vendor/autoload.php';

// Get credentials from settings
$google_client_id = get_setting('google_client_id');
$google_client_secret = get_setting('google_client_secret');
$redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . '/google-callback.php';

if (empty($google_client_id) || empty($google_client_secret)) {
    // In a real app, you'd show a user-friendly error page.
    die("Google Login is not configured. Please contact the site administrator.");
}

$client = new Google_Client();
$client->setClientId($google_client_id);
$client->setClientSecret($google_client_secret);
$client->setRedirectUri($redirect_uri);
$client->addScope("email");
$client->addScope("profile");

// Redirect to Google's OAuth 2.0 server
header('Location: ' . $client->createAuthUrl());
exit;
