<?php
// src/pages/home.php
require_once __DIR__ . '/../lib/functions.php';

$page_title = "Home";

include __DIR__ . '/../includes/header_public.php';
?>

<div class="hero-section">
    <h1><?php echo get_content('hero_title', 'Your All-in-One Marketing Platform'); ?></h1>
    <p><?php echo get_content('hero_subtitle', 'Reach your customers wherever they are.'); ?></p>
    <a href="/register.php" class="cta-button"><?php echo get_content('hero_cta', 'Get Started for Free'); ?></a>
</div>

<div class="container services-summary">
    <h2>Our Services</h2>
    <div class="services-grid">
        <div class="service-item">
            <i class="fas fa-envelope"></i>
            <h3>Bulk Email Marketing</h3>
            <p>Send beautiful and effective email campaigns to your audience.</p>
        </div>
        <div class="service-item">
            <i class="fas fa-sms"></i>
            <h3>Bulk SMS Marketing</h3>
            <p>Engage your customers directly with personalized text messages.</p>
        </div>
        <div class="service-item">
            <i class="fab fa-whatsapp"></i>
            <h3>Bulk WhatsApp Marketing</h3>
            <p>Connect with your users on the world's most popular messaging app.</p>
        </div>
        <div class="service-item">
            <i class="fas fa-check-circle"></i>
            <h3>Email Verification</h3>
            <p>Clean your email lists to improve deliverability and campaign ROI.</p>
        </div>
    </div>
</div>

<div class="container features-highlights">
    <h2>Platform Highlights</h2>
    <div class="features-grid">
        <div class="feature-item">
            <h3><i class="fas fa-robot"></i> AI Content Helper</h3>
            <p>Generate engaging marketing copy for your campaigns in seconds.</p>
        </div>
        <div class="feature-item">
            <h3><i class="fas fa-file-alt"></i> Landing Page Builder</h3>
            <p>Create beautiful, high-converting landing pages with no code.</p>
        </div>
        <div class="feature-item">
            <h3><i class="fas fa-cogs"></i> Marketing Automations</h3>
            <p>Set up autoresponders and welcome series to nurture your leads.</p>
        </div>
    </div>
</div>

<div class="container testimonials-section">
    <h2>What Our Customers Say</h2>
    <div class="testimonial-slider">
        <?php
        // This part will be dynamic, fetched from the database later
        ?>
        <div class="testimonial">
            <p>"This platform has transformed our marketing efforts. The credit system is fair, and the tools are powerful and easy to use."</p>
            <span>- Jane Doe, CEO of ExampleCorp</span>
        </div>
        <div class="testimonial">
            <p>"The email verification service is a lifesaver! Our bounce rates have dropped significantly since we started using it."</p>
            <span>- John Smith, Marketing Manager</span>
        </div>
    </div>
</div>

<div class="container pricing-preview">
    <h2>Simple, Pay-as-you-go Pricing</h2>
    <p>Only pay for what you use. Top up your credits and use them across any of our services.</p>
    <a href="/pricing.php" class="cta-button">View Pricing</a>
</div>

<?php
include __DIR__ . '/../includes/footer_public.php';
?>
