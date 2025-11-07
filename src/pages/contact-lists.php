<?php
// src/pages/contact-lists.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "Contact Lists";

// --- Logic for creating a new list ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_list') {
    $list_name = trim($_POST['list_name'] ?? '');
    if (!empty($list_name)) {
        $stmt = $mysqli->prepare("INSERT INTO contact_lists (user_id, team_id, list_name) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $user['id'], $user['team_id'], $list_name);
        $stmt->execute();
        header("Location: /contact-lists"); // Refresh to show the new list
        exit;
    }
}

// Fetch user's contact lists
$team_id_condition = $user['team_id'] ? "team_id = " . $user['team_id'] : "user_id = " . $user['id'];
$lists_query = $mysqli->query("
    SELECT cl.id, cl.list_name, cl.created_at, COUNT(cm.contact_id) as contact_count
    FROM contact_lists cl
    LEFT JOIN contact_list_map cm ON cl.id = cm.list_id
    WHERE cl.$team_id_condition
    GROUP BY cl.id
    ORDER BY cl.created_at DESC
");
$lists = $lists_query->fetch_all(MYSQLI_ASSOC);


include __DIR__ . '/../includes/header_app.php';
?>

<div class="container app-content">
    <div class="page-header">
        <h1>Contact Lists</h1>
        <button class="btn btn-primary" onclick="document.getElementById('createListModal').style.display='block'">Create New List</button>
    </div>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>List Name</th>
                    <th>Contacts</th>
                    <th>Created On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lists)): ?>
                    <tr><td colspan="4">You haven't created any contact lists yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($lists as $list): ?>
                    <tr>
                        <td><strong><a href="/view-list?id=<?php echo $list['id']; ?>"><?php echo htmlspecialchars($list['list_name']); ?></a></strong></td>
                        <td><?php echo number_format($list['contact_count']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($list['created_at'])); ?></td>
                        <td>
                            <a href="/view-list?id=<?php echo $list['id']; ?>" class="btn btn-sm btn-info">View</a>
                            <a href="/import-contacts?list_id=<?php echo $list['id']; ?>" class="btn btn-sm btn-success">Import</a>
                            <a href="/delete-list?id=<?php echo $list['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create List Modal -->
<div id="createListModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('createListModal').style.display='none'">&times;</span>
        <h2>Create New Contact List</h2>
        <form action="/contact-lists" method="POST">
            <input type="hidden" name="action" value="create_list">
            <div class="form-group">
                <label for="list_name">List Name</label>
                <input type="text" name="list_name" id="list_name" required>
            </div>
            <button type="submit" class="btn btn-primary">Create List</button>
        </form>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer_app.php';
?>
