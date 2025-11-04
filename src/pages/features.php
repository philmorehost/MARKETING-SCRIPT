<?php
// --- src/pages/features.php ---
$features_result = $mysqli->query("SELECT icon, title, description FROM cms_features ORDER BY display_order ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Features - <?php echo htmlspecialchars(get_setting('site_name', $mysqli)); ?></title>
    <link rel="stylesheet" href="/css/public_style.css">
</head>
<body>
    <?php include APP_ROOT . '/public/includes/site_header.php'; ?>

    <header class="page-header">
        <div class="container">
            <h1>Our Features</h1>
            <p>Everything you need to build, manage, and grow your audience.</p>
        </div>
    </header>

    <section class="features-list">
        <div class="container">
            <div class="grid-large">
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

    <?php include APP_ROOT . '/public/includes/site_footer.php'; ?>
</body>
</html>
