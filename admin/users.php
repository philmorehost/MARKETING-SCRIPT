<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}


// Handle form submissions for user actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? null;
    $action = $_POST['action'] ?? '';

    if ($user_id) {
        switch ($action) {
            case 'suspend':
                $stmt = $mysqli->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $message = "User suspended.";
                break;
            case 'activate':
                $stmt = $mysqli->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $message = "User activated.";
                break;
            case 'delete':
                $stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $message = "User deleted.";
                break;
            case 'update_credits':
                $credits = (float)($_POST['credits'] ?? 0);
                $stmt = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance + ? WHERE id = ?");
                $stmt->bind_param('di', $credits, $user_id);
                $stmt->execute();
                $message = "Credits updated.";
                break;
        }
    }
}


// Fetch users
$search = $_GET['search'] ?? '';
if ($search) {
    $stmt = $mysqli->prepare("SELECT id, name, email, credit_balance, status, created_at FROM users WHERE name LIKE ? OR email LIKE ? ORDER BY id DESC");
    $search_param = "%{$search}%";
    $stmt->bind_param('ss', $search_param, $search_param);
} else {
    $stmt = $mysqli->prepare("SELECT id, name, email, credit_balance, status, created_at FROM users ORDER BY id DESC");
}
$stmt->execute();
$users_result = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management</title>
    <link rel="stylesheet" href="../public/css/admin_style.css">
</head>
<body>
    <?php include APP_ROOT . '/admin/includes/header.php'; ?>
    <div class="admin-container">
        <aside class="sidebar">
            <?php include APP_ROOT . '/admin/includes/sidebar.php'; ?>
        </aside>
        <main class="main-content">
            <h1>User Management</h1>
            <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>

            <form method="get" action="">
                <input type="text" name="search" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">Search</button>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Credits</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo number_format($user['credit_balance'], 4); ?></td>
                        <td><?php echo htmlspecialchars($user['status']); ?></td>
                        <td><?php echo $user['created_at']; ?></td>
                        <td>
                            <!-- Actions Form -->
                            <form action="" method="post" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <?php if ($user['status'] === 'suspended'): ?>
                                <button type="submit" name="action" value="activate">Activate</button>
                                <?php else: ?>
                                <button type="submit" name="action" value="suspend">Suspend</button>
                                <?php endif; ?>
                                <button type="submit" name="action" value="delete" onclick="return confirm('Are you sure?')">Delete</button>
                            </form>
                            <!-- Credit Form -->
                            <form action="" method="post" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <input type="number" step="0.0001" name="credits" placeholder="Add/Remove Credits">
                                <button type="submit" name="action" value="update_credits">Update</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </main>
    </div>
    <?php include APP_ROOT . '/admin/includes/footer.php'; ?>
</body>
</html>
