<?php
require_once '../config/db.php';
require_once '../src/lib/functions.php';
$terms_of_service = get_setting('terms_of_service', $mysqli, 'Terms of service not yet set.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Terms of Service</title>
    <link rel="stylesheet" href="css/public_style.css">
</head>
<body>
    <?php include APP_ROOT . '/public/includes/site_header.php'; ?>
    <main class="page-content">
        <h1>Terms of Service</h1>
        <div><?php echo nl2br(htmlspecialchars($terms_of_service)); ?></div>
    </main>
    <?php include APP_ROOT . '/public/includes/site_footer.php'; ?>
</body>
</html>
