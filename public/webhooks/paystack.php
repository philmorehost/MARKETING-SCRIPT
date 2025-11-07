<?php
// public/webhooks/paystack.php
// This script listens for webhook notifications from Paystack

// Set up required files
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/src/lib/functions.php';

// --- Security Check ---
// Only POST requests are allowed.
if (strtoupper($_SERVER['REQUEST_METHOD']) != 'POST') {
    http_response_code(405);
    exit();
}

// Retrieve the request's body and parse it as JSON
$input = @file_get_contents("php://input");
$event = json_decode($input);

// Verify the event is from Paystack
$paystack_sk = get_setting('paystack_secret_key');
if (!$paystack_sk) {
    http_response_code(500);
    exit('Paystack secret key not configured.');
}

if (!isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']) || ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] !== hash_hmac('sha512', $input, $paystack_sk))) {
    http_response_code(401);
    exit('Invalid signature.');
}


// --- Process Event ---
if (isset($event->event) && $event->event === 'charge.success') {
    // --- Payment was successful ---
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_error) {
        http_response_code(500);
        exit('Database connection failed.');
    }

    // Extract data from the event
    $reference = $event->data->reference;
    $amount_kobo = $event->data->amount; // Amount in kobo/cents
    $amount_usd = $amount_kobo / 100; // Convert to USD
    $user_id = $event->data->metadata->user_id ?? null;
    $package_id = $event->data->metadata->package_id ?? null;

    if (!$user_id || !$package_id) {
         http_response_code(400);
         exit('Missing metadata: user_id or package_id.');
    }

    // --- Check if this transaction has already been processed ---
    $check_stmt = $mysqli->prepare("SELECT id FROM transactions WHERE gateway_tx_id = ?");
    $check_stmt->bind_param("s", $reference);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        http_response_code(200); // Acknowledge receipt, but do nothing
        exit('Transaction already processed.');
    }


    // --- Get Credit Package Details ---
    $pkg_stmt = $mysqli->prepare("SELECT credits, name FROM credit_packages WHERE id = ?");
    $pkg_stmt->bind_param("i", $package_id);
    $pkg_stmt->execute();
    $pkg_result = $pkg_stmt->get_result();
    if ($pkg_result->num_rows === 0) {
        http_response_code(400);
        exit('Invalid package_id.');
    }
    $package = $pkg_result->fetch_assoc();
    $credits_to_add = $package['credits'];
    $package_name = $package['name'];

    // --- Update User's Credit Balance ---
    $update_stmt = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance + ? WHERE id = ?");
    $update_stmt->bind_param("di", $credits_to_add, $user_id);

    if ($update_stmt->execute()) {
        // --- Create a Transaction Record ---
        $trans_stmt = $mysqli->prepare("INSERT INTO transactions (user_id, type, gateway, gateway_tx_id, description, amount_usd, amount_credits, status) VALUES (?, 'purchase', 'paystack', ?, ?, ?, ?, 'completed')");
        $description = "Purchase of " . $package_name;
        $trans_stmt->bind_param("issd", $user_id, $reference, $description, $amount_usd, $credits_to_add);
        $trans_stmt->execute();

        // --- Create a Notification for the User ---
        create_notification($user_id, "Your account has been credited with {$credits_to_add} credits.", "/billing");

        http_response_code(200); // Success
        echo "Webhook processed successfully.";

    } else {
        http_response_code(500);
        exit('Failed to update user balance.');
    }

    $mysqli->close();

} else {
    // Event is not 'charge.success', so we ignore it but acknowledge receipt.
    http_response_code(200);
}

exit();
