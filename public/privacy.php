<?php
require_once '../config/db.php';
require_once '../src/lib/functions.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$privacy_policy = get_setting('privacy_policy', $mysqli, 'Privacy policy not yet set.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Privacy Policy</title>
</head>
<body>
    <?php include 'includes/site_header.php'; ?>
    <main class="page-content">
        <h1>Privacy Policy</h1>
        <div><?php echo nl2br(htmlspecialchars($privacy_policy)); ?></div>
    </main>
    <?php include 'includes/site_footer.php'; ?>
</body>
</html>
