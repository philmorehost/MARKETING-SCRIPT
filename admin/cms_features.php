<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php'); exit;
}
require_once '../config/db.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $icon = $_POST['icon'] ?? 'icon-class';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';

    if ($action === 'create') {
        $stmt = $mysqli->prepare("INSERT INTO cms_features (icon, title, description) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $icon, $title, $description);
        $stmt->execute();
        $message = "Feature created.";
    } elseif ($action === 'update' && $id > 0) {
        $stmt = $mysqli->prepare("UPDATE cms_features SET icon=?, title=?, description=? WHERE id=?");
        $stmt->bind_param('sssi', $icon, $title, $description, $id);
        $stmt->execute();
        $message = "Feature updated.";
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $mysqli->prepare("DELETE FROM cms_features WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $message = "Feature deleted.";
    }
}

$features = $mysqli->query("SELECT * FROM cms_features ORDER BY display_order ASC, id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Features Editor</title><link rel="stylesheet" href="../public/css/admin_style.css"></head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="admin-container">
        <aside class="sidebar"><?php include 'includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Features Editor</h1>
            <?php if ($message): ?><p><?php echo $message; ?></p><?php endif; ?>

            <h2>Add New Feature</h2>
            <form action="" method="post">
                <input type="hidden" name="action" value="create">
                <input type="text" name="icon" placeholder="Icon Class (e.g., 'fa-star')">
                <input type="text" name="title" placeholder="Title" required>
                <textarea name="description" placeholder="Description"></textarea>
                <button type="submit">Create Feature</button>
            </form>
            <hr>
            <h2>Existing Features</h2>
            <?php while($feature = $features->fetch_assoc()): ?>
            <form action="" method="post">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?php echo $feature['id']; ?>">
                <input type="text" name="icon" value="<?php echo htmlspecialchars($feature['icon']); ?>">
                <input type="text" name="title" value="<?php echo htmlspecialchars($feature['title']); ?>">
                <textarea name="description"><?php echo htmlspecialchars($feature['description']); ?></textarea>
                <button type="submit">Update</button>
                <button type="submit" name="action" value="delete" onclick="return confirm('Are you sure?')">Delete</button>
            </form>
            <br>
            <?php endwhile; ?>
        </main>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
