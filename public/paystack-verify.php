<?php
session_start();
require_once '../config/db.php';
require_once '../src/lib/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$reference = $_GET['reference'] ?? '';
if (empty($reference)) {
    die('No reference supplied.');
}

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$paystack_secret_key = get_setting('paystack_secret_key', $mysqli);

// Verify the transaction
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.paystack.co/transaction/verify/' . rawurlencode($reference));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $paystack_secret_key]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if ($result['data']['status'] === 'success') {
    // --- Payment is successful, credit user ---
    $metadata = $result['data']['metadata'];
    $user_id = $metadata['user_id'];
    $package_id = $metadata['package_id'];
    $amount_usd = $result['data']['amount'] / 100;

    // Get package credits
    $pkg_stmt = $mysqli->prepare("SELECT name, credits FROM credit_packages WHERE id = ?");
    $pkg_stmt->bind_param('i', $package_id);
    $pkg_stmt->execute();
    $package = $pkg_stmt->get_result()->fetch_assoc();

    if ($package) {
        $credits_to_add = $package['credits'];

        // Use a transaction to ensure atomicity
        $mysqli->begin_transaction();
        try {
            // Check if transaction has already been processed by webhook
            $tx_check = $mysqli->query("SELECT id FROM transactions WHERE gateway_tx_id = '{$reference}'");
            if ($tx_check->num_rows === 0) {
                // 1. Add credits to user
                $mysqli->query("UPDATE users SET credit_balance = credit_balance + $credits_to_add WHERE id = $user_id");

                // 2. Create a transaction record
                $desc = "Credits purchased via Paystack: {$package['name']}";
                $insert_tx = $mysqli->prepare("INSERT INTO transactions (user_id, type, gateway, gateway_tx_id, description, amount_usd, amount_credits, status) VALUES (?, 'purchase', 'paystack', ?, ?, ?, ?, 'completed')");
                $insert_tx->bind_param('issdd', $user_id, $reference, $desc, $amount_usd, $credits_to_add);
                $insert_tx->execute();

                $mysqli->commit();
                $message = "Payment successful! Your credits have been added.";
            } else {
                $message = "Your account has already been credited for this transaction.";
            }

        } catch (Exception $e) {
            $mysqli->rollback();
            $message = "An error occurred. Please contact support.";
        }

    } else {
        $message = "Error: Credit package not found.";
    }

} else {
    $message = "Payment verification failed.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head><title>Payment Status</title></head>
<body>
    <h1>Payment Status</h1>
    <p><?php echo $message; ?></p>
    <a href="dashboard.php">Go to Dashboard</a>
</body>
</html>
