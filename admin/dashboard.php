<?php
$page_title = "Admin Dashboard";
require_once 'auth_admin.php';
require_once 'includes/header_admin.php';

// --- Data Fetching for Stats ---
$stats = [
    'total_revenue' => 0,
    'new_users_24h' => 0,
    'pending_pops' => 0,
    'open_tickets' => 0,
];

$stats['total_revenue'] = $mysqli->query("SELECT SUM(amount_usd) FROM transactions WHERE status = 'completed' AND type = 'purchase'")->fetch_row()[0] ?? 0;
$stats['new_users_24h'] = $mysqli->query("SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 1 DAY")->fetch_row()[0];
$stats['pending_pops'] = $mysqli->query("SELECT COUNT(*) FROM manual_payments WHERE status = 'pending'")->fetch_row()[0];
$stats['open_tickets'] = $mysqli->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'")->fetch_row()[0];

?>

<div class="container-fluid">
    <h1>Dashboard</h1>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Revenue</h3>
            <p>$<?php echo number_format($stats['total_revenue'], 2); ?></p>
        </div>
        <div class="stat-card">
            <h3>New Users (24h)</h3>
            <p><?php echo number_format($stats['new_users_24h']); ?></p>
        </div>
        <div class="stat-card">
            <h3>Pending POPs</h3>
            <p><?php echo number_format($stats['pending_pops']); ?></p>
        </div>
        <div class="stat-card">
            <h3>Open Tickets</h3>
            <p><?php echo number_format($stats['open_tickets']); ?></p>
        </div>
    </div>

    <div class="card mt-4">
        <h2>Quick Links</h2>
        <div class="quick-links">
            <a href="users.php" class="btn btn-primary">Manage Users</a>
            <a href="payments.php" class="btn btn-secondary">Verify Payments</a>
            <a href="support.php" class="btn btn-info">View Tickets</a>
            <a href="settings.php" class="btn btn-dark">Site Settings</a>
        </div>
    </div>

</div>

<?php
require_once 'includes/footer_admin.php';
?>
