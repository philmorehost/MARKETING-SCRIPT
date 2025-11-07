<?php
// src/pages/send-whatsapp-campaign.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /whatsapp-campaigns');
    exit;
}

// --- Form Data ---
$template_name = trim($_POST['template_name'] ?? '');
$list_id = $_POST['recipient_list'] ?? null;
$placeholders = $_POST['placeholders'] ?? [];
$template_params_json = json_encode($placeholders);

// --- Validation ---
$errors = [];
if (empty($template_name)) $errors[] = "Template Name is required.";
if (empty($list_id)) $errors[] = "You must select a recipient list.";

// --- Get total recipients ---
$recipients_query = $mysqli->prepare("SELECT COUNT(c.id) FROM contacts c JOIN contact_list_map cm ON c.id = cm.contact_id WHERE cm.list_id = ? AND c.phone_number IS NOT NULL");
$recipients_query->bind_param("i", $list_id);
$recipients_query->execute();
$total_recipients = $recipients_query->get_result()->fetch_row()[0];

if ($total_recipients === 0) $errors[] = "The selected list has no contacts with phone numbers.";

// --- Credit Check ---
$cost_per_whatsapp = get_setting('price_per_whatsapp', 10);
$total_cost = $total_recipients * $cost_per_whatsapp;
if ($user['credit_balance'] < $total_cost) {
    $errors[] = "Insufficient credits. This campaign requires $total_cost credits.";
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    header('Location: /whatsapp-campaigns');
    exit;
}

// --- Process Campaign ---
$mysqli->begin_transaction();
try {
    // 1. Deduct credits
    $mysqli->query("UPDATE users SET credit_balance = credit_balance - $total_cost WHERE id = {$user['id']}");

    // 2. Create campaign record
    $list_ids_json = json_encode([$list_id]);
    $stmt = $mysqli->prepare("INSERT INTO whatsapp_campaigns (user_id, team_id, template_name, template_params_json, list_ids_json, cost_in_credits, status) VALUES (?, ?, ?, ?, ?, ?, 'queued')");
    $stmt->bind_param("iisssd", $user['id'], $user['team_id'], $template_name, $template_params_json, $list_ids_json, $total_cost);
    $stmt->execute();
    $campaign_id = $stmt->insert_id;

    // 3. Add to queue (A cron job will expand this into individual messages)
    // For WhatsApp, we might queue the campaign as a whole, and the cron expands it.
    // This is different from email/sms for simplicity here.

    // 4. Record transaction
    $description = "WhatsApp Campaign: " . $template_name;
    $mysqli->query("INSERT INTO transactions (user_id, type, description, amount_credits, status) VALUES ({$user['id']}, 'spend_whatsapp', '$description', $total_cost, 'completed')");

    $mysqli->commit();
    header('Location: /whatsapp-campaigns?status=queued');
    exit;

} catch (Exception $e) {
    $mysqli->rollback();
    $_SESSION['form_errors'] = ["An error occurred: " . $e->getMessage()];
    header('Location: /whatsapp-campaigns');
    exit;
}
