<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}
require_once '../config/db.php';
require_once '../src/lib/functions.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hero_title = $_POST['settings']['hero_title'] ?? '';
    $hero_subtitle = $_POST['settings']['hero_subtitle'] ?? '';

    // Using our UPSERT logic from the main settings page
    $stmt = $mysqli->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('hero_title', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param('ss', $hero_title, $hero_title);
    $stmt->execute();

    $stmt = $mysqli->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('hero_subtitle', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param('ss', $hero_subtitle, $hero_subtitle);
    $stmt->execute();

    $message = "Homepage content updated!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Homepage CMS</title>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="admin-container">
        <aside class="sidebar">
            <?php include 'includes/sidebar.php'; ?>
        </aside>
        <main class="main-content">
            <h1>Homepage Editor</h1>
            <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
            <form action="cms_homepage.php" method="post">
                <h2>Hero Section</h2>
                <label>Hero Title:</label>
                <input type="text" name="settings[hero_title]" value="<?php echo htmlspecialchars(get_setting('hero_title', $mysqli)); ?>">
                <label>Hero Subtitle:</label>
                <textarea name="settings[hero_subtitle]"><?php echo htmlspecialchars(get_setting('hero_subtitle', $mysqli)); ?></textarea>
                <br><br>
                <button type="submit">Save Homepage</button>
            </form>
        </main>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
