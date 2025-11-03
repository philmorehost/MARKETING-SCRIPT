<?php
require_once '../config/db.php';
require_once '../src/lib/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /public/login');
    exit;
}
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];
$message = '';

// Fetch team's contact lists and their available columns for mapping
$lists_result = $mysqli->prepare("SELECT id, list_name, (SELECT COUNT(contact_id) FROM contact_list_map WHERE list_id = contact_lists.id) as subscriber_count FROM contact_lists WHERE team_id = ?");
$lists_result->bind_param('i', $team_id);
$lists_result->execute();
$lists = $lists_result->get_result();
// A simplified list of contact fields for the dropdown. In a real app, this could be dynamic.
$contact_fields = ['first_name', 'last_name', 'email', 'phone_number'];


// Fetch WhatsApp cost from settings
$price_per_whatsapp = (float)get_setting('price_per_whatsapp', $mysqli, 1);

// Handle Campaign Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_whatsapp'])) {
    $template_name = trim($_POST['template_name'] ?? '');
    $list_id = (int)($_POST['list_id'] ?? 0);
    $template_params = $_POST['template_params'] ?? []; // Now an array

    if (empty($template_name) || empty($list_id)) {
        $message = "A template name and contact list are required.";
    } else {
        $stmt_contacts = $mysqli->prepare("SELECT COUNT(*) as total FROM contacts c JOIN contact_list_map clm ON c.id = clm.contact_id WHERE clm.list_id = ? AND c.phone_number IS NOT NULL AND c.phone_number != ''");
        $stmt_contacts->bind_param('i', $list_id);
        $stmt_contacts->execute();
        $total_recipients = (int)$stmt_contacts->get_result()->fetch_assoc()['total'];

        if ($total_recipients > 0) {
            $total_cost = $total_recipients * $price_per_whatsapp;

            $stmt_balance = $mysqli->prepare("SELECT credit_balance FROM users WHERE id = ?");
            $stmt_balance->bind_param('i', $_SESSION['team_owner_id']);
            $stmt_balance->execute();
            $user_balance = (float)$stmt_balance->get_result()->fetch_assoc()['credit_balance'];

            if ($user_balance >= $total_cost) {
                $mysqli->begin_transaction();
                try {
                    $update_credits_stmt = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance - ? WHERE id = ?");
                    $update_credits_stmt->bind_param('di', $total_cost, $_SESSION['team_owner_id']);
                    $update_credits_stmt->execute();

                    $params_json = json_encode($template_params);
                    $list_id_json = json_encode([$list_id]);
                    $stmt_campaign = $mysqli->prepare("INSERT INTO whatsapp_campaigns (user_id, team_id, template_name, template_params_json, list_ids_json, cost_in_credits, status) VALUES (?, ?, ?, ?, ?, ?, 'queued')");
                    $stmt_campaign->bind_param('iisssd', $user_id, $team_id, $template_name, $params_json, $list_id_json, $total_cost);
                    $stmt_campaign->execute();
                    $campaign_id = $stmt_campaign->insert_id;

                    $contacts_stmt = $mysqli->prepare("SELECT c.phone_number, c.id FROM contacts c JOIN contact_list_map clm ON c.id = clm.contact_id WHERE clm.list_id = ? AND c.phone_number IS NOT NULL AND c.phone_number != ''");
                    $contacts_stmt->bind_param('i', $list_id);
                    $contacts_stmt->execute();
                    $contacts = $contacts_stmt->get_result();

                    $queue_stmt = $mysqli->prepare("INSERT INTO whatsapp_queue (campaign_id, contact_id, phone_number) VALUES (?, ?, ?)");
                    while($contact = $contacts->fetch_assoc()){
                         $queue_stmt->bind_param('iis', $campaign_id, $contact['id'], $contact['phone_number']);
                         $queue_stmt->execute();
                    }

                    $mysqli->commit();
                    $message = "WhatsApp campaign queued successfully!";
                } catch (Exception $e) {
                    $mysqli->rollback();
                    $message = "An error occurred: " . $e->getMessage();
                }
            } else {
                 $message = "Insufficient credits. You need ".number_format($total_cost, 4)." credits, but you only have ".number_format($user_balance, 4).".";
            }
        } else {
            $message = "The selected contact list has no contacts with valid phone numbers.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Bulk WhatsApp Service</title><link rel="stylesheet" href="/public/css/dashboard_style.css"></head>
<body>
    <?php include APP_ROOT . '/public/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Bulk WhatsApp Service</h1>
            <p class="warning"><strong>Important:</strong> You may only send messages using pre-approved Meta/Gupshup templates. Failure to do so may result in your account being blocked.</p>
            <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>

            <div class="card">
                <form action="/public/whatsapp-campaigns" method="post">
                    <input type="hidden" name="send_whatsapp" value="1">
                    <div class="form-group">
                        <label for="list_id">Select Contact List</label>
                        <select id="list_id" name="list_id" required>
                            <option value="">-- Select a List --</option>
                            <?php while($list = $lists->fetch_assoc()): ?>
                            <option value="<?php echo $list['id']; ?>" data-subscribers="<?php echo $list['subscriber_count']; ?>"><?php echo htmlspecialchars($list['list_name']); ?> (<?php echo $list['subscriber_count']; ?> contacts)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="template_name">Message Template Name/ID</label>
                        <input type="text" id="template_name" name="template_name" placeholder="e.g., sale_alert_v2" required>
                    </div>
                    <div class="form-group">
                        <label>Template Placeholders Mapping</label>
                        <div id="placeholders">
                            <!-- JS will add fields here -->
                            <p>Map template variables (e.g., {{1}}) to your contact fields.</p>
                            <button type="button" onclick="addPlaceholder()">Add Placeholder</button>
                        </div>
                    </div>
                     <div id="cost-estimator">Estimated Cost: 0.0000 Credits</div>
                    <button type="submit">Queue WhatsApp Campaign</button>
                </form>
            </div>
        </main>
    </div>
    <script>
        let placeholderCount = 0;

        function addPlaceholder() {
            placeholderCount++;
            const container = document.getElementById('placeholders');
            const newPlaceholder = document.createElement('div');
            newPlaceholder.className = 'placeholder-row';
            newPlaceholder.innerHTML = `
                <label for="param_${placeholderCount}">Variable {{${placeholderCount}}}</label>
                <select id="param_${placeholderCount}" name="template_params[${placeholderCount}]">
                    <?php foreach($contact_fields as $field): ?>
                    <option value="<?php echo $field; ?>"><?php echo ucfirst(str_replace('_', ' ', $field)); ?></option>
                    <?php endforeach; ?>
                </select>
            `;
            container.insertBefore(newPlaceholder, container.lastElementChild);
        }

        const listSelect = document.getElementById('list_id');
        const costDiv = document.getElementById('cost-estimator');
        const pricePerMessage = <?php echo $price_per_whatsapp; ?>;

        function updateCost() {
            const selectedOption = listSelect.options[listSelect.selectedIndex];
            const subscribers = parseInt(selectedOption.getAttribute('data-subscribers')) || 0;
            const totalCost = (subscribers * pricePerMessage).toFixed(4);
            costDiv.textContent = `Estimated Cost: ${totalCost} Credits`;
        }

        listSelect.addEventListener('change', updateCost);
        updateCost(); // Initial calculation
    </script>
    <?php include APP_ROOT . '/public/includes/footer.php'; ?>
</body>
</html>
