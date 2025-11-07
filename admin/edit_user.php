<?php
$page_title = "Edit User";
require_once 'auth_admin.php';

$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    header('Location: users.php');
    exit;
}

// Fetch user data
$stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    // User not found
    header('Location: users.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Update Logic ---
    $name = $_POST['name'] ?? $user['name'];
    $email = $_POST['email'] ?? $user['email'];
    $role = $_POST['role'] ?? $user['role'];
    $status = $_POST['status'] ?? $user['status'];
    $credits = $_POST['credit_balance'] ?? $user['credit_balance'];
    $add_credits = $_POST['add_credits'] ?? 0;

    // Recalculate credits
    $new_credit_balance = (float)$credits + (float)$add_credits;

    // Simple validation
    if (empty($name) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Name and a valid email are required.";
    }

    // Prevent changing the last admin's role
    if ($user['role'] === 'admin' && $role !== 'admin') {
         $admin_count_res = $mysqli->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
         if ($admin_count_res->fetch_row()[0] <= 1) {
             $errors[] = "Cannot change the role of the last administrator.";
         }
    }


    if (empty($errors)) {
        $update_stmt = $mysqli->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ?, credit_balance = ? WHERE id = ?");
        $update_stmt->bind_param("ssssdi", $name, $email, $role, $status, $new_credit_balance, $user_id);

        if ($update_stmt->execute()) {
            $success = true;
            // Re-fetch user data to display updated values
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

        } else {
            $errors[] = "Failed to update user.";
        }
    }
}


require_once 'includes/header_admin.php';
?>

<div class="container-fluid">
    <h1>Edit User: <?php echo htmlspecialchars($user['name']); ?></h1>
    <a href="users.php" class="btn btn-secondary mb-3">Back to User List</a>

    <?php if ($success): ?>
        <div class="alert alert-success">User updated successfully!</div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): echo "<p>$error</p>"; endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form action="edit_user.php?id=<?php echo $user['id']; ?>" method="POST">
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>">
            </div>
             <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>">
            </div>
            <div class="form-group">
                <label for="role">Role</label>
                <select name="role" id="role" class="form-control">
                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
             <div class="form-group">
                <label for="status">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    <option value="pending" <?php echo $user['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                </select>
            </div>
            <hr>
            <h4>Credit Balance</h4>
            <div class="form-group">
                <label for="credit_balance">Current Credits</label>
                <input type="text" name="credit_balance" id="credit_balance" class="form-control" value="<?php echo $user['credit_balance']; ?>">
            </div>
             <div class="form-group">
                <label for="add_credits">Add/Remove Credits</label>
                <input type="text" name="add_credits" id="add_credits" class="form-control" placeholder="e.g., 500 or -100">
                <small class="form-text text-muted">Enter a positive value to add credits, or a negative value to remove them.</small>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>

<?php
require_once 'includes/footer_admin.php';
?>
