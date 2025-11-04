<?php
// This webhook provides a more reliable way to capture successful payments
// in case the user closes the browser before the callback URL is hit.

require_once '../../src/config/db.php';
require_once '../../src/lib/functions.php';

// Retrieve the request's body and parse it as JSON
$input = @file_get_contents("php://input");
$event = json_decode($input);

if (http_response_code(200) && isset($event->event) && $event->event === 'charge.success') {
    $paystack_secret_key = get_setting('paystack_secret_key', $mysqli);

    // Verify the signature
    $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
    if ($signature !== hash_hmac('sha512', $input, $paystack_secret_key)) {
        http_response_code(401);
        exit();
    }

    $reference = $event->data->reference;

    // Check if transaction has already been processed to avoid duplicates
    $check_stmt = $mysqli->prepare("SELECT id FROM transactions WHERE gateway_tx_id = ?");
    $check_stmt->bind_param('s', $reference);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        exit(); // Already processed
    }

    $metadata = $event->data->metadata;
    $user_id = $metadata->user_id;
    $package_id = $metadata->package_id;
    $amount_paid = $event->data->amount / 100;

    $pkg_stmt = $mysqli->prepare("SELECT credits, price FROM credit_packages WHERE id = ?");
    $pkg_stmt->bind_param('i', $package_id);
    $pkg_stmt->execute();
    $package = $pkg_stmt->get_result()->fetch_assoc();

    if ($package && (float)$package['price'] === (float)$amount_paid) {
         $mysqli->begin_transaction();
        try {
            $update_stmt = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance + ? WHERE id = ?");
            $update_stmt->bind_param('di', $package['credits'], $user_id);
            $update_stmt->execute();

            $trans_stmt = $mysqli->prepare("INSERT INTO transactions (user_id, type, gateway, gateway_tx_id, description, amount_usd, amount_credits, status) VALUES (?, 'purchase', 'paystack', ?, ?, ?, ?, 'completed')");
            $description = "Purchase of {$package['credits']} credits (via webhook)";
            $trans_stmt->bind_param('issds', $user_id, $reference, $description, $amount_paid, $package['credits']);
            $trans_stmt->execute();

            $mysqli->commit();
        } catch (mysqli_sql_exception $exception) {
            $mysqli->rollback();
            // Log the error
        }
    }
}
http_response_code(200);
