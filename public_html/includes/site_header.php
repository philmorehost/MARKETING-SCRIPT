<?php
// --- public/includes/site_header.php ---
?>
<nav class="main-nav">
    <div class="container">
        <a href="/public/home" class="logo"><?php echo htmlspecialchars(get_setting('site_name', $mysqli, 'SaaS Platform')); ?></a>
        <ul>
            <li><a href="/public/features">Features</a></li>
            <li><a href="/public/pricing">Pricing</a></li>
            <li><a href="/public/contact">Contact</a></li>
        </ul>
        <div class="nav-buttons">
            <a href="/public/login" class="button-secondary">Login</a>
            <a href="/public/register" class="button-primary">Sign Up</a>
        </div>
    </div>
</nav>
