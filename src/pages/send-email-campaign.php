<?php
// src/pages/send-email-campaign.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /email-campaigns');
    exit;
}

$subject = trim($_POST['subject'] ?? '');
$html_content = trim($_POST['html_content'] ?? '');
$recipient_lists = $_POST['recipient_lists'] ?? [];
$action = $_POST['action'] ?? 'send'; // 'send' or 'schedule'

$errors = [];
if (empty($subject)) $errors[] = "Subject is required.";
if (empty($html_content)) $errors[] = "Email content cannot be empty.";
if (empty($recipient_lists)) $errors[] = "You must select at least one recipient list.";

// --- Get total recipients ---
$list_ids_str = implode(',', array_map('intval', $recipient_lists));
$recipients_query = $mysqli->query("
    SELECT DISTINCT c.id, c.email
    FROM contacts c
    JOIN contact_list_map cm ON c.id = cm.contact_id
    WHERE cm.list_id IN ($list_ids_str)
");
$recipients = $recipients_query->fetch_all(MYSQLI_ASSOC);
$total_recipients = count($recipients);

if ($total_recipients === 0) {
    $errors[] = "The selected lists have no contacts to send to.";
}

// --- Credit Check ---
$cost_per_email = get_setting('price_per_email_send', 1);
$total_cost = $total_recipients * $cost_per_email;

if ($user['credit_balance'] < $total_cost) {
    $errors[] = "Insufficient credits. This campaign requires $total_cost credits, but you only have {$user['credit_balance']}.";
}

if (!empty($errors)) {
    // Redirect back with errors (using session)
    $_SESSION['form_errors'] = $errors;
    header('Location: /create-email-campaign');
    exit;
}


// --- Process Campaign ---
$mysqli->begin_transaction();
try {
    // 1. Deduct credits
    $deduct_stmt = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance - ? WHERE id = ?");
    $deduct_stmt->bind_param("di", $total_cost, $user['id']);
    $deduct_stmt->execute();

    // 2. Create campaign record
    $status = ($action === 'schedule') ? 'scheduled' : 'queued';
    $campaign_stmt = $mysqli->prepare("INSERT INTO campaigns (user_id, team_id, subject, html_content, cost_in_credits, status) VALUES (?, ?, ?, ?, ?, ?)");
    $campaign_stmt->bind_param("iissss", $user['id'], $user['team_id'], $subject, $html_content, $total_cost, $status);
    $campaign_stmt->execute();
    $campaign_id = $campaign_stmt->insert_id;

    // 3. Add emails to the queue
    $queue_stmt = $mysqli->prepare("INSERT INTO campaign_queue (campaign_id, contact_id, email_address) VALUES (?, ?, ?)");
    foreach ($recipients as $recipient) {
        $queue_stmt->bind_param("iis", $campaign_id, $recipient['id'], $recipient['email']);
        $queue_stmt->execute();
    }

    // 4. Record transaction
    $trans_stmt = $mysqli->prepare("INSERT INTO transactions (user_id, type, description, amount_credits, status) VALUES (?, 'spend_email', ?, ?, 'completed')");
    $description = "Email campaign: " . $subject;
    $trans_stmt->bind_param("isd", $user['id'], $description, $total_cost);
    $trans_stmt->execute();

    $mysqli->commit();
    header('Location: /email-campaigns?status=queued');
    exit;

} catch (Exception $e) {
    $mysqli->rollback();
    $_SESSION['form_errors'] = ["An error occurred: " . $e->getMessage()];
    header('Location: /create-email-campaign');
    exit;
}
