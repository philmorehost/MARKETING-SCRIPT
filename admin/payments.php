<?php
$page_title = "Manual Payment Verification";
require_once 'auth_admin.php';

// --- Action Logic ---
$action = $_GET['action'] ?? null;
$payment_id = $_GET['id'] ?? null;
if ($action && $payment_id) {
    $mysqli->begin_transaction();
    try {
        // Get payment details
        $payment_stmt = $mysqli->prepare("SELECT * FROM manual_payments WHERE id = ? AND status = 'pending'");
        $payment_stmt->bind_param("i", $payment_id);
        $payment_stmt->execute();
        $payment = $payment_stmt->get_result()->fetch_assoc();

        if ($payment) {
            if ($action === 'approve') {
                // 1. Get package credits
                $pkg_stmt = $mysqli->prepare("SELECT credits, name FROM credit_packages WHERE id = ?");
                $pkg_stmt->bind_param("i", $payment['credit_package_id']);
                $pkg_stmt->execute();
                $package = $pkg_stmt->get_result()->fetch_assoc();
                $credits_to_add = $package['credits'];

                // 2. Add credits to user
                $update_user_stmt = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance + ? WHERE id = ?");
                $update_user_stmt->bind_param("di", $credits_to_add, $payment['user_id']);
                $update_user_stmt->execute();

                // 3. Create transaction record
                $trans_stmt = $mysqli->prepare("INSERT INTO transactions (user_id, type, gateway, description, amount_usd, amount_credits, status) VALUES (?, 'purchase', 'manual', ?, ?, ?, 'completed')");
                $description = "Manual purchase of " . $package['name'];
                $trans_stmt->bind_param("isdd", $payment['user_id'], $description, $payment['amount'], $credits_to_add);
                $trans_stmt->execute();

                // 4. Update payment status
                $update_payment_stmt = $mysqli->prepare("UPDATE manual_payments SET status = 'approved' WHERE id = ?");
                $update_payment_stmt->bind_param("i", $payment_id);
                $update_payment_stmt->execute();

                // 5. Create notification
                create_notification($payment['user_id'], "Your manual payment for " . $package['name'] . " was approved. {$credits_to_add} credits have been added.", "/billing");

            } elseif ($action === 'reject') {
                // Just update the status
                $update_payment_stmt = $mysqli->prepare("UPDATE manual_payments SET status = 'rejected' WHERE id = ?");
                $update_payment_stmt->bind_param("i", $payment_id);
                $update_payment_stmt->execute();
            }
            $mysqli->commit();
        }
    } catch (Exception $e) {
        $mysqli->rollback();
        // Handle error
    }
    header('Location: payments.php');
    exit;
}


// --- Fetch Pending Payments ---
$query = "SELECT mp.*, u.name as user_name, u.email as user_email
          FROM manual_payments mp
          JOIN users u ON mp.user_id = u.id
          WHERE mp.status = 'pending'
          ORDER BY mp.created_at ASC";
$pending_payments = $mysqli->query($query)->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header_admin.php';
?>
<div class="container-fluid">
    <h1>Manual Payment Verification</h1>
    <p>Review and approve payments made via manual bank transfer.</p>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Package Name</th>
                    <th>Amount</th>
                    <th>Proof</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pending_payments)): ?>
                    <tr><td colspan="6">No pending payments found.</td></tr>
                <?php else: ?>
                    <?php foreach ($pending_payments as $payment): ?>
                    <tr>
                        <td><?php echo $payment['created_at']; ?></td>
                        <td>
                            <?php echo htmlspecialchars($payment['user_name']); ?><br>
                            <small><?php echo htmlspecialchars($payment['user_email']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($payment['credit_package_name']); ?></td>
                        <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                        <td>
                            <a href="<?php echo $payment['proof_path']; ?>" target="_blank" class="btn btn-sm btn-info">View Proof</a>
                        </td>
                        <td>
                             <a href="payments.php?action=approve&id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to approve this payment and add credits?')">Approve</a>
                             <a href="payments.php?action=reject&id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to reject this payment?')">Reject</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
require_once 'includes/footer_admin.php';
?>
