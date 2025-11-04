<?php
// Security check: only admins can access
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login');
    exit;
}

// Note: $mysqli is available from the script that includes this, e.g. a front controller for admin
if (!isset($mysqli)) {
}


// --- Fetch Dashboard Stats ---

// Total Revenue (Monthly)
$rev_result = $mysqli->query("SELECT SUM(amount_usd) as total FROM transactions WHERE type = 'purchase' AND status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
$total_revenue = $rev_result->fetch_assoc()['total'] ?? 0;

// New Users (24h)
$users_result = $mysqli->query("SELECT COUNT(id) as total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$new_users_24h = $users_result->fetch_assoc()['total'] ?? 0;

// Pending POP Verifications
$pops_result = $mysqli->query("SELECT COUNT(id) as total FROM manual_payments WHERE status = 'pending'");
$pending_pops = $pops_result->fetch_assoc()['total'] ?? 0;

// Open Support Tickets
$tickets_result = $mysqli->query("SELECT COUNT(id) as total FROM support_tickets WHERE status = 'open'");
$open_tickets = $tickets_result->fetch_assoc()['total'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="/css/admin_style.css">
</head>
<body>
    <?php include APP_ROOT . '/admin/includes/header.php'; ?>

    <div class="admin-container">
        <aside class="sidebar">
            <?php include APP_ROOT . '/admin/includes/sidebar.php'; ?>
        </aside>

        <main class="main-content">
            <h1>Dashboard</h1>

            <div class="stats-grid">
                <div class="card">
                    <h3>Total Revenue (This Month)</h3>
                    <p>$<?php echo number_format($total_revenue, 2); ?></p>
                </div>
                <div class="card">
                    <h3>New Users (Last 24h)</h3>
                    <p><?php echo $new_users_24h; ?></p>
                </div>
                <div class="card">
                    <h3>Pending POP Verifications</h3>
                    <p><?php echo $pending_pops; ?></p>
                </div>
                <div class="card">
                    <h3>Open Support Tickets</h3>
                    <p><?php echo $open_tickets; ?></p>
                </div>
            </div>
        </main>
    </div>

    <?php include APP_ROOT . '/admin/includes/footer.php'; ?>
</body>
</html>
