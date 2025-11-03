<?php
// A very simple front controller
session_start();
require_once '../config/db.php';
require_once '../vendor/autoload.php';
require_once '../src/lib/functions.php';

// --- Routing ---
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/public'; // Adjust if your app is in a subdirectory
$route = str_replace($base_path, '', $request_uri);
$route = strtok($route, '?'); // Remove query string
$route = trim($route, '/');
$route = $route ?: 'home'; // Default route

// --- Define Routes ---
// Maps a URL route to a PHP file.
$routes = [
    'home' => 'home.php', // The public homepage
    'login' => 'login.php',
    'logout' => 'logout.php',
    'register' => 'register.php',
    'forgot-password' => 'forgot-password.php',
    'dashboard' => 'dashboard.php',
    'team' => 'team.php',
    'buy-credits' => 'buy-credits.php',
    'billing' => 'billing.php',
    'contact-lists' => 'contact-lists.php',
    'email-campaigns' => 'email-campaigns.php',
    'email-reports' => 'email-reports.php',
    'view-email-report' => 'view-email-report.php',
    'sms-campaigns' => 'sms-campaigns.php',
    'sms-reports' => 'sms-reports.php',
    'view-sms-report' => 'view-sms-report.php',
    'whatsapp-campaigns' => 'whatsapp-campaigns.php',
    'whatsapp-reports' => 'whatsapp-reports.php',
    'view-whatsapp-report' => 'view-whatsapp-report.php',
    'landing-pages' => 'landing-pages.php',
    'qr-codes' => 'qr-codes.php',
    'support' => 'support.php',
    'api-access' => 'api-access.php',

    // API / AJAX endpoints can be routed here too
    'ajax/wizard_create_list' => 'ajax/wizard_create_list.php',
    'ajax/wizard_complete' => 'ajax/wizard_complete.php',

    // Public CMS pages
    'features' => 'features.php',
    'pricing' => 'pricing.php',
    'contact' => 'contact.php',
    'privacy' => 'privacy.php',
    'terms' => 'terms.php',
];

// --- Route Dispatcher ---
if (array_key_exists($route, $routes)) {
    // Before including, let's define a base URL constant for links/assets
    define('BASE_URL', '/public');

    // The actual page files are stored in the src directory to keep them
    // outside of the web root for better security.
    $page_file = __DIR__ . '/../src/pages/' . $routes[$route];

    if (file_exists($page_file)) {
        include $page_file;
    } else {
        http_response_code(404);
        echo "<h1>404 Not Found</h1>";
        echo "<p>Page file missing: " . htmlspecialchars($page_file) . "</p>";
    }
} else {
    http_response_code(404);
    echo "<h1>404 Not Found</h1>";
    echo "<p>No route defined for '<strong>" . htmlspecialchars($route) . "</strong>'.</p>";
}
