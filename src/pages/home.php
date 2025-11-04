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
    <link rel="stylesheet" href="/public/css/public_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include APP_ROOT . '/public/includes/site_header.php'; ?>

    <main class="homepage">
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="container">
                <div class="hero-content">
                    <h1><?php echo htmlspecialchars($hero_title); ?></h1>
                    <p><?php echo htmlspecialchars($hero_subtitle); ?></p>
                    <a href="/public/register" class="btn btn-primary">Get Started for Free</a>
                </div>
            </div>
        </section>

        <!-- Services Section -->
        <section class="features-section">
            <div class="container">
                <div class="section-header">
                    <h2>Our Services</h2>
                    <p>A complete suite of tools to help you succeed.</p>
                </div>
                <div class="features-grid">
                    <?php
                    $services = json_decode(get_content('services_summary', $mysqli, '[]'), true);
                    foreach ($services as $service):
                        if (!empty($service['title'])):
                    ?>
                    <div class="feature-card">
                        <div class="feature-icon"><i class="<?php echo htmlspecialchars($service['icon']); ?>"></i></div>
                        <h3><?php echo htmlspecialchars($service['title']); ?></h3>
                        <p><?php echo htmlspecialchars($service['blurb']); ?></p>
                    </div>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </div>
            </div>
        </section>

        <!-- Testimonials Section -->
        <section class="testimonials-section">
            <div class="container">
                <div class="section-header">
                    <h2>Trusted by Businesses Worldwide</h2>
                </div>
                <div class="testimonials-grid">
                    <?php while ($testimonial = $testimonials_result->fetch_assoc()): ?>
                    <div class="testimonial-card">
                        <div class="testimonial-stars">
                            <?php for ($i = 0; $i < 5; $i++): ?>
                                <i class="fa<?php echo ($i < $testimonial['star_rating']) ? 's' : 'r'; ?> fa-star"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="quote">"<?php echo htmlspecialchars($testimonial['quote']); ?>"</p>
                        <div class="author">
                            <span class="author-name"><?php echo htmlspecialchars($testimonial['author_name']); ?></span>,
                            <span class="author-title"><?php echo htmlspecialchars($testimonial['author_title']); ?></span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </section>

        <!-- Pricing Preview Section -->
        <?php if ($popular_package): ?>
        <section class="pricing-section">
            <div class="container">
                <div class="section-header">
                    <h2>Simple, Pay-As-You-Go Pricing</h2>
                    <p>No monthly fees. No hidden costs. Only pay for what you use.</p>
                </div>
                <div class="pricing-card-container">
                    <div class="pricing-card popular">
                        <div class="popular-badge">Most Popular</div>
                        <h3><?php echo htmlspecialchars($popular_package['name']); ?></h3>
                        <div class="price">
                            <span class="currency">$</span><?php echo number_format($popular_package['price'], 2); ?>
                        </div>
                        <div class="credits"><?php echo number_format($popular_package['credits']); ?> Credits</div>
                        <p><?php echo htmlspecialchars($popular_package['description']); ?></p>
                        <a href="/public/pricing" class="btn btn-secondary">See All Plans</a>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <?php include APP_ROOT . '/public/includes/site_footer.php'; ?>
</body>
</html>
