<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /public/login');
    exit;
}
require_once '../config/db.php';
require_once '../src/lib/functions.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content_keys = ['hero_title', 'hero_subtitle'];
    $all_ok = true;
    foreach ($content_keys as $key) {
        if (isset($_POST['content'][$key])) {
            if (!update_content($key, $_POST['content'][$key], $mysqli)) {
                $all_ok = false;
            }
        }
    }

    if ($all_ok) {
        $message = "Homepage content updated successfully!";
    } else {
        $message = "An error occurred while updating homepage content.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Homepage CMS</title>
    <link rel="stylesheet" href="/public/css/admin_style.css">
</head>
<body>
    <?php include APP_ROOT . '/admin/includes/header.php'; ?>
    <div class="admin-container">
        <aside class="sidebar">
            <?php include APP_ROOT . '/admin/includes/sidebar.php'; ?>
        </aside>
        <main class="main-content">
            <h1>Homepage Editor</h1>
            <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
            <form action="" method="post">
                <div class="form-group">
                    <h2>Hero Section</h2>
                    <label for="hero_title">Hero Title:</label>
                    <input type="text" id="hero_title" name="content[hero_title]" value="<?php echo htmlspecialchars(get_content('hero_title', $mysqli)); ?>">
                </div>
                <div class="form-group">
                    <label for="hero_subtitle">Hero Subtitle:</label>
                    <textarea id="hero_subtitle" name="content[hero_subtitle]"><?php echo htmlspecialchars(get_content('hero_subtitle', $mysqli)); ?></textarea>
                </div>
                <button type="submit">Save Homepage</button>
            </form>
        </main>
    </div>
    <?php include APP_ROOT . '/admin/includes/footer.php'; ?>
</body>
</html>
