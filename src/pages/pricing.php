<?php
// src/pages/pricing.php
require_once __DIR__ . '/../lib/functions.php';

$page_title = "Pricing";

include __DIR__ . '/../includes/header_public.php';

// Fetch credit packages from the database
$packages_query = $mysqli->query("SELECT * FROM credit_packages ORDER BY price ASC");
$packages = $packages_query->fetch_all(MYSQLI_ASSOC);

// Fetch service costs from settings
$costs = [
    'Email Verification' => get_setting('price_per_verification', 1),
    'Email Send' => get_setting('price_per_email_send', 1),
    'SMS Page (160 chars)' => get_setting('price_per_sms_page', 5),
    'WhatsApp Message' => get_setting('price_per_whatsapp', 10),
    'Landing Page Publish' => get_setting('price_landing_page_publish', 100),
    '1000 AI Content Words' => get_setting('price_per_ai_word', 10),
    'Social Post' => get_setting('price_per_social_post', 2),
    'QR Code Generation' => get_setting('price_per_qr_code', 25),
];

?>

<div class="container page-content">
    <h1>Our Pricing</h1>
    <p class="pricing-intro">Our "Pay-as-you-go" model is simple and transparent. Buy credits and use them for any of our services. No monthly fees, no hidden charges. Your credits never expire.</p>

    <h2>Buy Credits</h2>
    <p>Choose a package that suits your needs. The more you buy, the more you save!</p>

    <div class="pricing-grid">
        <?php foreach ($packages as $pkg) : ?>
            <div class="pricing-card <?php echo $pkg['is_popular'] ? 'popular' : ''; ?>">
                <?php if ($pkg['is_popular']) : ?>
                    <div class="popular-badge">Most Popular</div>
                <?php endif; ?>
                <h3><?php echo htmlspecialchars($pkg['name']); ?></h3>
                <div class="price"><?php echo '$' . number_format($pkg['price'], 2); ?></div>
                <div class="credits"><?php echo number_format($pkg['credits']); ?> Credits</div>
                <p><?php echo htmlspecialchars($pkg['description']); ?></p>
                <a href="/register.php?plan=<?php echo $pkg['id']; ?>" class="cta-button">Get Started</a>
            </div>
        <?php endforeach; ?>
         <div class="pricing-card enterprise">
            <h3>Enterprise</h3>
            <div class="price">Custom</div>
            <div class="credits">Unlimited Possibilities</div>
            <p>Need a custom plan for high-volume usage? Contact us for a personalized quote.</p>
            <a href="/contact.php" class="cta-button">Contact Sales</a>
        </div>
    </div>

    <h2 class="cost-title">Cost Per Action</h2>
    <p>Here's what you can do with your credits. Mix and match any of our services.</p>

    <div class="cost-table-container">
        <table class="cost-table">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Cost in Credits</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($costs as $service => $cost) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($service); ?></td>
                    <td><?php echo number_format($cost); ?> credit(s)</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer_public.php';
?>
