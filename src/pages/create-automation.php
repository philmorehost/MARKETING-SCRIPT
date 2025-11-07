<?php
// src/pages/create-automation.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "Create Automation";

// Fetch lists and email templates
$team_id_condition = $user['team_id'] ? "team_id = " . $user['team_id'] : "user_id = " . $user['id'];
$lists = $mysqli->query("SELECT id, list_name FROM contact_lists WHERE $team_id_condition")->fetch_all(MYSQLI_ASSOC);
$emails = $mysqli->query("SELECT id, subject FROM campaigns WHERE $team_id_condition AND status = 'draft'")->fetch_all(MYSQLI_ASSOC); // Use drafts as templates


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $list_id = $_POST['trigger_list_id'] ?? null;
    $steps = $_POST['steps'] ?? [];

    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("INSERT INTO automations (user_id, team_id, name, trigger_list_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisi", $user['id'], $user['team_id'], $name, $list_id);
        $stmt->execute();
        $automation_id = $stmt->insert_id;

        $step_stmt = $mysqli->prepare("INSERT INTO automation_steps (automation_id, type, email_campaign_id_template, wait_days, step_order) VALUES (?, ?, ?, ?, ?)");
        foreach ($steps as $order => $step) {
            $type = $step['type'];
            $email_id = ($type === 'send_email') ? $step['email_id'] : null;
            $wait_days = ($type === 'wait') ? $step['days'] : null;
            $step_stmt->bind_param("isiii", $automation_id, $type, $email_id, $wait_days, $order);
            $step_stmt->execute();
        }
        $mysqli->commit();
        header('Location: /automations');
        exit;
    } catch (Exception $e) {
        $mysqli->rollback();
    }
}


include __DIR__ . '/../includes/header_app.php';
?>
<div class="container app-content">
    <h1>Create Automation</h1>
    <div class="card">
        <form method="POST">
            <div class="form-group">
                <label>Automation Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Trigger</label>
                <select name="trigger_list_id" class="form-control" required>
                    <option>When a contact is added to...</option>
                    <?php foreach ($lists as $list): ?>
                    <option value="<?php echo $list['id']; ?>"><?php echo $list['list_name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <hr>
            <h3>Automation Steps</h3>
            <div id="steps-container"></div>
            <button type="button" class="btn btn-secondary" onclick="addStep()">Add Step</button>
            <hr>

            <button type="submit" class="btn btn-primary">Save Automation</button>
        </form>
    </div>
</div>
<script>
let stepCounter = 0;
function addStep() {
    const container = document.getElementById('steps-container');
    const stepHtml = `
        <div class="automation-step">
            <select name="steps[${stepCounter}][type]" onchange="updateStep(this, ${stepCounter})">
                <option value="send_email">Send Email</option>
                <option value="wait">Wait</option>
            </select>
            <div id="step-options-${stepCounter}">
                 <select name="steps[${stepCounter}][email_id]">
                    <?php foreach ($emails as $email) { echo "<option value='{$email['id']}'>{$email['subject']}</option>"; } ?>
                 </select>
            </div>
        </div>`;
    container.insertAdjacentHTML('beforeend', stepHtml);
    stepCounter++;
}

function updateStep(select, counter) {
    const optionsDiv = document.getElementById('step-options-' + counter);
    if(select.value === 'wait') {
        optionsDiv.innerHTML = '<input type="number" name="steps['+counter+'][days]" placeholder="Days to wait">';
    } else {
        optionsDiv.innerHTML = `
            <select name="steps[${counter}][email_id]">
                <?php foreach ($emails as $email) { echo "<option value='{$email['id']}'>{$email['subject']}</option>"; } ?>
            </select>`;
    }
}
</script>
<?php
include __DIR__ . '/../includes/footer_app.php';
?>
