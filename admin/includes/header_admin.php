<?php
// admin/includes/header_admin.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | ' : ''; ?>Admin</title>
    <link rel="stylesheet" href="/css/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="logo">ADMIN</a>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                <li class="nav-section-title"><span>Management</span></li>
                <li><a href="users.php"><i class="fas fa-users"></i><span>Users</span></a></li>
                <li><a href="payments.php"><i class="fas fa-receipt"></i><span>Manual Payments</span></a></li>
                <li><a href="transactions.php"><i class="fas fa-exchange-alt"></i><span>Transactions</span></a></li>
                <li><a href="support.php"><i class="fas fa-life-ring"></i><span>Support Tickets</span></a></li>
                <li class="nav-section-title"><span>CMS</span></li>
                <li><a href="cms_homepage.php"><i class="fas fa-file-image"></i><span>Homepage</span></a></li>
                <li><a href="cms_features.php"><i class="fas fa-star"></i><span>Features</span></a></li>
                <li><a href="cms_pricing.php"><i class="fas fa-dollar-sign"></i><span>Credit Packages</span></a></li>
                <li><a href="cms_testimonials.php"><i class="fas fa-comment"></i><span>Testimonials</span></a></li>
                <li><a href="cms_pages.php"><i class="fas fa-file-alt"></i><span>Simple Pages</span></a></li>
                 <li class="nav-section-title"><span>Configuration</span></li>
                <li><a href="settings.php"><i class="fas fa-cogs"></i><span>Site Settings</span></a></li>
            </ul>
        </nav>
    </aside>
    <div class="main-content">
         <header class="topbar">
            <div class="user-menu">
                <span>Welcome, <?php echo htmlspecialchars($admin_user['name']); ?></span>
                <a href="../logout.php" class="btn btn-sm btn-logout">Logout</a>
            </div>
        </header>
        <main class="page-container">
