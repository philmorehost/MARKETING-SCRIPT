<?php
require_once '../config/db.php';
require_once '../src/lib/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$message = '';

// Fetch team's contact lists
$lists_result = $mysqli->prepare("SELECT id, list_name FROM contact_lists WHERE team_id = ?");
$lists_result->bind_param('i', $team_id);
$lists_result->execute();
$lists = $lists_result->get_result();

// Fetch SMS cost from settings
$price_per_sms_page = (float)get_setting('price_per_sms_page', $mysqli, 5);

// Handle Campaign Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_sms'])) {
    $sender_id = trim($_POST['sender_id'] ?? '');
    $list_id = (int)($_POST['list_id'] ?? 0);
    $sms_body = trim($_POST['message'] ?? '');

    // 1. Calculate cost
    $page_count = ceil(strlen($sms_body) / 160);
    $stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM contact_list_map WHERE list_id = ?");
    $stmt->bind_param('i', $list_id);
    $stmt->execute();
    $total_recipients = $stmt->get_result()->fetch_assoc()['total'];
    $total_cost = $total_recipients * $page_count * $price_per_sms_page;

    // 2. Check user balance
    $stmt = $mysqli->prepare("SELECT credit_balance FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_balance = $stmt->get_result()->fetch_assoc()['credit_balance'];

    if ($user_balance >= $total_cost) {
        // 3. Deduct credits & Queue campaign (Transaction recommended)
        $mysqli->begin_transaction();
        try {
            $team_owner_id = $_SESSION['team_owner_id'];

            $update_credits_stmt = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance - ? WHERE id = ?");
            $update_credits_stmt->bind_param('di', $total_cost, $team_owner_id);
            $update_credits_stmt->execute();

            $stmt = $mysqli->prepare("INSERT INTO sms_campaigns (user_id, team_id, sender_id, message_body, list_ids_json, total_pages, cost_in_credits, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'queued')");
            $list_id_json = json_encode([$list_id]);
            $stmt->bind_param('iisssids', $user_id, $team_id, $sender_id, $sms_body, $list_id_json, $page_count, $total_cost);
            $stmt->execute();
            $campaign_id = $stmt->insert_id;

            // 4. Add contacts to sms_queue
            $contacts_stmt = $mysqli->prepare("SELECT c.phone_number, c.id FROM contacts c JOIN contact_list_map clm ON c.id = clm.contact_id WHERE clm.list_id = ? AND c.phone_number IS NOT NULL");
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
        $message = "Insufficient credits. You need ".number_format($total_cost)." credits, but you only have ".number_format($user_balance).".";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk SMS Service</title>
    <link rel="stylesheet" href="css/dashboard_style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar">
            <?php include 'includes/sidebar.php'; ?>
        </aside>
        <main class="main-content">
            <h1>Bulk SMS Service</h1>
            <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>

            <form id="sms-form" action="/public/sms-campaigns" method="post">
                <input type="hidden" name="send_sms" value="1">
                <div class="form-group">
                    <label for="sender_id">Sender ID (max 11 chars)</label>
                    <input type="text" id="sender_id" name="sender_id" maxlength="11" required>
                </div>
                <div class="form-group">
                    <label for="list_id">Select Contact List</label>
                    <select id="list_id" name="list_id" required>
                        <option value="">-- Select a List --</option>
                        <?php while($list = $lists->fetch_assoc()): ?>
                        <option value="<?php echo $list['id']; ?>"><?php echo htmlspecialchars($list['list_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="message">Message</label>
                    <button type="button" onclick="openAiModal()">AI Helper</button>
                    <textarea id="message" name="message" rows="5" required></textarea>
                    <div id="sms-counter">Characters: 0, Pages: 1, Credits: 0</div>
                </div>
                <button type="submit">Send Campaign</button>
            </form>
        </main>
    </div>

    <!-- AI Modal -->
    <div id="ai-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div style="background:#fff; width:500px; margin:100px auto; padding:20px;">
            <h2>AI Content Helper</h2>
            <textarea id="ai-prompt" rows="4" style="width:100%;" placeholder="Enter your prompt... e.g., 'Write a short SMS message for a flash sale'"></textarea>
            <div id="ai-result" style="border:1px solid #ccc; min-height:100px; padding:10px; margin-top:10px;"></div>
            <button onclick="generateContent()">Generate</button>
            <button onclick="insertContent()">Insert & Close</button>
            <button onclick="closeAiModal()">Cancel</button>
        </div>
    </div>
    <script>
        const messageInput = document.getElementById('message');
        const counterDiv = document.getElementById('sms-counter');
        const pricePerPage = <?php echo $price_per_sms_page; ?>;

        messageInput.addEventListener('input', () => {
            const charCount = messageInput.value.length;
            const pageCount = Math.ceil(charCount / 160);
            const creditCost = pageCount * pricePerPage;
            counterDiv.textContent = `Characters: ${charCount}, Pages: ${pageCount}, Credits per recipient: ${creditCost}`;
        });

        function openAiModal() { document.getElementById('ai-modal').style.display = 'block'; }
        function closeAiModal() { document.getElementById('ai-modal').style.display = 'none'; }
        function generateContent() {
            const prompt = document.getElementById('ai-prompt').value;
            const resultDiv = document.getElementById('ai-result');
            resultDiv.textContent = 'Generating...';

            fetch('ai-helper.php', {
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
            .catch(error => {
                resultDiv.textContent = 'An unexpected error occurred.';
            });
        }
        function insertContent() {
            const content = document.getElementById('ai-result').textContent;
            document.getElementById('message').value += content;
            closeAiModal();
        }
    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
