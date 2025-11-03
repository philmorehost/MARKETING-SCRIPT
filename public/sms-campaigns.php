<?php
session_start();
require_once '../config/db.php';
require_once '../src/lib/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$message = '';

// Fetch user's contact lists
$lists_result = $mysqli->prepare("SELECT id, list_name FROM contact_lists WHERE user_id = ?");
$lists_result->bind_param('i', $user_id);
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
            $mysqli->query("UPDATE users SET credit_balance = credit_balance - $total_cost WHERE id = $user_id");

            $stmt = $mysqli->prepare("INSERT INTO sms_campaigns (user_id, sender_id, message_body, list_ids_json, total_pages, cost_in_credits, status) VALUES (?, ?, ?, ?, ?, ?, 'queued')");
            $list_id_json = json_encode([$list_id]);
            $stmt->bind_param('isssids', $user_id, $sender_id, $sms_body, $list_id_json, $page_count, $total_cost);
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

            <form id="sms-form" action="sms-campaigns.php" method="post">
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
                    <textarea id="message" name="message" rows="5" required></textarea>
                    <div id="sms-counter">Characters: 0, Pages: 1, Credits: 0</div>
                </div>
                <button type="submit">Send Campaign</button>
            </form>
        </main>
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
    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
