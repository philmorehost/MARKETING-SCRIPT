<?php
// src/pages/automations.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "Automations";

// Fetch automations
$team_id_condition = $user['team_id'] ? "team_id = " . $user['team_id'] : "user_id = " . $user['id'];
$automations_query = $mysqli->query("
    SELECT a.*, cl.list_name
    FROM automations a
    JOIN contact_lists cl ON a.trigger_list_id = cl.id
    WHERE a.$team_id_condition
");
$automations = $automations_query->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header_app.php';
?>
<div class="container app-content">
    <div class="page-header">
        <h1>Automations</h1>
        <a href="/create-automation" class="btn btn-primary">Create New Automation</a>
    </div>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>Automation Name</th>
                    <th>Trigger</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($automations)): ?>
                    <tr><td colspan="4">You have no automations.</td></tr>
                <?php else: foreach ($automations as $automation): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($automation['name']); ?></td>
                        <td>When a contact is added to "<?php echo htmlspecialchars($automation['list_name']); ?>"</td>
                        <td><?php echo ucfirst($automation['status']); ?></td>
                        <td>
                            <a href="/edit-automation?id=<?php echo $automation['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
include __DIR__ . '/../includes/footer_app.php';
?>
