<?php
// --- src/pages/home.php ---

// Fetch dynamic content for the homepage
$hero_title = get_content('hero_title', $mysqli, 'Powerful Email Marketing Made Simple');
$hero_subtitle = get_content('hero_subtitle', $mysqli, 'Engage your audience, grow your business.');

$features_result = $mysqli->query("SELECT icon, title, description FROM cms_features ORDER BY display_order ASC LIMIT 4");
$testimonials_result = $mysqli->query("SELECT author_name, author_title, quote, star_rating FROM testimonials ORDER BY display_order ASC LIMIT 3");
$packages_result = $mysqli->query("SELECT name, description, price, credits FROM credit_packages WHERE is_popular = 1 ORDER BY price ASC LIMIT 1");
$popular_package = $packages_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(get_setting('site_name', $mysqli, 'Marketing Platform')); ?></title>
    <link rel="stylesheet" href="/css/public_style.css">
</head>
<body>
    <?php include APP_ROOT . '/public/includes/site_header.php'; ?>

    <!-- Hero Section -->
    <header class="hero">
        <div class="container">
            <h1><?php echo htmlspecialchars($hero_title); ?></h1>
            <p><?php echo htmlspecialchars($hero_subtitle); ?></p>
            <a href="/register" class="button-primary">Get Started for Free</a>
        </div>
    </header>

    <!-- Services Summary -->
    <section class="services-summary">
        <div class="container">
            <h2>Our Services</h2>
            <div class="grid">
                <?php while ($feature = $features_result->fetch_assoc()): ?>
                <div class="card">
                    <i class="fa <?php echo htmlspecialchars($feature['icon']); ?>"></i>
                    <h3><?php echo htmlspecialchars($feature['title']); ?></h3>
                    <p><?php echo htmlspecialchars($feature['description']); ?></p>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="testimonials">
        <div class="container">
            <h2>What Our Users Say</h2>
            <div class="grid">
                <?php while ($testimonial = $testimonials_result->fetch_assoc()): ?>
                <div class="card">
                    <p class="quote">"<?php echo htmlspecialchars($testimonial['quote']); ?>"</p>
                    <p class="author">- <?php echo htmlspecialchars($testimonial['author_name']); ?>, <?php echo htmlspecialchars($testimonial['author_title']); ?></p>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <!-- Pricing Preview -->
    <?php if ($popular_package): ?>
    <section class="pricing-preview">
        <div class="container">
            <h2>Simple, Pay-As-You-Go Pricing</h2>
            <div class="card popular">
                <h3>Most Popular Plan</h3>
                <h4><?php echo htmlspecialchars($popular_package['name']); ?></h4>
                <p class="price">$<?php echo number_format($popular_package['price'], 2); ?></p>
                <p><?php echo number_format($popular_package['credits']); ?> Credits</p>
                <a href="/pricing" class="button-secondary">View All Plans</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php include APP_ROOT . '/public/includes/site_footer.php'; ?>
</body>
</html>
