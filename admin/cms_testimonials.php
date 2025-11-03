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
    $author_name = $_POST['author_name'] ?? '';
    $quote = $_POST['quote'] ?? '';

    if ($action === 'create') {
        $stmt = $mysqli->prepare("INSERT INTO testimonials (author_name, quote) VALUES (?, ?)");
        $stmt->bind_param('ss', $author_name, $quote);
        $stmt->execute();
        $message = "Testimonial created.";
    } elseif ($action === 'update' && $id > 0) {
        $stmt = $mysqli->prepare("UPDATE testimonials SET author_name=?, quote=? WHERE id=?");
        $stmt->bind_param('ssi', $author_name, $quote, $id);
        $stmt->execute();
        $message = "Testimonial updated.";
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $mysqli->prepare("DELETE FROM testimonials WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $message = "Testimonial deleted.";
    }
}

$testimonials = $mysqli->query("SELECT * FROM testimonials ORDER BY display_order ASC, id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Testimonials Editor</title><link rel="stylesheet" href="../public/css/admin_style.css"></head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="admin-container">
        <aside class="sidebar"><?php include 'includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Testimonials Editor</h1>
            <?php if ($message): ?><p><?php echo $message; ?></p><?php endif; ?>

            <h2>Add New Testimonial</h2>
            <form action="" method="post">
                <input type="hidden" name="action" value="create">
                <input type="text" name="author_name" placeholder="Author's Name" required>
                <textarea name="quote" placeholder="Testimonial content..." required></textarea>
                <button type="submit">Create Testimonial</button>
            </form>
            <hr>
            <h2>Existing Testimonials</h2>
            <?php while($testimonial = $testimonials->fetch_assoc()): ?>
            <form action="" method="post">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?php echo $testimonial['id']; ?>">
                <input type="text" name="author_name" value="<?php echo htmlspecialchars($testimonial['author_name']); ?>">
                <textarea name="quote"><?php echo htmlspecialchars($testimonial['quote']); ?></textarea>
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
