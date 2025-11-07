<?php
$page_title = "CMS: Credit Packages";
require_once 'auth_admin.php';

// --- CRUD Logic ---
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$id = $_POST['id'] ?? $_GET['id'] ?? null;
$feedback = [];

if ($action) {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $credits = $_POST['credits'] ?? 0;
    $is_popular = isset($_POST['is_popular']) ? 1 : 0;

    switch ($action) {
        case 'create':
            $stmt = $mysqli->prepare("INSERT INTO credit_packages (name, description, price, credits, is_popular) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssddi", $name, $description, $price, $credits, $is_popular);
            if($stmt->execute()) $feedback = ['type' => 'success', 'message' => 'Package created.'];
            else $feedback = ['type' => 'danger', 'message' => 'Error creating package.'];
            break;
        case 'update':
            $stmt = $mysqli->prepare("UPDATE credit_packages SET name = ?, description = ?, price = ?, credits = ?, is_popular = ? WHERE id = ?");
            $stmt->bind_param("ssddii", $name, $description, $price, $credits, $is_popular, $id);
            if($stmt->execute()) $feedback = ['type' => 'success', 'message' => 'Package updated.'];
            else $feedback = ['type' => 'danger', 'message' => 'Error updating package.'];
            break;
        case 'delete':
            $stmt = $mysqli->prepare("DELETE FROM credit_packages WHERE id = ?");
            $stmt->bind_param("i", $id);
            if($stmt->execute()) $feedback = ['type' => 'success', 'message' => 'Package deleted.'];
            else $feedback = ['type' => 'danger', 'message' => 'Error deleting package.'];
            header('Location: cms_pricing.php');
            exit;
            break;
    }
}

$packages = $mysqli->query("SELECT * FROM credit_packages ORDER BY price ASC")->fetch_all(MYSQLI_ASSOC);
$edit_package = null;
if ($action === 'edit' && $id) {
    $stmt = $mysqli->prepare("SELECT * FROM credit_packages WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_package = $stmt->get_result()->fetch_assoc();
}


require_once 'includes/header_admin.php';
?>
<div class="container-fluid">
    <h1>CMS: Credit Package Editor</h1>
     <?php if (!empty($feedback)): ?>
        <div class="alert alert-<?php echo $feedback['type']; ?>"><?php echo $feedback['message']; ?></div>
    <?php endif; ?>

    <div class="card">
        <h3><?php echo $edit_package ? 'Edit Package' : 'Create New Package'; ?></h3>
        <form action="cms_pricing.php" method="POST">
             <input type="hidden" name="action" value="<?php echo $edit_package ? 'update' : 'create'; ?>">
            <?php if ($edit_package): ?>
                <input type="hidden" name="id" value="<?php echo $edit_package['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Package Name</label>
                <input type="text" name="name" value="<?php echo $edit_package['name'] ?? ''; ?>" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" value="<?php echo $edit_package['description'] ?? ''; ?>" class="form-control">
            </div>
             <div class="form-group">
                <label>Price (USD)</label>
                <input type="number" step="0.01" name="price" value="<?php echo $edit_package['price'] ?? ''; ?>" class="form-control" required>
            </div>
             <div class="form-group">
                <label>Credits</label>
                <input type="number" step="0.01" name="credits" value="<?php echo $edit_package['credits'] ?? ''; ?>" class="form-control" required>
            </div>
             <div class="form-group">
                <input type="checkbox" name="is_popular" value="1" <?php echo ($edit_package['is_popular'] ?? 0) ? 'checked' : ''; ?>>
                <label>Mark as "Most Popular"?</label>
            </div>
            <button type="submit" class="btn btn-primary"><?php echo $edit_package ? 'Save Changes' : 'Create Package'; ?></button>
        </form>
    </div>

     <div class="card">
        <h3>Existing Packages</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Credits</th>
                    <th>Popular</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($packages as $package): ?>
                <tr>
                    <td><?php echo htmlspecialchars($package['name']); ?></td>
                    <td>$<?php echo number_format($package['price'], 2); ?></td>
                    <td><?php echo number_format($package['credits']); ?></td>
                    <td><?php echo $package['is_popular'] ? 'Yes' : 'No'; ?></td>
                    <td>
                        <a href="cms_pricing.php?action=edit&id=<?php echo $package['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                        <a href="cms_pricing.php?action=delete&id=<?php echo $package['id']; ?>" onclick="return confirm('Are you sure?')" class="btn btn-sm btn-danger">Delete</a>
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
