<?php
session_start();

if (!isset($_SESSION['admin_user_id'])) {
    header('Location: /public/login.php');
    exit;
}

// Restore admin session
$_SESSION['user_id'] = $_SESSION['admin_user_id'];
$_SESSION['user_role'] = $_SESSION['admin_user_role'];
unset($_SESSION['admin_user_id']);
unset($_SESSION['admin_user_role']);

// You might need to refetch user details like name, email, team_id here
// For simplicity, we'll just redirect to the admin dashboard.

header('Location: /admin/dashboard.php');
exit;
