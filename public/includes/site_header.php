<header class="site-header">
    <div class="logo">
        <a href="index.php"><?php echo htmlspecialchars(get_setting('site_name', $mysqli, 'SaaS Platform')); ?></a>
    </div>
    <nav class="main-nav">
        <ul>
            <li><a href="features.php">Features</a></li>
            <li><a href="pricing.php">Pricing</a></li>
            <li><a href="contact.php">Contact</a></li>
        </ul>
    </nav>
    <div class="user-actions">
        <a href="login.php" class="button-secondary">Login</a>
        <a href="register.php" class="button-primary">Sign Up</a>
    </div>
</header>
