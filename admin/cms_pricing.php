<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login'); exit;
}
$message = '';

// Handle POST actions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $name = $_POST['name'] ?? '';
    $desc = $_POST['description'] ?? '';
    $price = (float)($_POST['price'] ?? 0);
    $credits = (float)($_POST['credits'] ?? 0);
    $is_popular = isset($_POST['is_popular']) ? 1 : 0;

    if ($action === 'create' && !empty($name)) {
        $stmt = $mysqli->prepare("INSERT INTO credit_packages (name, description, price, credits, is_popular) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('ssddi', $name, $desc, $price, $credits, $is_popular);
        if($stmt->execute()) $message = "Package created.";
    } elseif ($action === 'update' && $id > 0 && !empty($name)) {
        $stmt = $mysqli->prepare("UPDATE credit_packages SET name=?, description=?, price=?, credits=?, is_popular=? WHERE id=?");
        $stmt->bind_param('ssddii', $name, $desc, $price, $credits, $is_popular, $id);
        if($stmt->execute()) $message = "Package updated.";
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $mysqli->prepare("DELETE FROM credit_packages WHERE id=?");
        $stmt->bind_param('i', $id);
        if($stmt->execute()) $message = "Package deleted.";
    }
}

// Fetch all packages
$packages = $mysqli->query("SELECT * FROM credit_packages ORDER BY price");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Credit Package Editor</title>
    <link rel="stylesheet" href="/css/admin_style.css">
</head>
<body>
    <?php include APP_ROOT . '/admin/includes/header.php'; ?>
    <div class="admin-container">
        <aside class="sidebar"><?php include APP_ROOT . '/admin/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Credit Package Editor</h1>
            <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>

            <div class="card">
                <h2>Add New Package</h2>
                <form action="" method="post">
                    <input type="hidden" name="action" value="create">
                    <div class="form-group"><label>Name:</label><input type="text" name="name" required></div>
                    <div class="form-group"><label>Description:</label><input type="text" name="description"></div>
                    <div class="form-group"><label>Price (USD):</label><input type="number" step="0.01" name="price" required></div>
                    <div class="form-group"><label>Credits:</label><input type="number" step="0.0001" name="credits" required></div>
                    <div class="form-group"><label><input type="checkbox" name="is_popular" value="1"> Mark as Popular</label></div>
                    <button type="submit">Create Package</button>
                </form>
            </div>

            <hr>

            <h2>Existing Packages</h2>
            <div class="table-container">
                <table>
                    <thead><tr><th>Name</th><th>Description</th><th>Price</th><th>Credits</th><th>Popular</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php while($pkg = $packages->fetch_assoc()): ?>
                    <tr>
                        <form action="" method="post">
                            <input type="hidden" name="id" value="<?php echo $pkg['id']; ?>">
                            <td><input type="text" name="name" value="<?php echo htmlspecialchars($pkg['name']); ?>"></td>
                            <td><input type="text" name="description" value="<?php echo htmlspecialchars($pkg['description']); ?>"></td>
                            <td><input type="number" step="0.01" name="price" value="<?php echo $pkg['price']; ?>"></td>
                            <td><input type="number" step="0.0001" name="credits" value="<?php echo $pkg['credits']; ?>"></td>
                            <td><input type="checkbox" name="is_popular" value="1" <?php if($pkg['is_popular']) echo 'checked'; ?>></td>
                            <td class="actions">
                                <button type="submit" name="action" value="update">Update</button>
                                <button type="submit" name="action" value="delete" class="danger" onclick="return confirm('Delete this package?');">Delete</button>
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
