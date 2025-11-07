<?php
$page_title = "CMS: Simple Pages";
require_once 'auth_admin.php';

// The content for these pages is stored in the `settings` table for simplicity.
// Keys: privacy_policy_content, terms_of_service_content

function get_c($key, $default = '') {
    global $mysqli;
    static $content_cache = [];
    if (isset($content_cache[$key])) return $content_cache[$key];

    $stmt = $mysqli->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $content_cache[$key] = $row['setting_value'];
        return $row['setting_value'];
    }
    return $default;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $privacy_content = $_POST['privacy_policy_content'] ?? '';
    $terms_content = $_POST['terms_of_service_content'] ?? '';

    $update_stmt = $mysqli->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    // Update Privacy Policy
    $key = 'privacy_policy_content';
    $update_stmt->bind_param("ss", $key, $privacy_content);
    $update_stmt->execute();

    // Update Terms of Service
    $key = 'terms_of_service_content';
    $update_stmt->bind_param("ss", $key, $terms_content);
    $update_stmt->execute();

    $success = true;
}

require_once 'includes/header_admin.php';
?>
<div class="container-fluid">
    <h1>CMS: Simple Page Editor</h1>
    <?php if (isset($success) && $success): ?>
        <div class="alert alert-success">Page content saved successfully!</div>
    <?php endif; ?>

    <form action="cms_pages.php" method="POST" class="card">
        <div class="form-group">
            <label for="privacy_policy_content"><h3>Privacy Policy</h3></label>
            <textarea name="privacy_policy_content" id="privacy_policy_content" class="form-control" rows="15"><?php echo htmlspecialchars(get_c('privacy_policy_content')); ?></textarea>
        </div>
        <hr>
        <div class="form-group">
            <label for="terms_of_service_content"><h3>Terms of Service</h3></label>
            <textarea name="terms_of_service_content" id="terms_of_service_content" class="form-control" rows="15"><?php echo htmlspecialchars(get_c('terms_of_service_content')); ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Save Page Content</button>
    </form>
</div>
<?php
require_once 'includes/footer_admin.php';
?>
