<?php
if (!isset($_SESSION['userid'])) {
    header('Location: /login');
    exit;
}
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];

// Fetch transaction history for the team
$trans_result = $mysqli->prepare("SELECT created_at, description, amount_credits, status FROM transactions WHERE team_id = ? ORDER BY created_at DESC");
$trans_result->bind_param('i', $team_id);
$trans_result->execute();
$transactions = $trans_result->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Billing & Transaction History</title><link rel="stylesheet" href="/public/css/dashboard_style.css"></head>
<body>
    <?php include APP_ROOT . '/public_html/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public_html/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Billing & Credits</h1>
            <div class="card">
                <h2>Transaction History</h2>
                <div class="table-container">
                    <table>
                        <thead><tr><th>Date</th><th>Description</th><th>Amount (Credits)</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if ($transactions->num_rows > 0): ?>
                            <?php while($tx = $transactions->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $tx['created_at']; ?></td>
                                <td><?php echo htmlspecialchars($tx['description']); ?></td>
                                <td><?php echo number_format($tx['amount_credits'], 4); ?></td>
                                <td><?php echo htmlspecialchars($tx['status']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4">No transactions yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    <?php include APP_ROOT . '/public_html/includes/footer.php'; ?>
</body>
</html>
