<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login'); exit;
}
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $author_name = $_POST['author_name'] ?? '';
    $author_title = $_POST['author_title'] ?? '';
    $quote = $_POST['quote'] ?? '';
    $star_rating = (int)($_POST['star_rating'] ?? 5);


    if ($action === 'create' && !empty($author_name) && !empty($quote)) {
        $stmt = $mysqli->prepare("INSERT INTO testimonials (author_name, author_title, quote, star_rating) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('sssi', $author_name, $author_title, $quote, $star_rating);
        if ($stmt->execute()) $message = "Testimonial created.";
    } elseif ($action === 'update' && $id > 0) {
        $stmt = $mysqli->prepare("UPDATE testimonials SET author_name=?, author_title=?, quote=?, star_rating=? WHERE id=?");
        $stmt->bind_param('sssii', $author_name, $author_title, $quote, $star_rating, $id);
        if ($stmt->execute()) $message = "Testimonial updated.";
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $mysqli->prepare("DELETE FROM testimonials WHERE id=?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) $message = "Testimonial deleted.";
    }
}

$testimonials = $mysqli->query("SELECT * FROM testimonials ORDER BY display_order ASC, id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Testimonials Editor</title><link rel="stylesheet" href="/css/admin_style.css"></head>
<body>
    <?php include APP_ROOT . '/admin/includes/header.php'; ?>
    <div class="admin-container">
        <aside class="sidebar"><?php include APP_ROOT . '/admin/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Testimonials Editor</h1>
            <?php if ($message): ?><p class="message success"><?php echo $message; ?></p><?php endif; ?>

            <div class="card">
                <h2>Add New Testimonial</h2>
                <form action="" method="post">
                    <input type="hidden" name="action" value="create">
                    <div class="form-group"><label>Author's Name:</label><input type="text" name="author_name" required></div>
                    <div class="form-group"><label>Author's Title:</label><input type="text" name="author_title"></div>
                    <div class="form-group"><label>Star Rating:</label><input type="number" name="star_rating" min="1" max="5" value="5" required></div>
                    <div class="form-group"><label>Quote:</label><textarea name="quote" required></textarea></div>
                    <button type="submit">Create Testimonial</button>
                </form>
            </div>
            <hr>
            <h2>Existing Testimonials</h2>
            <div class="table-container">
                <table>
                    <thead><tr><th>Author</th><th>Title</th><th>Quote</th><th>Rating</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php while($testimonial = $testimonials->fetch_assoc()): ?>
                    <tr>
                        <form action="" method="post">
                            <input type="hidden" name="id" value="<?php echo $testimonial['id']; ?>">
                            <td><input type="text" name="author_name" value="<?php echo htmlspecialchars($testimonial['author_name']); ?>"></td>
                            <td><input type="text" name="author_title" value="<?php echo htmlspecialchars($testimonial['author_title']); ?>"></td>
                            <td><textarea name="quote"><?php echo htmlspecialchars($testimonial['quote']); ?></textarea></td>
                            <td><input type="number" name="star_rating" min="1" max="5" value="<?php echo $testimonial['star_rating']; ?>"></td>
                            <td class="actions">
                                <button type="submit" name="action" value="update">Update</button>
                                <button type="submit" name="action" value="delete" class="danger" onclick="return confirm('Are you sure?')">Delete</button>
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
