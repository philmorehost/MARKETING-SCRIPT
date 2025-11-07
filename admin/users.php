<?php
$page_title = "User Management";
require_once 'auth_admin.php';
require_once 'includes/header_admin.php';

// --- Logic for actions ---
$action = $_GET['action'] ?? null;
$user_id = $_GET['user_id'] ?? null;

// 'Login As User' Action
if ($action === 'login_as' && $user_id) {
    // Cannot login as another admin
    $user_to_login_stmt = $mysqli->prepare("SELECT id, role FROM users WHERE id = ? AND role != 'admin'");
    $user_to_login_stmt->bind_param("i", $user_id);
    $user_to_login_stmt->execute();
    $user_to_login = $user_to_login_stmt->get_result()->fetch_assoc();

    if ($user_to_login) {
        // Store current admin's session
        $_SESSION['admin_user_id'] = $_SESSION['user_id'];
        $_SESSION['admin_user_role'] = $_SESSION['user_role'];

        // Switch to the user's session
        $_SESSION['user_id'] = $user_to_login['id'];
        $_SESSION['user_role'] = $user_to_login['role'];

        header('Location: /dashboard');
        exit;
    }
}

// --- Fetch user list ---
$search = $_GET['search'] ?? '';
$query = "SELECT id, name, email, role, status, credit_balance, created_at FROM users";
$params = [];
$types = '';

if (!empty($search)) {
    $query .= " WHERE name LIKE ? OR email LIKE ?";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}
$query .= " ORDER BY created_at DESC";

$stmt = $mysqli->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users_result = $stmt->get_result();

?>

<div class="container-fluid">
    <h1>User Management</h1>

    <div class="card">
        <form action="users.php" method="GET" class="search-form">
            <input type="text" name="search" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
    </div>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Credits</th>
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
                    <td><?php echo ucfirst($user['role']); ?></td>
                    <td><?php echo ucfirst($user['status']); ?></td>
                    <td><?php echo number_format($user['credit_balance'], 2); ?></td>
                    <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                    <td>
                        <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                        <?php if ($user['role'] !== 'admin'): ?>
                        <a href="users.php?action=login_as&user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning">Login As</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once 'includes/footer_admin.php';
?>
