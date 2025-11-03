<?php
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];
$message = '';

// Fetch team's contact lists for the trigger
$lists_result = $mysqli->prepare("SELECT id, list_name FROM contact_lists WHERE team_id = ?");
$lists_result->bind_param('i', $team_id);
$lists_result->execute();
$lists = $lists_result->get_result();

// Handle Automation Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_automation'])) {
    $name = trim($_POST['name'] ?? '');
    $trigger_list_id = (int)$_POST['trigger_list_id'];

    if (!empty($name) && $trigger_list_id > 0) {
        $stmt = $mysqli->prepare("INSERT INTO automations (user_id, team_id, name, trigger_list_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iisi', $user_id, $team_id, $name, $trigger_list_id);
        $stmt->execute();
        $message = "Automation created successfully. Now add steps to it.";
    }
}

// Fetch existing automations
$automations_result = $mysqli->prepare("SELECT id, name, trigger_list_id FROM automations WHERE team_id = ?");
$automations_result->bind_param('i', $team_id);
$automations_result->execute();
$automations = $automations_result->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head><title>Automations</title></head>
<body>
    <?php include APP_ROOT . '/public/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Automations</h1>
            <?php if ($message): ?><p><?php echo $message; ?></p><?php endif; ?>

            <h2>Create New Automation</h2>
            <form action="automations.php" method="post">
                <input type="hidden" name="create_automation" value="1">
                <input type="text" name="name" placeholder="Automation Name (e.g., Welcome Series)" required>
                <select name="trigger_list_id" required>
                    <option value="">-- Select Trigger: Contact added to... --</option>
                    <?php while($list = $lists->fetch_assoc()): ?>
                    <option value="<?php echo $list['id']; ?>"><?php echo htmlspecialchars($list['list_name']); ?></option>
                    <?php endwhile; ?>
                </select>
                <button type="submit">Create Automation</button>
            </form>

            <hr>
            <h2>Your Automations</h2>
            <table>
                <thead><tr><th>Name</th><th>Trigger</th><th>Action</th></tr></thead>
                <tbody>
                <?php while ($automation = $automations->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($automation['name']); ?></td>
                        <td>Contact added to List ID <?php echo $automation['trigger_list_id']; ?></td>
                        <td><a href="view-automation.php?id=<?php echo $automation['id']; ?>">View/Edit Steps</a></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </main>
    </div>
    <?php include APP_ROOT . '/public/includes/footer.php'; ?>
</body>
</html>
