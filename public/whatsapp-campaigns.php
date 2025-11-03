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

// Fetch WhatsApp cost from settings
$price_per_whatsapp = (float)get_setting('price_per_whatsapp', $mysqli, 10);

// Handle Campaign Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_whatsapp'])) {
    $template_name = trim($_POST['template_name'] ?? '');
    $list_id = (int)($_POST['list_id'] ?? 0);
    $template_params = trim($_POST['template_params'] ?? ''); // e.g., {{1}}=first_name,{{2}}=city

    // 1. Calculate cost
    $stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM contact_list_map WHERE list_id = ?");
    $stmt->bind_param('i', $list_id);
    $stmt->execute();
    $total_recipients = $stmt->get_result()->fetch_assoc()['total'];
    $total_cost = $total_recipients * $price_per_whatsapp;

    // 2. Check user balance
    $stmt = $mysqli->prepare("SELECT credit_balance FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_balance = $stmt->get_result()->fetch_assoc()['credit_balance'];

    if ($user_balance >= $total_cost) {
        $mysqli->begin_transaction();
        try {
            $mysqli->query("UPDATE users SET credit_balance = credit_balance - $total_cost WHERE id = $user_id");

            $stmt = $mysqli->prepare("INSERT INTO whatsapp_campaigns (user_id, template_name, template_params_json, list_ids_json, cost_in_credits, status) VALUES (?, ?, ?, ?, ?, 'queued')");
            $list_id_json = json_encode([$list_id]);
            $stmt->bind_param('isssd', $user_id, $template_name, $template_params, $list_id_json, $total_cost);
            $stmt->execute();
            $campaign_id = $stmt->insert_id;

            // 4. Add contacts to whatsapp_queue
            $contacts_stmt = $mysqli->prepare("SELECT c.phone_number, c.id FROM contacts c JOIN contact_list_map clm ON c.id = clm.contact_id WHERE clm.list_id = ? AND c.phone_number IS NOT NULL");
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
        $message = "Insufficient credits.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk WhatsApp Service</title>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar">
            <?php include 'includes/sidebar.php'; ?>
        </aside>
        <main class="main-content">
            <h1>Bulk WhatsApp Service</h1>
            <p><strong>Important:</strong> You may only send messages using pre-approved Meta/Gupshup templates.</p>
            <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>

            <form action="whatsapp-campaigns.php" method="post">
                <input type="hidden" name="send_whatsapp" value="1">
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
                    <label for="template_name">Message Template Name/ID</label>
                    <input type="text" id="template_name" name="template_name" required>
                </div>
                <div class="form-group">
                    <label for="template_params">Template Placeholders</label>
                    <input type="text" id="template_params" name="template_params" placeholder="e.g. {{1}}=first_name,{{2}}=custom_field_city">
                    <small>Map template variables to your contact list columns.</small>
                </div>
                <p>Cost per recipient: <?php echo $price_per_whatsapp; ?> credit(s)</p>
                <button type="submit">Queue WhatsApp Campaign</button>
            </form>
        </main>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
