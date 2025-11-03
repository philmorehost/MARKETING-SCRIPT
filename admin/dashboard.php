<?php

// Security check: only admins can access
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

require_once '../config/db.php';
// We'll fetch stats here later
$total_revenue = 0;
$new_users_24h = 0;
$pending_pops = 0;
$open_tickets = 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="/public/css/admin_style.css"> <!-- We will create this -->
</head>
<body>
    <?php include APP_ROOT . '/admin/includes/header.php'; ?>

    <div class="admin-container">
        <aside class="sidebar">
            <?php include APP_ROOT . '/admin/includes/sidebar.php'; ?>
        </aside>

        <main class="main-content">
            <h1>Dashboard</h1>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="card">
                    <h3>Total Revenue (Monthly)</h3>
                    <p>$<?php echo number_format($total_revenue, 2); ?></p>
                </div>
                <div class="card">
                    <h3>New Users (24h)</h3>
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

            <!-- Other dashboard content will go here -->
        </main>
    </div>

    <?php include APP_ROOT . '/admin/includes/footer.php'; ?>
</body>
</html>
