<?php
session_start();
require_once '../src/config/db.php';
require_once '../src/lib/functions.php';

$reference = $_GET['reference'] ?? null;
if (!$reference) {
    die('No reference supplied');
}

$paystack_secret_key = get_setting('paystack_secret_key', $mysqli);
if (empty($paystack_secret_key)) {
    die("Payment gateway is not configured.");
}

// Verify the transaction with Paystack
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/verify/{$reference}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $paystack_secret_key
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if ($result['status'] === true && $result['data']['status'] === 'success') {
    $metadata = $result['data']['metadata'];
    $user_id = $metadata['user_id'];
    $package_id = $metadata['package_id'];
    $amount_paid = $result['data']['amount'] / 100; // Convert from kobo

    // Fetch package details to get credit amount
    $pkg_stmt = $mysqli->prepare("SELECT credits, price FROM credit_packages WHERE id = ?");
    $pkg_stmt->bind_param('i', $package_id);
    $pkg_stmt->execute();
    $package = $pkg_stmt->get_result()->fetch_assoc();

    if ($package && (float)$package['price'] === (float)$amount_paid) {
        $mysqli->begin_transaction();
        try {
            // Update user's credit balance
            $update_stmt = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance + ? WHERE id = ?");
            $update_stmt->bind_param('di', $package['credits'], $user_id);
            $update_stmt->execute();

            // Insert into transactions table
            $trans_stmt = $mysqli->prepare("INSERT INTO transactions (user_id, type, gateway, gateway_tx_id, description, amount_usd, amount_credits, status) VALUES (?, 'purchase', 'paystack', ?, ?, ?, ?, 'completed')");
            $description = "Purchase of {$package['credits']} credits";
            $trans_stmt->bind_param('issds', $user_id, $reference, $description, $amount_paid, $package['credits']);
            $trans_stmt->execute();

            $mysqli->commit();

            // Redirect to a success page
            $_SESSION['flash_message'] = "Payment successful! {$package['credits']} credits have been added to your account.";
            header('Location: /dashboard');
            exit;

        } catch (mysqli_sql_exception $exception) {
            $mysqli->rollback();
            die('Transaction failed: ' . $exception->getMessage());
        }
    } else {
        die('Invalid transaction or amount mismatch.');
    }
} else {
    die('Transaction verification failed.');
}
