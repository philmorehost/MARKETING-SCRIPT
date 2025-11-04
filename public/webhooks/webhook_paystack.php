<?php
// --- webhook_paystack.php ---
require_once '../../config/db.php';
require_once '../../src/lib/functions.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) { http_response_code(500); exit; }

// --- Security ---
// 1. Verify the request is from Paystack by checking the signature
$paystack_secret_key = get_setting('paystack_secret_key', $mysqli);
$payload = file_get_contents('php://input'); // Read the input ONCE

if (!isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']) || (hash_hmac('sha512', $payload, $paystack_secret_key) !== $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'])) {
    http_response_code(401);
    die('Unauthorized');
}

// --- Process Event ---
$event_data = json_decode($payload, true);

if (isset($event_data['event']) && $event_data['event'] === 'charge.success') {
    $data = $event_data['data'];
    $reference = $data['reference'];

    // Check if we've already processed this transaction to prevent duplicates
    $stmt_check = $mysqli->prepare("SELECT id FROM transactions WHERE gateway_tx_id = ?");
    $stmt_check->bind_param('s', $reference);
    $stmt_check->execute();

    if ($stmt_check->get_result()->num_rows === 0) {
        $metadata = $data['metadata'] ?? [];
        $user_id = $metadata['user_id'] ?? null;
        $package_id = $metadata['package_id'] ?? null;
        $amount_usd = $data['amount'] / 100;

        if ($user_id && $package_id) {
            $pkg_stmt = $mysqli->prepare("SELECT name, credits FROM credit_packages WHERE id = ?");
            $pkg_stmt->bind_param('i', $package_id);
            $pkg_stmt->execute();
            $package = $pkg_stmt->get_result()->fetch_assoc();

            if ($package) {
                $credits_to_add = $package['credits'];
                $mysqli->begin_transaction();
                try {
                    $stmt_credit = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance + ? WHERE id = ?");
                    $stmt_credit->bind_param('di', $credits_to_add, $user_id);
                    $stmt_credit->execute();

                    $desc = "Credits purchased via Paystack: {$package['name']}";
                    $stmt_insert_tx = $mysqli->prepare("INSERT INTO transactions (user_id, type, gateway, gateway_tx_id, description, amount_usd, amount_credits, status) VALUES (?, 'purchase', 'paystack', ?, ?, ?, ?, 'completed')");
                    $stmt_insert_tx->bind_param('issdd', $user_id, $reference, $desc, $amount_usd, $credits_to_add);
                    $stmt_insert_tx->execute();

                    // --- Create a notification ---
                    $notif_message = "Your purchase of " . number_format($credits_to_add) . " credits was successful.";
                    $stmt_notif = $mysqli->prepare("INSERT INTO notifications (user_id, team_id, message, link) VALUES (?, (SELECT team_id FROM users WHERE id = ? LIMIT 1), ?, '/billing')");
                    $stmt_notif->bind_param('iis', $user_id, $user_id, $notif_message);
                    $stmt_notif->execute();
                    // --- End notification ---

                    $mysqli->commit();
                } catch (Exception $e) {
                    $mysqli->rollback();
                    // In a real app, log this error to a file
                }
            }
        }
    }
}

http_response_code(200);
echo "OK";
