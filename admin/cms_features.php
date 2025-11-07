<?php
$page_title = "CMS: Features";
require_once 'auth_admin.php';

// --- CRUD Logic ---
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$id = $_POST['id'] ?? $_GET['id'] ?? null;
$feedback = [];

if ($action) {
    $icon = $_POST['icon'] ?? '';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';

    switch ($action) {
        case 'create':
            $stmt = $mysqli->prepare("INSERT INTO cms_features (icon, title, description) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $icon, $title, $description);
            if($stmt->execute()) $feedback = ['type' => 'success', 'message' => 'Feature created.'];
            else $feedback = ['type' => 'danger', 'message' => 'Error creating feature.'];
            break;
        case 'update':
            $stmt = $mysqli->prepare("UPDATE cms_features SET icon = ?, title = ?, description = ? WHERE id = ?");
            $stmt->bind_param("sssi", $icon, $title, $description, $id);
            if($stmt->execute()) $feedback = ['type' => 'success', 'message' => 'Feature updated.'];
            else $feedback = ['type' => 'danger', 'message' => 'Error updating feature.'];
            break;
        case 'delete':
            $stmt = $mysqli->prepare("DELETE FROM cms_features WHERE id = ?");
            $stmt->bind_param("i", $id);
            if($stmt->execute()) $feedback = ['type' => 'success', 'message' => 'Feature deleted.'];
            else $feedback = ['type' => 'danger', 'message' => 'Error deleting feature.'];
            header('Location: cms_features.php'); // Redirect after delete
            exit;
            break;
    }
}

// Fetch all features
$features = $mysqli->query("SELECT * FROM cms_features ORDER BY display_order ASC")->fetch_all(MYSQLI_ASSOC);

$edit_feature = null;
if ($action === 'edit' && $id) {
    $stmt = $mysqli->prepare("SELECT * FROM cms_features WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_feature = $stmt->get_result()->fetch_assoc();
}


require_once 'includes/header_admin.php';
?>
<div class="container-fluid">
    <h1>CMS: Features Editor</h1>
    <?php if (!empty($feedback)): ?>
        <div class="alert alert-<?php echo $feedback['type']; ?>"><?php echo $feedback['message']; ?></div>
    <?php endif; ?>

    <div class="card">
        <h3><?php echo $edit_feature ? 'Edit Feature' : 'Create New Feature'; ?></h3>
        <form action="cms_features.php" method="POST">
            <input type="hidden" name="action" value="<?php echo $edit_feature ? 'update' : 'create'; ?>">
            <?php if ($edit_feature): ?>
                <input type="hidden" name="id" value="<?php echo $edit_feature['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Font Awesome Icon Class (e.g., fas fa-star)</label>
                <input type="text" name="icon" value="<?php echo $edit_feature['icon'] ?? ''; ?>" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" value="<?php echo $edit_feature['title'] ?? ''; ?>" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" required><?php echo $edit_feature['description'] ?? ''; ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><?php echo $edit_feature ? 'Save Changes' : 'Create Feature'; ?></button>
            <?php if ($edit_feature): ?>
                <a href="cms_features.php" class="btn btn-secondary">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h3>Existing Features</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Icon</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($features as $feature): ?>
                <tr>
                    <td><i class="<?php echo $feature['icon']; ?>"></i></td>
                    <td><?php echo htmlspecialchars($feature['title']); ?></td>
                    <td><?php echo htmlspecialchars(substr($feature['description'], 0, 100)); ?>...</td>
                    <td>
                        <a href="cms_features.php?action=edit&id=<?php echo $feature['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                        <a href="cms_features.php?action=delete&id=<?php echo $feature['id']; ?>" onclick="return confirm('Are you sure?')" class="btn btn-sm btn-danger">Delete</a>
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
