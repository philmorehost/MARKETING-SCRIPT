<?php
session_start();
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

// Fetch email cost from settings
$price_per_email = (float)get_setting('price_per_email_send', $mysqli, 1);

// Handle Campaign Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $subject = trim($_POST['subject'] ?? '');
    $list_id = (int)($_POST['list_id'] ?? 0);
    $html_content = trim($_POST['html_content'] ?? '');

    // 1. Calculate cost
    $stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM contact_list_map WHERE list_id = ?");
    $stmt->bind_param('i', $list_id);
    $stmt->execute();
    $total_recipients = $stmt->get_result()->fetch_assoc()['total'];
    $total_cost = $total_recipients * $price_per_email;

    // 2. Check user balance
    $stmt = $mysqli->prepare("SELECT credit_balance FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_balance = $stmt->get_result()->fetch_assoc()['credit_balance'];

    if ($user_balance >= $total_cost) {
        // 3. Deduct credits & Queue campaign
        $mysqli->begin_transaction();
        try {
            // Note: Credits are shared by the team, deducted from the owner.
            // A more complex system might track individual contributions.
            $team_owner_id = $_SESSION['team_owner_id']; // Assuming this is in session

            $update_credits_stmt = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance - ? WHERE id = ?");
            $update_credits_stmt->bind_param('di', $total_cost, $team_owner_id);
            $update_credits_stmt->execute();

            $stmt = $mysqli->prepare("INSERT INTO campaigns (user_id, team_id, subject, html_content, cost_in_credits, status) VALUES (?, ?, ?, ?, ?, 'queued')");
            $stmt->bind_param('iisds', $user_id, $team_id, $subject, $html_content, $total_cost);
            $stmt->execute();
            $campaign_id = $stmt->insert_id;

            // 4. Add contacts to campaign_queue
            $contacts_stmt = $mysqli->prepare("SELECT c.email, c.id FROM contacts c JOIN contact_list_map clm ON c.id = clm.contact_id WHERE clm.list_id = ? AND c.email IS NOT NULL");
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
        $message = "Insufficient credits. You need ".number_format($total_cost)." credits, but you only have ".number_format($user_balance).".";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Marketing Campaigns</title>
    <link rel="stylesheet" href="css/dashboard_style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar">
            <?php include 'includes/sidebar.php'; ?>
        </aside>
        <main class="main-content">
            <h1>Email Campaigns</h1>
            <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>

            <form id="email-form" action="email-campaigns.php" method="post">
                <input type="hidden" name="send_email" value="1">
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" required>
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
                    <label for="html_content">Message Body (HTML)</label>
                    <button type="button" onclick="openAiModal()">AI Helper</button>
                    <textarea id="html_content" name="html_content" rows="15" placeholder="Use TinyMCE here in a real build"></textarea>
                    <p>Cost per recipient: <?php echo $price_per_email; ?> credit(s)</p>
                </div>
                <button type="submit">Queue Campaign</button>
            </form>
        </main>
    </div>

    <!-- AI Modal -->
    <div id="ai-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div style="background:#fff; width:500px; margin:100px auto; padding:20px;">
            <h2>AI Content Helper</h2>
            <textarea id="ai-prompt" rows="4" style="width:100%;" placeholder="Enter your prompt... e.g., 'Write a subject line for a 50% off sale'"></textarea>
            <div id="ai-result" style="border:1px solid #ccc; min-height:100px; padding:10px; margin-top:10px;"></div>
            <button onclick="generateContent()">Generate</button>
            <button onclick="insertContent()">Insert & Close</button>
            <button onclick="closeAiModal()">Cancel</button>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
    <script>
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
            document.getElementById('html_content').value += content;
            closeAiModal();
        }
    </script>
</body>
</html>
