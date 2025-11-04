<?php
// --- public/includes/site_header.php ---
?>
<nav class="main-nav">
    <div class="container">
        <a href="/home" class="logo"><?php echo htmlspecialchars(get_setting('site_name', $mysqli, 'SaaS Platform')); ?></a>
        <ul>
            <li><a href="/features">Features</a></li>
            <li><a href="/pricing">Pricing</a></li>
            <li><a href="/contact">Contact</a></li>
        </ul>
        <div class="nav-buttons">
            <a href="/login" class="button-secondary">Login</a>
            <a href="/register" class="button-primary">Sign Up</a>
        </div>
    </div>
</nav>
