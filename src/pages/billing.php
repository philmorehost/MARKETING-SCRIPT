<?php
if (!isset($_SESSION['userid'])) {
    header('Location: /public/login');
    exit;
}
$user_id = $_SESSION['user_id'];
// Get team_id from the user's session or DB
$stmt = $mysqli->prepare("SELECT team_id FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$team_id = $stmt->get_result()->fetch_assoc()['team_id'];


$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Base query
$sql = "SELECT created_at, description, amount_credits, status FROM transactions WHERE team_id = ?";
$params = ['i', $team_id];

// Append filters
if (!empty($search)) {
    $sql .= " AND description LIKE ?";
    $params[0] .= 's';
    $params[] = "%{$search}%";
}
if (!empty($date_from)) {
    $sql .= " AND created_at >= ?";
    $params[0] .= 's';
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $sql .= " AND created_at <= ?";
    $params[0] .= 's';
    $params[] = $date_to . ' 23:59:59';
}

$sql .= " ORDER BY created_at DESC";

$trans_result = $mysqli->prepare($sql);
if ($trans_result) {
    $trans_result->bind_param(...$params);
    $trans_result->execute();
    $transactions = $trans_result->get_result();
} else {
    // Handle error
    $transactions = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Billing & Transaction History</title><link rel="stylesheet" href="/public/css/dashboard_style.css"></head>
<body>
    <?php include APP_ROOT . '/public/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <div class="page-header">
                <h1>Billing & Credits</h1>
                <a href="/buy-credits" class="button-primary">Buy Credits</a>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Transaction History</h2>
                </div>

                <form method="get" class="filter-form">
                    <input type="text" name="search" placeholder="Search description..." value="<?php echo htmlspecialchars($search); ?>">
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    <button type="submit">Filter</button>
                </form>

                <div class="table-container">
                    <table>
                        <thead><tr><th>Date</th><th>Description</th><th>Amount (Credits)</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if ($transactions && $transactions->num_rows > 0): ?>
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
    <?php include APP_ROOT . '/public/includes/footer.php'; ?>
</body>
</html>
