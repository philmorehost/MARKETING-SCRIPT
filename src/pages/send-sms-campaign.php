<?php
// src/pages/send-sms-campaign.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /sms-campaigns');
    exit;
}

// --- Form Data ---
$sender_id = trim($_POST['sender_id'] ?? '');
$message = trim($_POST['message'] ?? '');
$recipient_lists = $_POST['recipient_lists'] ?? [];
$manual_recipients_str = trim($_POST['manual_recipients'] ?? '');

// --- Validation ---
$errors = [];
if (empty($sender_id)) $errors[] = "Sender ID is required.";
if (empty($message)) $errors[] = "Message cannot be empty.";
if (empty($recipient_lists) && empty($manual_recipients_str)) $errors[] = "You must select at least one recipient.";

// --- Consolidate Recipients ---
$phone_numbers = [];
// From lists
if (!empty($recipient_lists)) {
    $list_ids_str = implode(',', array_map('intval', $recipient_lists));
    $recipients_query = $mysqli->query("SELECT phone_number FROM contacts c JOIN contact_list_map cm ON c.id = cm.contact_id WHERE cm.list_id IN ($list_ids_str) AND c.phone_number IS NOT NULL");
    while ($row = $recipients_query->fetch_assoc()) {
        $phone_numbers[] = $row['phone_number'];
    }
}
// From textarea
if (!empty($manual_recipients_str)) {
    $manual_numbers = explode("\n", $manual_recipients_str);
    foreach ($manual_numbers as $num) {
        $phone_numbers[] = trim($num);
    }
}
$unique_phone_numbers = array_unique(array_filter($phone_numbers));
$total_recipients = count($unique_phone_numbers);

if ($total_recipients === 0) $errors[] = "No valid phone numbers found.";

// --- Cost Calculation ---
$char_count = strlen($message);
$sms_pages = $char_count <= 160 ? 1 : ceil($char_count / 153);
$cost_per_sms_page = get_setting('price_per_sms_page', 5);
$total_cost = $total_recipients * $sms_pages * $cost_per_sms_page;

if ($user['credit_balance'] < $total_cost) {
    $errors[] = "Insufficient credits. This campaign requires $total_cost credits.";
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    header('Location: /sms-campaigns');
    exit;
}

// --- Process Campaign ---
$mysqli->begin_transaction();
try {
    // 1. Deduct credits
    $mysqli->query("UPDATE users SET credit_balance = credit_balance - $total_cost WHERE id = {$user['id']}");

    // 2. Create SMS campaign record
    $list_ids_json = json_encode($recipient_lists);
    $stmt = $mysqli->prepare("INSERT INTO sms_campaigns (user_id, team_id, sender_id, message_body, list_ids_json, total_pages, cost_in_credits, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'queued')");
    $stmt->bind_param("iisssids", $user['id'], $user['team_id'], $sender_id, $message, $list_ids_json, $sms_pages, $total_cost);
    $stmt->execute();
    $campaign_id = $stmt->insert_id;

    // 3. Add messages to queue
    $queue_stmt = $mysqli->prepare("INSERT INTO sms_queue (sms_campaign_id, phone_number, message_pages) VALUES (?, ?, ?)");
    foreach ($unique_phone_numbers as $number) {
        $queue_stmt->bind_param("isi", $campaign_id, $number, $sms_pages);
        $queue_stmt->execute();
    }

    // 4. Record transaction
    $description = "SMS Campaign: " . substr($message, 0, 50);
    $mysqli->query("INSERT INTO transactions (user_id, type, description, amount_credits, status) VALUES ({$user['id']}, 'spend_sms', '$description', $total_cost, 'completed')");

    $mysqli->commit();
    header('Location: /sms-campaigns?status=queued');
    exit;

} catch (Exception $e) {
    $mysqli->rollback();
    $_SESSION['form_errors'] = ["An error occurred: " . $e->getMessage()];
    header('Location: /sms-campaigns');
    exit;
}
