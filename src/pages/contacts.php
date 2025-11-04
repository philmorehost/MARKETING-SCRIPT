<?php
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id']; // Assuming team_id is stored in session
$message = '';

// Handle Create New List
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_list'])) {
    $list_name = trim($_POST['list_name'] ?? '');
    if (!empty($list_name)) {
        $stmt = $mysqli->prepare("INSERT INTO contact_lists (user_id, team_id, list_name) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $user_id, $team_id, $list_name);
        if ($stmt->execute()) {
            $message = "List '{$list_name}' created successfully!";
        } else {
            $message = "Error creating list.";
        }
    } else {
        $message = "List name cannot be empty.";
    }
}

// Handle Delete List
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_list'])) {
    $list_id = (int)$_POST['list_id'];
    // Ensure the list belongs to the team before deleting
    $stmt = $mysqli->prepare("DELETE FROM contact_lists WHERE id = ? AND team_id = ?");
    $stmt->bind_param('ii', $list_id, $team_id);
    $stmt->execute();
    $message = "List deleted.";
}

// Handle Rename List
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_list'])) {
    $list_id = (int)$_POST['list_id'];
    $new_name = trim($_POST['new_list_name'] ?? '');
    if (!empty($new_name)) {
        $stmt = $mysqli->prepare("UPDATE contact_lists SET list_name = ? WHERE id = ? AND team_id = ?");
        $stmt->bind_param('sii', $new_name, $list_id, $team_id);
        $stmt->execute();
        $message = "List renamed successfully.";
    } else {
        $message = "New list name cannot be empty.";
    }
}


// Fetch all contact lists for the team
$lists_result = $mysqli->prepare("SELECT id, list_name, (SELECT COUNT(*) FROM contact_list_map WHERE list_id = contact_lists.id) as contact_count FROM contact_lists WHERE team_id = ? ORDER BY id DESC");
$lists_result->bind_param('i', $team_id);
$lists_result->execute();
$lists = $lists_result->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contact Lists</title>
    <link rel="stylesheet" href="css/dashboard_style.css">
</head>
<body>
    <?php include APP_ROOT . '/public/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar">
            <?php include APP_ROOT . '/public/includes/sidebar.php'; ?>
        </aside>
        <main class="main-content">
            <h1>Contact Lists</h1>
            <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>

            <div class="create-list-form">
                <h2>Create a New List</h2>
                <form action="/public/contacts" method="post">
                    <input type="text" name="list_name" placeholder="Enter new list name" required>
                    <button type="submit" name="create_list">Create List</button>
                </form>
            </div>

            <hr>

            <h2>Your Lists</h2>
            <table>
                <thead>
                    <tr>
                        <th>List Name</th>
                        <th>Contacts</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($list = $lists->fetch_assoc()): ?>
                    <tr>
                        <td><a href="view-list.php?id=<?php echo $list['id']; ?>"><?php echo htmlspecialchars($list['list_name']); ?></a></td>
                        <td><?php echo $list['contact_count']; ?></td>
                        <td>
                             <a href="view-list.php?id=<?php echo $list['id']; ?>" class="button-primary">View/Manage</a>
                             <button class="button-secondary" onclick="openRenameModal(<?php echo $list['id']; ?>, '<?php echo htmlspecialchars($list['list_name'], ENT_QUOTES); ?>')">Rename</button>
                             <form action="/public/contacts" method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this list?');">
                                <input type="hidden" name="list_id" value="<?php echo $list['id']; ?>">
                                <button type="submit" name="delete_list" class="button-danger">Delete</button>
                             </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

        </main>
    </div>

    <!-- Rename Modal -->
    <div id="rename-modal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <h2>Rename List</h2>
            <form action="/public/contacts" method="post">
                <input type="hidden" name="rename_list" value="1">
                <input type="hidden" id="rename-list-id" name="list_id">
                <div class="form-group">
                    <label for="new-list-name">New List Name</label>
                    <input type="text" id="new-list-name" name="new_list_name" required>
                </div>
                <button type="submit">Save Changes</button>
                <button type="button" onclick="closeRenameModal()">Cancel</button>
            </form>
        </div>
    </div>

    <?php include APP_ROOT . '/public/includes/footer.php'; ?>

    <script>
    function openRenameModal(listId, listName) {
        document.getElementById('rename-list-id').value = listId;
        document.getElementById('new-list-name').value = listName;
        document.getElementById('rename-modal').style.display = 'flex';
    }

    function closeRenameModal() {
        document.getElementById('rename-modal').style.display = 'none';
    }
    </script>
</body>
</html>
