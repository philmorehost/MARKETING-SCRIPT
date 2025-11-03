<?php
// --- src/pages/pricing.php ---
$packages_result = $mysqli->query("SELECT name, description, price, credits, is_popular FROM credit_packages ORDER BY price ASC");
$costs = [
    'Email Verification' => get_setting('price_per_verification', $mysqli, 0.5),
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
    <link rel="stylesheet" href="/public/css/public_style.css">
</head>
<body>
    <?php include APP_ROOT . '/public/includes/site_header.php'; ?>

    <header class="page-header">
        <div class="container">
            <h1>Simple Pay-As-You-Go Pricing</h1>
            <p>No subscriptions. No monthly fees. Only pay for what you use.</p>
        </div>
    </header>

    <section class="pricing-table">
        <div class="container">
            <div class="grid-pricing">
                <?php while ($pkg = $packages_result->fetch_assoc()): ?>
                <div class="card <?php if ($pkg['is_popular']) echo 'popular'; ?>">
                    <?php if ($pkg['is_popular']) echo '<div class="popular-badge">Most Popular</div>'; ?>
                    <h3><?php echo htmlspecialchars($pkg['name']); ?></h3>
                    <p class="price">$<?php echo number_format($pkg['price'], 2); ?></p>
                    <p class="credits"><?php echo number_format($pkg['credits']); ?> Credits</p>
                    <p><?php echo htmlspecialchars($pkg['description']); ?></p>
                    <a href="/public/register" class="button-primary">Get Started</a>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <section class="credit-costs">
        <div class="container">
            <h2>How Credits Work</h2>
            <p>Different actions consume a different number of credits. Here’s a breakdown:</p>
            <div class="table-container">
                <table>
                    <thead><tr><th>Action</th><th>Cost in Credits</th></tr></thead>
                    <tbody>
                    <?php foreach ($costs as $action => $cost): ?>
                        <tr>
                            <td><?php echo $action; ?></td>
                            <td><?php echo rtrim(rtrim(number_format($cost, 4), '0'), '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <?php include APP_ROOT . '/public/includes/site_footer.php'; ?>
</body>
</html>
