<?php
require_once '../config/db.php';
require_once '../src/lib/functions.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Fetch homepage content from CMS
$hero_title = get_setting('hero_title', $mysqli, 'Default Hero Title');
$hero_subtitle = get_setting('hero_subtitle', $mysqli, 'Default hero subtitle text goes here.');

// Fetch services/features
$features_result = $mysqli->query("SELECT icon, title, description FROM cms_features ORDER BY display_order ASC LIMIT 6");

// Fetch testimonials
$testimonials_result = $mysqli->query("SELECT author_name, quote, star_rating FROM testimonials ORDER BY display_order ASC LIMIT 3");

// Fetch pricing packages
$pricing_result = $mysqli->query("SELECT name, price, credits, is_popular FROM credit_packages ORDER BY price ASC LIMIT 3");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars(get_setting('site_name', $mysqli, 'Marketing Platform')); ?></title>
    <link rel="stylesheet" href="css/public_style.css"> <!-- We'll create this -->
</head>
<body>
    <?php include 'includes/site_header.php'; ?>

    <section class="hero">
        <h1><?php echo htmlspecialchars($hero_title); ?></h1>
        <p><?php echo htmlspecialchars($hero_subtitle); ?></p>
        <a href="register.php" class="cta-button">Get Started for Free</a>
    </section>

    <section class="services-summary">
        <h2>Our Services</h2>
        <div class="grid">
            <?php while($feature = $features_result->fetch_assoc()): ?>
            <div class="service-item">
                <!-- Icon would go here, e.g., <i class="<?php echo $feature['icon']; ?>"></i> -->
                <h3><?php echo htmlspecialchars($feature['title']); ?></h3>
                <p><?php echo htmlspecialchars($feature['description']); ?></p>
            </div>
            <?php endwhile; ?>
        </div>
    </section>

    <section class="testimonials">
        <h2>What Our Users Say</h2>
        <div class="grid">
             <?php while($testimonial = $testimonials_result->fetch_assoc()): ?>
            <div class="testimonial-item">
                <p class="quote">"<?php echo htmlspecialchars($testimonial['quote']); ?>"</p>
                <p class="author">- <?php echo htmlspecialchars($testimonial['author_name']); ?></p>
            </div>
            <?php endwhile; ?>
        </div>
    </section>

    <section class="pricing-preview">
        <h2>Simple, Pay-as-you-go Pricing</h2>
         <div class="grid">
            <?php while($package = $pricing_result->fetch_assoc()): ?>
            <div class="pricing-item">
                <h3><?php echo htmlspecialchars($package['name']); ?></h3>
                <p class="price">$<?php echo number_format($package['price'], 2); ?></p>
                <p class="credits"><?php echo number_format($package['credits']); ?> Credits</p>
            </div>
            <?php endwhile; ?>
        </div>
        <a href="pricing.php">See All Packages</a>
    </section>

    <?php include 'includes/site_footer.php'; ?>
</body>
</html>
