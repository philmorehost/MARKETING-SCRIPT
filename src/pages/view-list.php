<?php
// src/pages/view-list.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$list_id = $_GET['id'] ?? null;
if (!$list_id) {
    header('Location: /contact-lists');
    exit;
}

// Verify the user owns this list
$team_id_condition = $user['team_id'] ? "team_id = " . $user['team_id'] : "user_id = " . $user['id'];
$list_stmt = $mysqli->prepare("SELECT * FROM contact_lists WHERE id = ? AND ($team_id_condition)");
$list_stmt->bind_param("i", $list_id);
$list_stmt->execute();
$list = $list_stmt->get_result()->fetch_assoc();

if (!$list) {
    // User does not own this list or list does not exist
    header('Location: /contact-lists');
    exit;
}

$page_title = "View List: " . htmlspecialchars($list['list_name']);

// Fetch contacts in this list
$contacts_query = $mysqli->prepare("
    SELECT c.*
    FROM contacts c
    JOIN contact_list_map cm ON c.id = cm.contact_id
    WHERE cm.list_id = ?
    ORDER BY c.created_at DESC
");
$contacts_query->bind_param("i", $list_id);
$contacts_query->execute();
$contacts = $contacts_query->get_result()->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header_app.php';
?>

<div class="container app-content">
    <a href="/contact-lists">&larr; Back to all lists</a>
    <h1><?php echo htmlspecialchars($list['list_name']); ?></h1>

    <div class="card">
         <div class="page-header">
            <h3>Contacts (<?php echo count($contacts); ?>)</h3>
            <a href="/import-contacts?list_id=<?php echo $list_id; ?>" class="btn btn-primary">Import Contacts</a>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Name</th>
                    <th>Phone Number</th>
                    <th>Added On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($contacts)): ?>
                    <tr><td colspan="5">This list has no contacts yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($contacts as $contact): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($contact['email']); ?></td>
                        <td><?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($contact['phone_number']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($contact['created_at'])); ?></td>
                        <td>
                            <a href="#" class="btn btn-sm btn-info">Edit</a>
                            <a href="#" class="btn btn-sm btn-danger">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer_app.php';
?>
