<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php'); exit;
}
require_once '../config/db.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
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

    if ($action === 'create') {
        $stmt = $mysqli->prepare("INSERT INTO credit_packages (name, description, price, credits, is_popular) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('ssddi', $name, $desc, $price, $credits, $is_popular);
        $stmt->execute();
        $message = "Package created.";
    } elseif ($action === 'update' && $id > 0) {
        $stmt = $mysqli->prepare("UPDATE credit_packages SET name=?, description=?, price=?, credits=?, is_popular=? WHERE id=?");
        $stmt->bind_param('ssddii', $name, $desc, $price, $credits, $is_popular, $id);
        $stmt->execute();
        $message = "Package updated.";
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $mysqli->prepare("DELETE FROM credit_packages WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $message = "Package deleted.";
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
    <link rel="stylesheet" href="../public/css/admin_style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="admin-container">
        <aside class="sidebar">
            <?php include 'includes/sidebar.php'; ?>
        </aside>
        <main class="main-content">
            <h1>Credit Package Editor</h1>
            <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>

            <h2>Add New Package</h2>
            <form action="cms_pricing.php" method="post">
                <input type="hidden" name="action" value="create">
                <input type="text" name="name" placeholder="Package Name" required>
                <input type="text" name="description" placeholder="Description">
                <input type="number" step="0.01" name="price" placeholder="Price (USD)" required>
                <input type="number" step="0.0001" name="credits" placeholder="Credits" required>
                <label><input type="checkbox" name="is_popular" value="1"> Mark as Popular</label>
                <button type="submit">Create Package</button>
            </form>

            <hr>

            <h2>Existing Packages</h2>
            <table>
            <?php while($pkg = $packages->fetch_assoc()): ?>
                <tr>
                    <td>
                        <form action="cms_pricing.php" method="post">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?php echo $pkg['id']; ?>">
                            <input type="text" name="name" value="<?php echo htmlspecialchars($pkg['name']); ?>">
                            <input type="text" name="description" value="<?php echo htmlspecialchars($pkg['description']); ?>">
                            <input type="number" step="0.01" name="price" value="<?php echo $pkg['price']; ?>">
                            <input type="number" step="0.0001" name="credits" value="<?php echo $pkg['credits']; ?>">
                            <input type="checkbox" name="is_popular" value="1" <?php if($pkg['is_popular']) echo 'checked'; ?>>
                            <button type="submit">Update</button>
                        </form>
                    </td>
                    <td>
                        <form action="cms_pricing.php" method="post" onsubmit="return confirm('Delete this package?');">
                             <input type="hidden" name="action" value="delete">
                             <input type="hidden" name="id" value="<?php echo $pkg['id']; ?>">
                             <button type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            </table>
        </main>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
