<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /public/login');
    exit;
}
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

    // Handle services summary
    if (isset($_POST['services'])) {
        if(!update_content('services_summary', json_encode($_POST['services']), $mysqli)) {
            $all_ok = false;
        }
    }

    // Handle hero image upload
    if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = APP_ROOT . '/public/uploads/';
        $filename = 'hero_image.' . pathinfo($_FILES['hero_image']['name'], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES['hero_image']['tmp_name'], $upload_dir . $filename)) {
            // We'll store the path in settings instead of content for consistency
            $stmt = $mysqli->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('hero_image', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $db_path = 'uploads/' . $filename;
            $stmt->bind_param('ss', $db_path, $db_path);
            if (!$stmt->execute()) {
                 $all_ok = false;
            }
        } else {
            $all_ok = false;
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
                 <div class="form-group">
                    <label for="hero_image">Hero Image</label>
                    <input type="file" id="hero_image" name="hero_image">
                    <p>Current Image:</p>
                    <img src="/public/<?php echo htmlspecialchars(get_setting('hero_image', $mysqli, 'images/default_hero.jpg')); ?>" alt="Hero Image" style="max-width: 200px; display: block; margin-top: 10px;">
                </div>
                 <h2>Services Summary Section</h2>
                <?php
                $services = json_decode(get_content('services_summary', $mysqli, '[]'), true);
                for($i = 0; $i < 4; $i++):
                    $service = $services[$i] ?? ['icon' => '', 'title' => '', 'blurb' => ''];
                ?>
                <div class="form-group">
                    <h4>Service <?php echo $i + 1; ?></h4>
                    <label>Icon (e.g., 'fas fa-envelope'):</label> <input type="text" name="services[<?php echo $i; ?>][icon]" value="<?php echo htmlspecialchars($service['icon']); ?>">
                    <label>Title:</label> <input type="text" name="services[<?php echo $i; ?>][title]" value="<?php echo htmlspecialchars($service['title']); ?>">
                    <label>Blurb:</label> <textarea name="services[<?php echo $i; ?>][blurb]"><?php echo htmlspecialchars($service['blurb']); ?></textarea>
                </div>
                <?php endfor; ?>
                <button type="submit">Save Homepage</button>
            </form>
        </main>
    </div>
    <?php include APP_ROOT . '/admin/includes/footer.php'; ?>
</body>
</html>
