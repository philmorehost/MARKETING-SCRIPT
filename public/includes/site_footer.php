<footer class="site-footer">
    <div class="footer-content">
        <div class="about-section">
            <h3><?php echo htmlspecialchars(get_setting('site_name', $mysqli)); ?></h3>
            <p>Your go-to platform for marketing and email verification.</p>
        </div>
        <div class="links-section">
            <h4>Quick Links</h4>
            <ul>
                <li><a href="features.php">Features</a></li>
                <li><a href="pricing.php">Pricing</a></li>
                <li><a href="contact.php">Contact Us</a></li>
            </ul>
        </div>
        <div class="legal-section">
            <h4>Legal</h4>
            <ul>
                <li><a href="privacy.php">Privacy Policy</a></li>
                <li><a href="terms.php">Terms of Service</a></li>
            </ul>
        </div>
    </div>
    <div class="copyright">
        &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(get_setting('site_name', $mysqli)); ?>. All rights reserved.
    </div>
</footer>
