<?php
require_once '../config/db.php';
require_once '../src/lib/functions.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Fetch all credit packages
$packages_result = $mysqli->query("SELECT name, description, price, credits, is_popular FROM credit_packages ORDER BY price ASC");

// Fetch credit costs from settings
$costs = [
    'Email Verification' => get_setting('price_per_verification', $mysqli, 1),
    'Email Send' => get_setting('price_per_email_send', $mysqli, 1),
    'SMS Page' => get_setting('price_per_sms_page', $mysqli, 5),
    'WhatsApp Message' => get_setting('price_per_whatsapp', $mysqli, 10),
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pricing - <?php echo htmlspecialchars(get_setting('site_name', $mysqli)); ?></title>
    <link rel="stylesheet" href="css/public_style.css">
</head>
<body>
    <?php include 'includes/site_header.php'; ?>

    <main class="page-content">
        <h1>Our Pricing</h1>
        <p>Simple, transparent, pay-as-you-go. Buy credits and use them for any of our services.</p>

        <section class="pricing-table">
            <h2>Credit Packages</h2>
            <div class="grid">
                <?php while($package = $packages_result->fetch_assoc()): ?>
                <div class="pricing-card <?php if ($package['is_popular']) echo 'popular'; ?>">
                    <?php if ($package['is_popular']): ?><div class="popular-badge">Most Popular</div><?php endif; ?>
                    <h3><?php echo htmlspecialchars($package['name']); ?></h3>
                    <p class="price">$<?php echo number_format($package['price'], 2); ?></p>
                    <p class="credits"><?php echo number_format($package['credits']); ?> Credits</p>
                    <p class="description"><?php echo htmlspecialchars($package['description']); ?></p>
                    <a href="register.php" class="button-primary">Get Started</a>
                </div>
                <?php endwhile; ?>
            </div>
        </section>

        <section class="credit-costs">
            <h2>How Credits Are Used</h2>
            <p>Your credits are deducted based on the services you use. Here's the breakdown:</p>
            <ul>
                <?php foreach($costs as $action => $cost): ?>
                <li><strong><?php echo $action; ?></strong> = <?php echo $cost; ?> credit(s)</li>
                <?php endforeach; ?>
            </ul>
        </section>
    </main>

    <?php include 'includes/site_footer.php'; ?>
</body>
</html>
