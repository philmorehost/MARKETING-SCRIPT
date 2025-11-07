<?php
$page_title = "CMS: Testimonials";
require_once 'auth_admin.php';

// --- CRUD Logic ---
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$id = $_POST['id'] ?? $_GET['id'] ?? null;
$feedback = [];

if ($action) {
    $author_name = $_POST['author_name'] ?? '';
    $author_title = $_POST['author_title'] ?? '';
    $quote = $_POST['quote'] ?? '';
    $star_rating = $_POST['star_rating'] ?? 5;

    switch ($action) {
        case 'create':
            $stmt = $mysqli->prepare("INSERT INTO testimonials (author_name, author_title, quote, star_rating) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $author_name, $author_title, $quote, $star_rating);
            if($stmt->execute()) $feedback = ['type' => 'success', 'message' => 'Testimonial created.'];
            else $feedback = ['type' => 'danger', 'message' => 'Error.'];
            break;
        case 'update':
            $stmt = $mysqli->prepare("UPDATE testimonials SET author_name = ?, author_title = ?, quote = ?, star_rating = ? WHERE id = ?");
            $stmt->bind_param("sssii", $author_name, $author_title, $quote, $star_rating, $id);
            if($stmt->execute()) $feedback = ['type' => 'success', 'message' => 'Testimonial updated.'];
            else $feedback = ['type' => 'danger', 'message' => 'Error.'];
            break;
        case 'delete':
            $stmt = $mysqli->prepare("DELETE FROM testimonials WHERE id = ?");
            $stmt->bind_param("i", $id);
            if($stmt->execute()) $feedback = ['type' => 'success', 'message' => 'Testimonial deleted.'];
            else $feedback = ['type' => 'danger', 'message' => 'Error.'];
            header('Location: cms_testimonials.php');
            exit;
            break;
    }
}

$testimonials = $mysqli->query("SELECT * FROM testimonials ORDER BY display_order ASC")->fetch_all(MYSQLI_ASSOC);
$edit_testimonial = null;
if ($action === 'edit' && $id) {
    $stmt = $mysqli->prepare("SELECT * FROM testimonials WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_testimonial = $stmt->get_result()->fetch_assoc();
}

require_once 'includes/header_admin.php';
?>
<div class="container-fluid">
    <h1>CMS: Testimonials Editor</h1>
     <?php if (!empty($feedback)): ?>
        <div class="alert alert-<?php echo $feedback['type']; ?>"><?php echo $feedback['message']; ?></div>
    <?php endif; ?>

    <div class="card">
        <h3><?php echo $edit_testimonial ? 'Edit Testimonial' : 'Create New Testimonial'; ?></h3>
        <form action="cms_testimonials.php" method="POST">
             <input type="hidden" name="action" value="<?php echo $edit_testimonial ? 'update' : 'create'; ?>">
            <?php if ($edit_testimonial): ?>
                <input type="hidden" name="id" value="<?php echo $edit_testimonial['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Author Name</label>
                <input type="text" name="author_name" value="<?php echo $edit_testimonial['author_name'] ?? ''; ?>" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Author Title</label>
                <input type="text" name="author_title" value="<?php echo $edit_testimonial['author_title'] ?? ''; ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>Quote</label>
                <textarea name="quote" class="form-control" required><?php echo $edit_testimonial['quote'] ?? ''; ?></textarea>
            </div>
             <div class="form-group">
                <label>Star Rating (1-5)</label>
                <input type="number" name="star_rating" value="<?php echo $edit_testimonial['star_rating'] ?? 5; ?>" min="1" max="5" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary"><?php echo $edit_testimonial ? 'Save Changes' : 'Create Testimonial'; ?></button>
        </form>
    </div>

     <div class="card">
        <h3>Existing Testimonials</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Author</th>
                    <th>Quote</th>
                    <th>Rating</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($testimonials as $testimonial): ?>
                <tr>
                    <td><?php echo htmlspecialchars($testimonial['author_name']); ?></td>
                    <td><?php echo htmlspecialchars(substr($testimonial['quote'], 0, 100)); ?>...</td>
                    <td><?php echo $testimonial['star_rating']; ?></td>
                    <td>
                        <a href="cms_testimonials.php?action=edit&id=<?php echo $testimonial['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                        <a href="cms_testimonials.php?action=delete&id=<?php echo $testimonial['id']; ?>" onclick="return confirm('Are you sure?')" class="btn btn-sm btn-danger">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
require_once 'includes/footer_admin.php';
?>
