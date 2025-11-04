<?php
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
$price_per_email = (float)get_setting('price_per_email_send', $mysqli, 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $subject = trim($_POST['subject'] ?? '');
    $list_id = (int)($_POST['list_id'] ?? 0);
    $html_content = trim($_POST['html_content'] ?? '');

    if (empty($subject) || empty($list_id) || empty($html_content)) {
        $message = "Subject, a contact list, and email content are required.";
    } else {
        $stmt_contacts = $mysqli->prepare("SELECT COUNT(*) as total FROM contacts c JOIN contact_list_map clm ON c.id = clm.contact_id WHERE clm.list_id = ? AND c.email IS NOT NULL AND c.email != ''");
        $stmt_contacts->bind_param('i', $list_id);
        $stmt_contacts->execute();
        $total_recipients = (int)$stmt_contacts->get_result()->fetch_assoc()['total'];

        if ($total_recipients > 0) {
            $total_cost = $total_recipients * $price_per_email;

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

                    $stmt_campaign = $mysqli->prepare("INSERT INTO campaigns (user_id, team_id, subject, html_content, cost_in_credits, status) VALUES (?, ?, ?, ?, ?, 'queued')");
                    $stmt_campaign->bind_param('iisssd', $user_id, $team_id, $subject, $html_content, $total_cost);
                    $stmt_campaign->execute();
                    $campaign_id = $stmt_campaign->insert_id;

                    $contacts_stmt = $mysqli->prepare("SELECT c.email, c.id FROM contacts c JOIN contact_list_map clm ON c.id = clm.contact_id WHERE clm.list_id = ? AND c.email IS NOT NULL AND c.email != ''");
                    $contacts_stmt->bind_param('i', $list_id);
                    $contacts_stmt->execute();
                    $contacts = $contacts_stmt->get_result();

                    $queue_stmt = $mysqli->prepare("INSERT INTO campaign_queue (campaign_id, contact_id, email_address) VALUES (?, ?, ?)");
                    while($contact = $contacts->fetch_assoc()){
                         $queue_stmt->bind_param('iis', $campaign_id, $contact['id'], $contact['email']);
                         $queue_stmt->execute();
                    }

                    $mysqli->commit();
                    $message = "Email campaign queued successfully!";
                } catch (Exception $e) {
                    $mysqli->rollback();
                    $message = "An error occurred: " . $e->getMessage();
                }
            } else {
                $message = "Insufficient credits. You need ".number_format($total_cost, 4)." credits, but you only have ".number_format($user_balance, 4).".";
            }
        } else {
            $message = "The selected contact list has no contacts with valid email addresses.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Email Campaigns</title><link rel="stylesheet" href="/public/css/dashboard_style.css"><script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script></head>
<body>
    <?php include APP_ROOT . '/public_html/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public_html/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Email Campaigns</h1>
            <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>
            <div class="card">
                <form id="email-form" action="/public/email-campaigns" method="post">
                    <input type="hidden" name="send_email" value="1">
                    <div class="form-group"><label for="subject">Subject</label><input type="text" id="subject" name="subject" required></div>
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
                        <label for="html_content">Email Content</label>
                        <div class="ai-helper-container"><button type="button" class="button-ai" onclick="openAiModal()">AI Helper</button></div>
                        <textarea id="html_content" name="html_content" rows="15"></textarea>
                    </div>
                    <div id="cost-estimator">Estimated Cost: 0.0000 Credits</div>
                    <button type="submit">Queue Email Campaign</button>
                </form>
            </div>
        </main>
    </div>
    <div id="ai-modal" class="modal-backdrop" style="display:none;" onclick="closeAiModal(event)"><div class="modal-content"><h2>AI Content Helper</h2><textarea id="ai-prompt" rows="4" placeholder="e.g., 'Write an exciting email about a 50% off flash sale'"></textarea><div id="ai-result" class="ai-result-box"></div><div class="modal-actions"><button type="button" onclick="generateContent()">Generate</button><button type="button" onclick="insertContent()">Insert & Close</button><button type="button" class="cancel" onclick="closeAiModal()">Cancel</button></div></div></div>
    <script>
        tinymce.init({ selector: '#html_content', plugins: 'autolink lists link image charmap print preview hr anchor pagebreak', toolbar_mode: 'floating' });
        const listSelect = document.getElementById('list_id');
        const costDiv = document.getElementById('cost-estimator');
        const pricePerEmail = <?php echo $price_per_email; ?>;
        function updateCost() {
            const selectedOption = listSelect.options[listSelect.selectedIndex];
            const subscribers = parseInt(selectedOption.getAttribute('data-subscribers')) || 0;
            const totalCost = (subscribers * pricePerEmail).toFixed(4);
            costDiv.textContent = `Estimated Cost: ${totalCost} Credits`;
        }
        listSelect.addEventListener('change', updateCost);
        updateCost();
        function openAiModal() { document.getElementById('ai-modal').style.display = 'flex'; }
        function closeAiModal(event) { if (event === undefined || event.target.id === 'ai-modal') { document.getElementById('ai-modal').style.display = 'none'; } }
        function generateContent() {
            const prompt = document.getElementById('ai-prompt').value;
            const resultDiv = document.getElementById('ai-result');
            resultDiv.textContent = 'Generating...';
            fetch('/public/ai-helper', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'prompt=' + encodeURIComponent(prompt) })
            .then(response => response.json())
            .then(data => {
                if (data.error) { resultDiv.textContent = 'Error: ' + data.error; }
                else { resultDiv.textContent = data.content; }
            })
            .catch(error => resultDiv.textContent = 'An unexpected error occurred.');
        }
        function insertContent() {
            const content = document.getElementById('ai-result').textContent;
            tinymce.get('html_content').insertContent(content);
            document.getElementById('ai-modal').style.display = 'none';
        }
    </script>
    <?php include APP_ROOT . '/public_html/includes/footer.php'; ?>
</body>
</html>
