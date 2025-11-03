<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

require_once '../config/db.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$message = '';

// Handle Approval/Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id'])) {
    $payment_id = (int)$_POST['payment_id'];
    $action = $_POST['action'];

    // Fetch payment details
    $stmt = $mysqli->prepare("SELECT user_id, credit_package_id, amount FROM manual_payments WHERE id = ? AND status = 'pending'");
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();

    if ($payment) {
        $user_id = $payment['user_id'];
        $amount_usd = $payment['amount'];
        $package_id = $payment['credit_package_id'];

        if ($action === 'approve' && $package_id) {
            // Find the corresponding credit package to get the credits
            $stmt = $mysqli->prepare("SELECT name, credits FROM credit_packages WHERE id = ?");
            $stmt->bind_param('i', $package_id);
            $stmt->execute();
            $package = $stmt->get_result()->fetch_assoc();

            if ($package) {
                $credits_to_add = $package['credits'];
                $package_name = $package['name'];

                // Use a transaction
                $mysqli->begin_transaction();
                try {
                    // 1. Add credits to user
                    $update_user = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance + ? WHERE id = ?");
                    $update_user->bind_param('di', $credits_to_add, $user_id);
                    $update_user->execute();

                    // 2. Create a transaction record
                    $desc = "Credits purchased via Bank Transfer: {$package_name}";
                    $insert_tx = $mysqli->prepare("INSERT INTO transactions (user_id, type, description, amount_usd, amount_credits, status) VALUES (?, 'purchase', ?, ?, ?, 'completed')");
                    $insert_tx->bind_param('isdd', $user_id, $desc, $amount_usd, $credits_to_add);
                    $insert_tx->execute();

                    // 3. Update payment status
                    $update_payment = $mysqli->prepare("UPDATE manual_payments SET status = 'approved' WHERE id = ?");
                    $update_payment->bind_param('i', $payment_id);
                    $update_payment->execute();

                    $mysqli->commit();
                    $message = "Payment approved and credits added.";

                } catch (Exception $e) {
                    $mysqli->rollback();
                    $message = "An error occurred. Transaction rolled back.";
                }
            } else {
                $message = "Error: Credit package '{$package_name}' not found in database.";
            }

        } elseif ($action === 'reject') {
            $stmt = $mysqli->prepare("UPDATE manual_payments SET status = 'rejected' WHERE id = ?");
            $stmt->bind_param('i', $payment_id);
            $stmt->execute();
            $message = "Payment rejected.";
        }
    }
}


// Fetch pending payments
$pending_payments_result = $mysqli->query("SELECT mp.*, u.email FROM manual_payments mp JOIN users u ON mp.user_id = u.id WHERE mp.status = 'pending' ORDER BY mp.created_at ASC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manual Payment Verification</title>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="admin-container">
        <aside class="sidebar">
            <?php include 'includes/sidebar.php'; ?>
        </aside>
        <main class="main-content">
            <h1>Manual Payment Verification</h1>
            <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Package/Amount</th>
                        <th>Proof</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($payment = $pending_payments_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($payment['email']); ?></td>
                        <td><?php echo htmlspecialchars($payment['credit_package_name']); ?> ($<?php echo $payment['amount']; ?>)</td>
                        <td><a href="../public/<?php echo htmlspecialchars($payment['proof_path']); ?>" target="_blank">View Proof</a></td>
                        <td><?php echo $payment['created_at']; ?></td>
                        <td>
                            <form action="payments.php" method="post" style="display:inline;">
                                <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                <button type="submit" name="action" value="approve">Approve</button>
                                <button type="submit" name="action" value="reject">Reject</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </main>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
