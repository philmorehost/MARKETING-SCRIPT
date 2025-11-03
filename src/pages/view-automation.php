<?php
require_once '../config/db.php';
$user_id = $_SESSION['user_id'] ?? 0;
$team_id = $_SESSION['team_id'] ?? 0;
$automation_id = (int)($_GET['id'] ?? 0);
if ($user_id === 0 || $team_id === 0 || $automation_id === 0) {
    header('Location: login.php');
    exit;
}


// Verify ownership
$stmt = $mysqli->prepare("SELECT name FROM automations WHERE id = ? AND team_id = ?");
$stmt->bind_param('ii', $automation_id, $team_id);
$stmt->execute();
$automation = $stmt->get_result()->fetch_assoc();
if (!$automation) { header('Location: automations.php'); exit; }

// Handle adding a step
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_step'])) {
    $type = $_POST['type'] ?? '';
    if ($type === 'wait') {
        $wait_days = (int)$_POST['wait_days'];
        $stmt = $mysqli->prepare("INSERT INTO automation_steps (automation_id, type, wait_days) VALUES (?, 'wait', ?)");
        $stmt->bind_param('ii', $automation_id, $wait_days);
        $stmt->execute();
    } elseif ($type === 'send_email') {
        // In a real app, you'd select an email template
        $email_template_id = (int)$_POST['email_template_id'];
        $stmt = $mysqli->prepare("INSERT INTO automation_steps (automation_id, type, email_campaign_id_template) VALUES (?, 'send_email', ?)");
        $stmt->bind_param('ii', $automation_id, $email_template_id);
        $stmt->execute();
    }
}

// Fetch steps
$steps_result = $mysqli->prepare("SELECT * FROM automation_steps WHERE automation_id = ? ORDER BY step_order ASC, id ASC");
$steps_result->bind_param('i', $automation_id);
$steps_result->execute();
$steps = $steps_result->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Edit Automation</title></head>
<body>
    <?php include APP_ROOT . '/public/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Editing: <?php echo htmlspecialchars($automation['name']); ?></h1>
            <a href="automations.php">&larr; Back</a>

            <h2>Steps</h2>
            <ol>
                <?php while($step = $steps->fetch_assoc()): ?>
                <li>
                    <?php if ($step['type'] === 'wait'): ?>
                        Wait for <?php echo $step['wait_days']; ?> day(s)
                    <?php elseif ($step['type'] === 'send_email'): ?>
                        Send email template #<?php echo $step['email_campaign_id_template']; ?>
                    <?php endif; ?>
                </li>
                <?php endwhile; ?>
            </ol>

            <hr>
            <h2>Add a Step</h2>
            <form action="" method="post">
                <input type="hidden" name="add_step" value="1">
                <select name="type">
                    <option value="wait">Wait</option>
                    <option value="send_email">Send Email</option>
                </select>
                <input type="number" name="wait_days" placeholder="Days to wait">
                <input type="number" name="email_template_id" placeholder="Email Template ID">
                <button type="submit">Add Step</button>
            </form>
        </main>
    </div>
    <?php include APP_ROOT . '/public/includes/footer.php'; ?>
</body>
</html>
