<?php
// --- public/includes/site_footer.php ---
?>
<footer class="main-footer">
    <div class="container">
        <div class="footer-about">
            <h3><?php echo htmlspecialchars(get_setting('site_name', $mysqli, 'SaaS Platform')); ?></h3>
            <p>Your one-stop solution for email, SMS, and WhatsApp marketing, built for simplicity and power.</p>
        </div>
        <div class="footer-links">
            <h4>Quick Links</h4>
            <ul>
                <li><a href="/public/features">Features</a></li>
                <li><a href="/public/pricing">Pricing</a></li>
                <li><a href="/public/contact">Contact Us</a></li>
                <li><a href="/public/privacy">Privacy Policy</a></li>
                <li><a href="/public/terms">Terms of Service</a></li>
            </ul>
        </div>
        <div class="footer-social">
            <h4>Connect With Us</h4>
            <!-- Add social links from settings later -->
            <a href="#">Facebook</a>
            <a href="#">Twitter</a>
            <a href="#">LinkedIn</a>
        </div>
    </div>
    <div class="container footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(get_setting('site_name', $mysqli, 'SaaS Platform')); ?>. All Rights Reserved.</p>
    </div>
</footer>
