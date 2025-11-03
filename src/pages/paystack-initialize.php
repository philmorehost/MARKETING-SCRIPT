<?php
require_once '../config/db.php';
require_once '../src/lib/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$package_id = (int)($_POST['package_id'] ?? 0);

if ($package_id === 0) {
    header('Location: buy-credits.php');
    exit;
}


// Get package and user details
$pkg_stmt = $mysqli->prepare("SELECT name, price FROM credit_packages WHERE id = ?");
$pkg_stmt->bind_param('i', $package_id);
$pkg_stmt->execute();
$package = $pkg_stmt->get_result()->fetch_assoc();

$user_stmt = $mysqli->prepare("SELECT email FROM users WHERE id = ?");
$user_stmt->bind_param('i', $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

if (!$package || !$user) {
    die("Invalid package or user.");
}

// --- Paystack API ---
$paystack_secret_key = get_setting('paystack_secret_key', $mysqli);
if (empty($paystack_secret_key)) {
    die("Payment gateway is not configured.");
}

$post_data = [
    'email' => $user['email'],
    'amount' => $package['price'] * 100, // Paystack expects amount in kobo
    'callback_url' => "http://{$_SERVER['HTTP_HOST']}/public/paystack-verify.php",
    'metadata' => [
        'user_id' => $user_id,
        'package_id' => $package_id
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.paystack.co/transaction/initialize');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $paystack_secret_key,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if ($result['status'] === true) {
    // Redirect to Paystack's payment page
    header('Location: ' . $result['data']['authorization_url']);
    exit;
} else {
    // Handle error
    die('Error initializing transaction: ' . $result['message']);
}
