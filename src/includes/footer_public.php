<?php
// src/includes/footer_public.php
?>
</main>

<footer class="main-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <h3><?php echo get_setting('site_name', 'Active Email Verifier'); ?></h3>
                <p><?php echo get_content('footer_about_text', 'The complete marketing toolkit for growing businesses. All the features you need, with a simple pay-as-you-go pricing model.'); ?></p>
            </div>
            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="/features">Features</a></li>
                    <li><a href="/pricing">Pricing</a></li>
                    <li><a href="/contact">Contact Us</a></li>
                    <li><a href="/login">Login</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Legal</h4>
                <ul>
                    <li><a href="/privacy">Privacy Policy</a></li>
                    <li><a href="/terms">Terms of Service</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Connect With Us</h4>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo get_setting('site_name', 'Active Email Verifier'); ?>. All Rights Reserved.</p>
        </div>
    </div>
</footer>

<script src="/js/main.js"></script>
</body>
</html>
