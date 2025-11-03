<?php
header('Content-Type: application/json');

require_once '../config/db.php';
require_once '../src/lib/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$prompt = $_POST['prompt'] ?? '';

if (empty($prompt)) {
    echo json_encode(['error' => 'Prompt cannot be empty.']);
    exit;
}


// --- Settings ---
$ai_provider = get_setting('ai_provider', $mysqli, 'none');
$api_key = get_setting('ai_api_key', $mysqli);
$price_per_1000_words = (float)get_setting('price_per_ai_word', $mysqli, 10);

if ($ai_provider === 'none' || empty($api_key)) {
    echo json_encode(['error' => 'AI provider is not configured by the admin.']);
    exit;
}

// --- Placeholder for actual AI API Call ---
// In a real implementation, you would use the OpenAI/Gemini SDK here.
$generated_text = "This is a sample AI-generated response for the prompt: '{$prompt}'. It contains twenty-five words to demonstrate the credit calculation functionality for this feature.";
$word_count = str_word_count($generated_text);
// --- End Placeholder ---

// --- Credit Calculation & Deduction ---
$cost = ($word_count / 1000) * $price_per_1000_words;

$stmt = $mysqli->prepare("SELECT credit_balance FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_balance = $stmt->get_result()->fetch_assoc()['credit_balance'];

if ($user_balance < $cost) {
    echo json_encode(['error' => 'Insufficient credits for this AI generation.']);
    exit;
}

// Deduct credits in a transaction
$mysqli->begin_transaction();
try {
    $update_stmt = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance - ? WHERE id = ?");
    $update_stmt->bind_param('di', $cost, $user_id);
    $update_stmt->execute();

    // Log the transaction
    $desc = "AI Content Generation ({$word_count} words)";
    $tx_stmt = $mysqli->prepare("INSERT INTO transactions (user_id, type, description, amount_credits, status) VALUES (?, 'spend_ai', ?, ?, 'completed')");
    $tx_stmt->bind_param('isd', $user_id, $desc, $cost);
    $tx_stmt->execute();

    $mysqli->commit();

    echo json_encode(['success' => true, 'content' => $generated_text, 'cost' => $cost]);

} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(['error' => 'An error occurred during transaction.']);
}
