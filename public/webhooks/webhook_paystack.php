<?php
// --- webhook_paystack.php ---
require_once '../../config/db.php';
require_once '../../src/lib/functions.php';

// --- Security ---
// 1. Verify the request is from Paystack
$paystack_secret_key = get_setting('paystack_secret_key', new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME));
if (!isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']) || (hash_hmac('sha512', file_get_contents('php://input'), $paystack_secret_key) !== $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'])) {
    http_response_code(401);
    die('Unauthorized');
}

// --- Process Event ---
$payload = file_get_contents('php://input');
$event_data = json_decode($payload, true);

if ($event_data['event'] === 'charge.success') {
    $data = $event_data['data'];
    $reference = $data['reference'];

    // Check if we've already processed this transaction
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $tx_check = $mysqli->query("SELECT id FROM transactions WHERE gateway_tx_id = '{$reference}'");

    if ($tx_check->num_rows === 0) {
        // --- Credit User ---
        $metadata = $data['metadata'];
        $user_id = $metadata['user_id'];
        $package_id = $metadata['package_id'];
        $amount_usd = $data['amount'] / 100;

        $pkg_stmt = $mysqli->prepare("SELECT name, credits FROM credit_packages WHERE id = ?");
        $pkg_stmt->bind_param('i', $package_id);
        $pkg_stmt->execute();
        $package = $pkg_stmt->get_result()->fetch_assoc();

        if ($package) {
            $credits_to_add = $package['credits'];

            $mysqli->begin_transaction();
            try {
                $mysqli->query("UPDATE users SET credit_balance = credit_balance + $credits_to_add WHERE id = $user_id");

                $desc = "Credits purchased via Paystack: {$package['name']}";
                $insert_tx = $mysqli->prepare("INSERT INTO transactions (user_id, type, gateway, gateway_tx_id, description, amount_usd, amount_credits, status) VALUES (?, 'purchase', 'paystack', ?, ?, ?, ?, 'completed')");
                $insert_tx->bind_param('issdd', $user_id, $reference, $desc, $amount_usd, $credits_to_add);
                $insert_tx->execute();

                $mysqli->commit();
            } catch (Exception $e) {
                $mysqli->rollback();
                // In a real app, log this error
            }
        }
    }
}

http_response_code(200);
