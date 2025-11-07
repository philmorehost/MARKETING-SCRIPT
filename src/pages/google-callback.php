<?php
// src/pages/google-callback.php
require_once APP_ROOT . '/vendor/autoload.php';

$google_client_id = get_setting('google_client_id');
$google_client_secret = get_setting('google_client_secret');
$redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . '/google-callback.php';

$client = new Google_Client();
$client->setClientId($google_client_id);
$client->setClientSecret($google_client_secret);
$client->setRedirectUri($redirect_uri);

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        // Handle error
        header('Location: /login?error=google_auth_failed');
        exit;
    }

    $client->setAccessToken($token['access_token']);

    $google_oauth = new Google_Service_Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();

    $email = $google_account_info->email;
    $name = $google_account_info->name;
    $google_id = $google_account_info->id;
    $avatar_url = $google_account_info->picture;

    // Check if user exists
    $stmt = $mysqli->prepare("SELECT id, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // User exists, log them in
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];

        // Update Google ID and avatar if missing
        $update_stmt = $mysqli->prepare("UPDATE users SET google_id = ?, avatar_url = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $google_id, $avatar_url, $user['id']);
        $update_stmt->execute();

    } else {
        // New user, register them
        $status = 'active'; // Auto-activate for social logins
        $role = 'user';

        // No password for Google-signed-up users
        $insert_stmt = $mysqli->prepare("INSERT INTO users (name, email, google_id, avatar_url, status, role) VALUES (?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("ssssss", $name, $email, $google_id, $avatar_url, $status, $role);

        if ($insert_stmt->execute()) {
            $user_id = $insert_stmt->insert_id;
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_role'] = $role;
        } else {
            // Handle registration error
            header('Location: /register?error=google_registration_failed');
            exit;
        }
    }

    // Redirect to dashboard
    header('Location: /dashboard');
    exit;

} else {
    // No code, redirect to login
    header('Location: /login');
    exit;
}
