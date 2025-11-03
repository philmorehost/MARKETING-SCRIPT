<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php'); exit;
}
require_once '../config/db.php';
require_once '../src/lib/functions.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $privacy_policy = $_POST['privacy_policy'] ?? '';
    $terms_of_service = $_POST['terms_of_service'] ?? '';

    $stmt = $mysqli->prepare("INSERT INTO cms_content (content_key, content_value) VALUES ('privacy_policy', ?) ON DUPLICATE KEY UPDATE content_value = ?");
    $stmt->bind_param('ss', $privacy_policy, $privacy_policy);
    $stmt->execute();

    $stmt = $mysqli->prepare("INSERT INTO cms_content (content_key, content_value) VALUES ('terms_of_service', ?) ON DUPLICATE KEY UPDATE content_value = ?");
    $stmt->bind_param('ss', $terms_of_service, $terms_of_service);
    $stmt->execute();

    $message = "Pages updated successfully.";
}

// Fetch content from cms_content table
$privacy_policy = '';
$terms_of_service = '';
$content_result = $mysqli->query("SELECT content_key, content_value FROM cms_content WHERE content_key IN ('privacy_policy', 'terms_of_service')");
while($row = $content_result->fetch_assoc()) {
    if ($row['content_key'] === 'privacy_policy') {
        $privacy_policy = $row['content_value'];
    }
    if ($row['content_key'] === 'terms_of_service') {
        $terms_of_service = $row['content_value'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Simple Page Editor</title></head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="admin-container">
        <aside class="sidebar"><?php include 'includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Simple Page Editor</h1>
            <?php if ($message): ?><p><?php echo $message; ?></p><?php endif; ?>
            <form action="" method="post">
                <h2>Privacy Policy</h2>
                <textarea name="privacy_policy" rows="15" style="width:100%;"><?php echo htmlspecialchars($privacy_policy); ?></textarea>

                <h2>Terms of Service</h2>
                <textarea name="terms_of_service" rows="15" style="width:100%;"><?php echo htmlspecialchars($terms_of_service); ?></textarea>

                <button type="submit">Save Pages</button>
            </form>
        </main>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
