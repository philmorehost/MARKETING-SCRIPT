<?php
// admin/auth_admin.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not authenticated or not an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login');
    exit;
}

// Check for "Login As User" mode
if (isset($_SESSION['admin_user_id'])) {
    // Admins in "Login As User" mode should not be able to access the admin area.
    // They must first "logout" of the user's account to return to their own.
    // We can handle this by redirecting them to the user dashboard.
    header('Location: /dashboard');
    exit;
}

// We can assume the global $mysqli and functions are already included by a front controller,
// but for direct access or testing, it's good practice to include them.
$app_root = dirname(__DIR__);
require_once $app_root . '/config/db.php';
require_once $app_root . '/src/lib/functions.php';

// Create a global mysqli connection if it doesn't exist
if (!isset($mysqli) || !$mysqli) {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_error) {
        die("Database connection failed: " . $mysqli->connect_error);
    }
}

// Fetch admin user details
$admin_stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
$admin_stmt->bind_param("i", $_SESSION['user_id']);
$admin_stmt->execute();
$admin_user = $admin_stmt->get_result()->fetch_assoc();

if (!$admin_user) {
    // This case would be rare, but it's a good safeguard.
    // It means the user ID in the session is not a valid admin.
    session_destroy();
    header('Location: /login');
    exit;
}
