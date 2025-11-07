<?php
// src/pages/whatsapp-campaigns.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "Bulk WhatsApp Campaigns";
$cost_per_whatsapp = get_setting('price_per_whatsapp', 10);

include __DIR__ . '/../includes/header_app.php';
?>

<div class="container app-content">
    <h1>Bulk WhatsApp Service</h1>
     <div class="alert alert-warning">
        <strong>Important:</strong> WhatsApp campaigns require pre-approved message templates. You can only message contacts who have opted in to receive messages from you.
    </div>

    <div class="card">
        <form action="/send-whatsapp-campaign" method="POST">
             <div class="form-group">
                <label for="template_name">Message Template Name/ID</label>
                <input type="text" name="template_name" id="template_name" class="form-control" required placeholder="e.g., sale_alert_v2">
            </div>

            <div class="form-group">
                <label>Template Placeholders</label>
                <p class="form-hint">Map template variables like {{1}} or {{name}} to your contact list fields.</p>
                <div id="placeholder-mapping">
                    <!-- JS will add mapping fields here -->
                </div>
                <button type="button" class="btn btn-sm btn-secondary" onclick="addPlaceholder()">Add Placeholder</button>
            </div>

             <div class="form-group">
                <label>Recipient List</label>
                <select name="recipient_list" class="form-control" required>
                    <option value="">-- Select a Contact List --</option>
                    <?php
                        $team_id_condition = $user['team_id'] ? "team_id = " . $user['team_id'] : "user_id = " . $user['id'];
                        $lists = $mysqli->query("SELECT id, list_name FROM contact_lists WHERE $team_id_condition");
                        while($list = $lists->fetch_assoc()) {
                            echo "<option value='{$list['id']}'>{$list['list_name']}</option>";
                        }
                    ?>
                </select>
            </div>

             <button type="submit" class="btn btn-success">Queue WhatsApp Campaign</button>
        </form>
    </div>
</div>

<script>
function addPlaceholder() {
    const container = document.getElementById('placeholder-mapping');
    const newPlaceholder = document.createElement('div');
    newPlaceholder.className = 'placeholder-item';
    newPlaceholder.innerHTML = `
        <input type="text" name="placeholders[key][]" placeholder="Template Var (e.g., {{1}})">
        <select name="placeholders[value][]">
            <option value="first_name">First Name</option>
            <option value="last_name">Last Name</option>
            <option value="email">Email</option>

        </select>
        <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()">Remove</button>
    `;
    container.appendChild(newPlaceholder);
}
</script>

<?php
include __DIR__ . '/../includes/footer_app.php';
?>
