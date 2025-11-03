<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /public/login');
    exit;
}

// Fetch all transactions
$transactions = $mysqli->query("SELECT t.*, u.email FROM transactions t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Transactions</title>
    <link rel="stylesheet" href="/public/css/admin_style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="admin-container">
        <aside class="sidebar">
            <?php include 'includes/sidebar.php'; ?>
        </aside>
        <main class="main-content">
            <h1>Transaction Ledger</h1>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Amount (USD)</th>
                            <th>Amount (Credits)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($tx = $transactions->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $tx['created_at']; ?></td>
                            <td><?php echo htmlspecialchars($tx['email']); ?></td>
                            <td><?php echo htmlspecialchars($tx['type']); ?></td>
                            <td><?php echo htmlspecialchars($tx['description']); ?></td>
                            <td>$<?php echo number_format($tx['amount_usd'], 2); ?></td>
                            <td><?php echo number_format($tx['amount_credits'], 4); ?></td>
                            <td><?php echo htmlspecialchars($tx['status']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
