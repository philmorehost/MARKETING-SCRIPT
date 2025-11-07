<?php
// src/includes/header_public.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | ' : ''; ?><?php echo get_setting('site_name', 'Active Email Verifier'); ?></title>
    <link rel="stylesheet" href="/css/public_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>

<header class="main-header">
    <div class="container">
        <a href="/" class="logo"><?php echo get_setting('site_name', 'Active Email Verifier'); ?></a>
        <nav class="main-nav">
            <ul>
                <li><a href="/features">Features</a></li>
                <li><a href="/pricing">Pricing</a></li>
                <li><a href="/contact">Contact Us</a></li>
            </ul>
        </nav>
        <div class="header-actions">
            <a href="/login" class="btn btn-secondary">Login</a>
            <a href="/register" class="btn btn-primary">Sign Up</a>
        </div>
    </div>
</header>

<main>
