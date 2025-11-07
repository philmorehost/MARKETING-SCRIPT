<?php
$page_title = "CMS: Homepage";
require_once 'auth_admin.php';

// Using the same get_s function from settings, but aliased for content
function get_c($key, $default = '') {
    return get_setting($key, $default); // In this setup, CMS content is stored in the settings table
}
// This could be changed to use the cms_content table if preferred.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Logic to save content is the same as settings
    $posted_content = $_POST['content'];
    $update_stmt = $mysqli->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    foreach ($posted_content as $key => $value) {
        $update_stmt->bind_param("ss", $key, $value);
        $update_stmt->execute();
    }
    $success = true;
}


require_once 'includes/header_admin.php';
?>
<div class="container-fluid">
    <h1>CMS: Homepage Editor</h1>
     <?php if (isset($success) && $success): ?>
        <div class="alert alert-success">Homepage content saved successfully!</div>
    <?php endif; ?>

    <form action="cms_homepage.php" method="POST" class="card">
        <h3>Hero Section</h3>
        <div class="form-group">
            <label>Hero Title</label>
            <input type="text" name="content[hero_title]" value="<?php echo get_c('hero_title'); ?>" class="form-control">
        </div>
         <div class="form-group">
            <label>Hero Subtitle</label>
            <textarea name="content[hero_subtitle]" class="form-control"><?php echo get_c('hero_subtitle'); ?></textarea>
        </div>
        <div class="form-group">
            <label>Hero Call-to-Action Button Text</label>
            <input type="text" name="content[hero_cta]" value="<?php echo get_c('hero_cta'); ?>" class="form-control">
        </div>

        <hr>
        <h3>Footer Content</h3>
         <div class="form-group">
            <label>Footer 'About' Text</label>
            <textarea name="content[footer_about_text]" class="form-control"><?php echo get_c('footer_about_text'); ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Save Homepage Content</button>
    </form>
</div>
<?php
require_once 'includes/footer_admin.php';
?>
