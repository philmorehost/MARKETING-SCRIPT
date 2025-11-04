<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login'); exit;
}
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $icon = $_POST['icon'] ?? 'fa-star';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';

    if ($action === 'create' && !empty($title)) {
        $stmt = $mysqli->prepare("INSERT INTO cms_features (icon, title, description) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $icon, $title, $description);
        if ($stmt->execute()) $message = "Feature created successfully.";
        else $message = "Error creating feature.";
    } elseif ($action === 'update' && $id > 0 && !empty($title)) {
        $stmt = $mysqli->prepare("UPDATE cms_features SET icon=?, title=?, description=? WHERE id=?");
        $stmt->bind_param('sssi', $icon, $title, $description, $id);
        if ($stmt->execute()) $message = "Feature updated successfully.";
        else $message = "Error updating feature.";
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $mysqli->prepare("DELETE FROM cms_features WHERE id=?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) $message = "Feature deleted successfully.";
        else $message = "Error deleting feature.";
    }
}

$features_result = $mysqli->query("SELECT * FROM cms_features ORDER BY display_order ASC, id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Features Editor</title><link rel="stylesheet" href="/public/css/admin_style.css"></head>
<body>
    <?php include APP_ROOT . '/admin/includes/header.php'; ?>
    <div class="admin-container">
        <aside class="sidebar"><?php include APP_ROOT . '/admin/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Features Editor</h1>
            <?php if ($message): ?><p class="message success"><?php echo $message; ?></p><?php endif; ?>

            <div class="card">
                <h2>Add New Feature</h2>
                <form action="" method="post">
                    <input type="hidden" name="action" value="create">
                    <div class="form-group"><label for="icon">Icon Class</label><input id="icon" type="text" name="icon" placeholder="e.g., 'fa-star'"></div>
                    <div class="form-group"><label for="title">Title</label><input id="title" type="text" name="title" required></div>
                    <div class="form-group"><label for="description">Description</label><textarea id="description" name="description"></textarea></div>
                    <button type="submit">Create Feature</button>
                </form>
            </div>

            <hr>
            <h2>Existing Features</h2>
            <div class="table-container">
                <table>
                    <thead><tr><th>Icon</th><th>Title</th><th>Description</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php while($feature = $features_result->fetch_assoc()): ?>
                        <tr>
                            <form action="" method="post">
                                <input type="hidden" name="id" value="<?php echo $feature['id']; ?>">
                                <td><input type="text" name="icon" value="<?php echo htmlspecialchars($feature['icon']); ?>"></td>
                                <td><input type="text" name="title" value="<?php echo htmlspecialchars($feature['title']); ?>"></td>
                                <td><textarea name="description"><?php echo htmlspecialchars($feature['description']); ?></textarea></td>
                                <td class="actions">
                                    <button type="submit" name="action" value="update">Update</button>
                                    <button type="submit" name="action" value="delete" onclick="return confirm('Are you sure you want to delete this feature?')" class="danger">Delete</button>
                                </td>
                            </form>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <?php include APP_ROOT . '/admin/includes/footer.php'; ?>
</body>
</html>
