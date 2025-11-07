<?php
// src/includes/header_app.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | ' : ''; ?><?php echo get_setting('site_name', 'Active Email Verifier'); ?></title>
    <link rel="stylesheet" href="/css/app_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>

<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="/dashboard" class="logo"><?php echo get_setting('site_name', 'A'); ?></a>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="/dashboard"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                <li><a href="/buy-credits"><i class="fas fa-wallet"></i><span>Buy Credits</span></a></li>
                <li><a href="/contact-lists"><i class="fas fa-address-book"></i><span>Contacts</span></a></li>
                <li><a href="/email-campaigns"><i class="fas fa-envelope"></i><span>Email</span></a></li>
                <li><a href="/sms-campaigns"><i class="fas fa-sms"></i><span>SMS</span></a></li>
                <li><a href="/whatsapp-campaigns"><i class="fas fa-comment-dots"></i><span>WhatsApp</span></a></li>
                <li><a href="/landing-pages"><i class="fas fa-file-alt"></i><span>Landing Pages</span></a></li>
                 <li><a href="/automations"><i class="fas fa-cogs"></i><span>Automations</span></a></li>
                <li><a href="/social-posts"><i class="fas fa-share-alt"></i><span>Social</span></a></li>
                <li><a href="/qr-codes"><i class="fas fa-qrcode"></i><span>QR Codes</span></a></li>
                <li><a href="/support"><i class="fas fa-life-ring"></i><span>Support</span></a></li>
            </ul>
        </nav>
    </aside>
    <div class="main-content">
        <header class="topbar">
            <div class="credits-display">
                <i class="fas fa-coins"></i>
                <span><?php echo number_format($user['credit_balance'], 2); ?></span> Credits
            </div>
            <div class="user-menu">
                <img src="<?php echo htmlspecialchars($user['avatar_url'] ?? '/img/default-avatar.png'); ?>" alt="User Avatar" class="avatar">
                <div class="dropdown">
                    <a href="/settings">Settings</a>
                    <a href="/billing">Billing</a>
                    <a href="/team">Team</a>
                    <a href="/logout.php">Logout</a>
                </div>
            </div>
        </header>
        <main class="page-container">
