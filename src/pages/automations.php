<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];
$message = '';

// Fetch team's contact lists for the trigger dropdown and for mapping names
$lists_result = $mysqli->prepare("SELECT id, list_name FROM contact_lists WHERE team_id = ?");
$lists_result->bind_param('i', $team_id);
$lists_result->execute();
$lists_query_result = $lists_result->get_result();
$contact_lists = [];
while ($row = $lists_query_result->fetch_assoc()) {
    $contact_lists[$row['id']] = $row['list_name'];
}


// Handle Automation Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_automation'])) {
    $name = trim($_POST['name'] ?? '');
    $trigger_list_id = (int)$_POST['trigger_list_id'];

    if (!empty($name) && $trigger_list_id > 0 && isset($contact_lists[$trigger_list_id])) {
        $stmt = $mysqli->prepare("INSERT INTO automations (user_id, team_id, name, trigger_list_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iisi', $user_id, $team_id, $name, $trigger_list_id);
        if ($stmt->execute()) {
            $new_automation_id = $stmt->insert_id;
            header("Location: /view-automation?id={$new_automation_id}"); // Redirect to edit page
            exit;
        } else {
            $message = "Error: Could not create the automation.";
        }
    } else {
        $message = "Please provide a valid name and trigger list.";
    }
}

// Fetch existing automations
$automations_result = $mysqli->prepare("SELECT id, name, trigger_list_id, status FROM automations WHERE team_id = ? ORDER BY created_at DESC");
$automations_result->bind_param('i', $team_id);
$automations_result->execute();
$automations = $automations_result->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head><title>Automations</title><link rel="stylesheet" href="/css/dashboard_style.css"></head>
<body>
    <?php include APP_ROOT . '/public/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Marketing Automations</h1>
            <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>
            <div class="card">
                <h2>Create New Automation</h2>
                <form action="/automations" method="post">
                    <input type="hidden" name="create_automation" value="1">
                    <div class="form-group"><label for="name">Automation Name</label><input type="text" id="name" name="name" placeholder="e.g., Welcome Series" required></div>
                    <div class="form-group">
                        <label for="trigger_list_id">Trigger</label>
                        <select id="trigger_list_id" name="trigger_list_id" required>
                            <option value="">-- When a contact is added to... --</option>
                            <?php foreach ($contact_lists as $id => $name): ?>
                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit">Create & Add Steps</button>
                </form>
            </div>
            <hr>
            <h2>Your Automations</h2>
            <div class="table-container">
                <table>
                    <thead><tr><th>Name</th><th>Trigger</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if ($automations->num_rows > 0): ?>
                        <?php while ($automation = $automations->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($automation['name']); ?></td>
                            <td>Contact added to "<?php echo htmlspecialchars($contact_lists[$automation['trigger_list_id']] ?? 'Unknown List'); ?>"</td>
                            <td><?php echo htmlspecialchars(ucfirst($automation['status'])); ?></td>
                            <td><a href="/view-automation?id=<?php echo $automation['id']; ?>">View/Edit Steps</a></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4">You haven't created any automations yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <?php include APP_ROOT . '/public/includes/footer.php'; ?>
</body>
</html>
