<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /public/login');
    exit;
}
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];
$automation_id = (int)($_GET['id'] ?? 0);
if ($automation_id === 0) {
    header('Location: /public/automations');
    exit;
}
$message = '';

// Verify ownership & get automation details
$stmt = $mysqli->prepare("SELECT * FROM automations WHERE id = ? AND team_id = ?");
$stmt->bind_param('ii', $automation_id, $team_id);
$stmt->execute();
$automation = $stmt->get_result()->fetch_assoc();
if (!$automation) { header('Location: /public/automations'); exit; }

// Fetch email campaigns to use as templates
$campaigns_result = $mysqli->prepare("SELECT id, subject FROM campaigns WHERE team_id = ? AND status != 'sent' ORDER BY created_at DESC"); // Only use drafts/templates
$campaigns_result->bind_param('i', $team_id);
$campaigns_result->execute();
$email_templates = $campaigns_result->get_result();

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_step') {
        $type = $_POST['type'] ?? '';
        if ($type === 'wait') {
            $wait_days = (int)$_POST['wait_days'];
            if ($wait_days > 0) {
                $stmt = $mysqli->prepare("INSERT INTO automation_steps (automation_id, type, wait_days) VALUES (?, 'wait', ?)");
                $stmt->bind_param('ii', $automation_id, $wait_days);
                $stmt->execute(); $message = "Wait step added.";
            }
        } elseif ($type === 'send_email') {
            $email_template_id = (int)$_POST['email_template_id'];
            if ($email_template_id > 0) {
                $stmt = $mysqli->prepare("INSERT INTO automation_steps (automation_id, type, email_campaign_id_template) VALUES (?, 'send_email', ?)");
                $stmt->bind_param('ii', $automation_id, $email_template_id);
                $stmt->execute(); $message = "Email step added.";
            }
        }
    } elseif ($action === 'delete_step') {
        $step_id = (int)($_POST['step_id'] ?? 0);
        $stmt = $mysqli->prepare("DELETE FROM automation_steps WHERE id = ? AND automation_id = ?");
        $stmt->bind_param('ii', $step_id, $automation_id);
        $stmt->execute(); $message = "Step deleted.";
    } elseif ($action === 'toggle_status') {
        $new_status = $automation['status'] === 'active' ? 'paused' : 'active';
        $stmt = $mysqli->prepare("UPDATE automations SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $new_status, $automation_id);
        $stmt->execute();
        $automation['status'] = $new_status; // Update for display
        $message = "Automation status updated.";
    }
    header("Location: /public/view-automation?id=" . $automation_id); exit;
}

// Fetch steps
$steps_result = $mysqli->prepare("SELECT s.*, c.subject as email_subject FROM automation_steps s LEFT JOIN campaigns c ON s.email_campaign_id_template = c.id WHERE s.automation_id = ? ORDER BY s.id ASC");
$steps_result->bind_param('i', $automation_id);
$steps_result->execute();
$steps = $steps_result->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Edit Automation</title><link rel="stylesheet" href="/public/css/dashboard_style.css"></head>
<body>
    <?php include APP_ROOT . '/public/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <a href="/public/automations" class="back-link">&larr; Back to Automations</a>
            <h1><?php echo htmlspecialchars($automation['name']); ?></h1>
            <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>
            <div class="card">
                <form action="/public/view-automation?id=<?php echo $automation_id; ?>" method="post">
                    <input type="hidden" name="action" value="toggle_status">
                    <strong>Status:</strong> <?php echo ucfirst($automation['status']); ?>
                    <button type="submit"><?php echo $automation['status'] === 'active' ? 'Pause' : 'Activate'; ?></button>
                </form>
            </div>
            <h2>Steps</h2>
            <div class="automation-steps">
                <?php if($steps->num_rows > 0): ?>
                    <ol>
                    <?php while($step = $steps->fetch_assoc()): ?>
                        <li>
                            <div class="step-desc">
                                <?php if ($step['type'] === 'wait'): ?>
                                    <strong>Wait</strong> for <?php echo $step['wait_days']; ?> day(s)
                                <?php elseif ($step['type'] === 'send_email'): ?>
                                    <strong>Send email:</strong> "<?php echo htmlspecialchars($step['email_subject'] ?? 'Template not found'); ?>"
                                <?php endif; ?>
                            </div>
                            <form action="/public/view-automation?id=<?php echo $automation_id; ?>" method="post" class="delete-form">
                                <input type="hidden" name="action" value="delete_step">
                                <input type="hidden" name="step_id" value="<?php echo $step['id']; ?>">
                                <button type="submit" class="danger" onclick="return confirm('Delete this step?')">&times;</button>
                            </form>
                        </li>
                    <?php endwhile; ?>
                    </ol>
                <?php else: ?>
                    <p>No steps in this automation yet.</p>
                <?php endif; ?>
            </div>
            <hr>
            <div class="card">
                <h2>Add a Step</h2>
                <div id="step-forms">
                    <h3>Wait Step</h3>
                    <form action="/public/view-automation?id=<?php echo $automation_id; ?>" method="post">
                        <input type="hidden" name="action" value="add_step">
                        <input type="hidden" name="type" value="wait">
                        <div class="form-group"><label>Days to wait:</label><input type="number" name="wait_days" min="1" value="1" required></div>
                        <button type="submit">Add Wait Step</button>
                    </form>
                    <hr>
                    <h3>Send Email Step</h3>
                    <form action="/public/view-automation?id=<?php echo $automation_id; ?>" method="post">
                        <input type="hidden" name="action" value="add_step">
                        <input type="hidden" name="type" value="send_email">
                        <div class="form-group">
                            <label>Select Email Template:</label>
                            <select name="email_template_id" required>
                                <option value="">-- Select an Email --</option>
                                <?php while($tpl = $email_templates->fetch_assoc()): ?>
                                <option value="<?php echo $tpl['id']; ?>"><?php echo htmlspecialchars($tpl['subject']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit">Add Email Step</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <?php include APP_ROOT . '/public/includes/footer.php'; ?>
</body>
</html>
