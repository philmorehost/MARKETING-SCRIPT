<?php
define('APP_ROOT', dirname(__DIR__));
session_start();

// Security check: only admins can access this area
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login');
    exit;
}

require_once APP_ROOT . '/config/db.php';
require_once APP_ROOT . '/src/lib/functions.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/admin';
$route = str_replace($base_path, '', $request_uri);
$route = strtok($route, '?');
$route = trim($route, '/');
$route = $route ?: 'dashboard'; // Default route

$routes = [
    'dashboard' => 'dashboard.php',
    'users' => 'users.php',
    'settings' => 'settings.php',
    'payments' => 'payments.php',
    'transactions' => 'transactions.php',
    'support' => 'support.php',
    'view-admin-ticket' => 'view-admin-ticket.php',
    'cms-homepage' => 'cms_homepage.php',
    'cms-features' => 'cms_features.php',
    'cms-pricing' => 'cms_pricing.php',
    'cms-testimonials' => 'cms_testimonials.php',
    'cms-pages' => 'cms_pages.php',
];

if (array_key_exists($route, $routes)) {
    $page_file = APP_ROOT . '/admin/' . $routes[$route];
    if (file_exists($page_file)) {
        include $page_file;
    } else {
        http_response_code(404);
        echo "<h1>404 Not Found (Admin)</h1>";
    }
} else {
    http_response_code(404);
    echo "<h1>404 Not Found (Admin)</h1>";
}

$mysqli->close();
