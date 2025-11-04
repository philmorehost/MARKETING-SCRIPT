<?php
// (Existing PHP code remains the same)
require_once APP_ROOT . '/config/db.php';
require_once APP_ROOT . '/src/lib/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];
$message = '';
$lists_result = $mysqli->prepare("SELECT id, list_name, (SELECT COUNT(contact_id) FROM contact_list_map WHERE list_id = contact_lists.id) as subscriber_count FROM contact_lists WHERE team_id = ?");
$lists_result->bind_param('i', $team_id);
$lists_result->execute();
$lists = $lists_result->get_result();
$price_per_sms_page = (float)get_setting('price_per_sms_page', $mysqli, 1);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_sms'])) {
    $sender_id = trim($_POST['sender_id'] ?? '');
    $list_id = (int)($_POST['list_id'] ?? 0);
    $sms_body = trim($_POST['message'] ?? '');
    if (empty($sender_id) || empty($list_id) || empty($sms_body)) {
        $message = "Sender ID, a contact list, and a message are required.";
    } else {
        $stmt_contacts = $mysqli->prepare("SELECT COUNT(*) as total FROM contacts c JOIN contact_list_map clm ON c.id = clm.contact_id WHERE clm.list_id = ? AND c.phone_number IS NOT NULL AND c.phone_number != ''");
        $stmt_contacts->bind_param('i', $list_id);
        $stmt_contacts->execute();
        $total_recipients = (int)$stmt_contacts->get_result()->fetch_assoc()['total'];
        if ($total_recipients > 0) {
            $page_count = ceil(strlen($sms_body) / 160);
            $total_cost = $total_recipients * $page_count * $price_per_sms_page;
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
                    $stmt_campaign = $mysqli->prepare("INSERT INTO sms_campaigns (user_id, team_id, sender_id, message_body, list_ids_json, total_pages, cost_in_credits, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'queued')");
                    $list_id_json = json_encode([$list_id]);
                    $stmt_campaign->bind_param('iisssids', $user_id, $team_id, $sender_id, $sms_body, $list_id_json, $page_count, $total_cost);
                    $stmt_campaign->execute();
                    $campaign_id = $stmt_campaign->insert_id;
                    $contacts_stmt = $mysqli->prepare("SELECT c.phone_number, c.id FROM contacts c JOIN contact_list_map clm ON c.id = clm.contact_id WHERE clm.list_id = ? AND c.phone_number IS NOT NULL AND c.phone_number != ''");
                    $contacts_stmt->bind_param('i', $list_id);
                    $contacts_stmt->execute();
                    $contacts = $contacts_stmt->get_result();
                    $queue_stmt = $mysqli->prepare("INSERT INTO sms_queue (sms_campaign_id, contact_id, phone_number, message_pages) VALUES (?, ?, ?, ?)");
                    while($contact = $contacts->fetch_assoc()){
                         $queue_stmt->bind_param('iisi', $campaign_id, $contact['id'], $contact['phone_number'], $page_count);
                         $queue_stmt->execute();
                    }
                    $mysqli->commit();
                    $message = "Campaign queued successfully! It will be sent shortly.";
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
<head><title>Bulk SMS Service</title><link rel="stylesheet" href="/css/dashboard_style.css"></head>
<body>
    <?php include APP_ROOT . '/public/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Bulk SMS Service</h1>
            <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>
            <div class="card">
                <form id="sms-form" action="/sms-campaigns" method="post">
                    <input type="hidden" name="send_sms" value="1">
                    <div class="form-group">
                        <label for="sender_id">Sender ID (max 11 chars)</label>
                        <input type="text" id="sender_id" name="sender_id" maxlength="11" required>
                    </div>
                    <div class="form-group">
                        <label for="list_id">Select Contact List</label>
                        <select id="list_id" name="list_id" required>
                            <option value="">-- Select a List --</option>
                            <?php $lists->data_seek(0); while($list = $lists->fetch_assoc()): ?>
                            <option value="<?php echo $list['id']; ?>" data-subscribers="<?php echo $list['subscriber_count']; ?>"><?php echo htmlspecialchars($list['list_name']); ?> (<?php echo $list['subscriber_count']; ?> contacts)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="message">Message</label>
                        <div class="ai-helper-container"><button type="button" class="button-ai" onclick="openAiModal()">AI Helper</button></div>
                        <textarea id="message" name="message" rows="5" required></textarea>
                        <div id="sms-counter">Characters: 0 | Pages: 1</div>
                        <div id="cost-estimator">Estimated Cost: 0.0000 Credits</div>
                    </div>
                    <button type="submit">Queue SMS Campaign</button>
                </form>
            </div>
        </main>
    </div>

    <!-- AI Modal -->
    <div id="ai-modal" class="modal-backdrop" style="display:none;" onclick="closeAiModal(event)">
        <div class="modal-content">
            <h2>AI Content Helper</h2>
            <textarea id="ai-prompt" rows="4" placeholder="Enter your prompt... e.g., 'Write a short, exciting SMS message for a 50% off flash sale on shoes'"></textarea>
            <div id="ai-result" class="ai-result-box"></div>
            <div class="modal-actions">
                <button type="button" onclick="generateContent()">Generate</button>
                <button type="button" onclick="insertContent()">Insert & Close</button>
                <button type="button" class="cancel" onclick="closeAiModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        const messageInput = document.getElementById('message');
        const listSelect = document.getElementById('list_id');
        const counterDiv = document.getElementById('sms-counter');
        const costDiv = document.getElementById('cost-estimator');
        const pricePerPage = <?php echo $price_per_sms_page; ?>;

        function updateCost() {
            const charCount = messageInput.value.length;
            const pageCount = charCount === 0 ? 1 : Math.ceil(charCount / 160);
            const selectedOption = listSelect.options[listSelect.selectedIndex];
            const subscribers = parseInt(selectedOption.getAttribute('data-subscribers')) || 0;
            const totalCost = (subscribers * pageCount * pricePerPage).toFixed(4);
            counterDiv.textContent = `Characters: ${charCount} | Pages: ${pageCount}`;
            costDiv.textContent = `Estimated Cost: ${totalCost} Credits`;
        }
        messageInput.addEventListener('input', updateCost);
        listSelect.addEventListener('change', updateCost);
        updateCost();

        function openAiModal() { document.getElementById('ai-modal').style.display = 'flex'; }
        function closeAiModal(event) {
            if (event === undefined || event.target.id === 'ai-modal') {
                 document.getElementById('ai-modal').style.display = 'none';
            }
        }
        function generateContent() {
            const prompt = document.getElementById('ai-prompt').value;
            const resultDiv = document.getElementById('ai-result');
            resultDiv.textContent = 'Generating...';
            fetch('/ai-helper', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'prompt=' + encodeURIComponent(prompt)
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    resultDiv.textContent = 'Error: ' + data.error;
                } else {
                    resultDiv.textContent = data.content;
                }
            })
            .catch(error => resultDiv.textContent = 'An unexpected error occurred.');
        }
        function insertContent() {
            const content = document.getElementById('ai-result').textContent;
            messageInput.value += content;
            document.getElementById('ai-modal').style.display = 'none';
            updateCost(); // Recalculate cost after inserting
        }
    </script>
    <?php include APP_ROOT . '/public/includes/footer.php'; ?>
</body>
</html>
