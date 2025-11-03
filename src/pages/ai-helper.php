<?php
header('Content-Type: application/json');

// Note: session_start() is handled by the front controller
// Note: APP_ROOT and $mysqli are available from the front controller

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
$user_id = $_SESSION['user_id'];
$team_owner_id = $_SESSION['team_owner_id'];
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

// --- AI API Call ---
$generated_text = '';
$error_message = null;

if ($ai_provider === 'google_gemini') {
    $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $api_key;
    $post_data = json_encode([
        'contents' => [[
            'parts' => [[
                'text' => $prompt
            ]]
        ]]
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        $error_message = 'cURL Error: ' . $err;
    } else {
        $response_data = json_decode($response, true);
        if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
            $generated_text = $response_data['candidates'][0]['content']['parts'][0]['text'];
        } else {
            $error_message = 'Failed to parse AI response. ' . ($response_data['error']['message'] ?? 'Unknown API error.');
        }
    }
} else {
    // Placeholder for other providers like OpenAI
    $error_message = "Provider '{$ai_provider}' is not yet supported.";
}

if ($error_message) {
    echo json_encode(['error' => $error_message]);
    exit;
}

// --- Credit Calculation & Deduction ---
$word_count = str_word_count($generated_text);
$cost = ($word_count / 1000) * $price_per_1000_words;

$stmt_balance = $mysqli->prepare("SELECT credit_balance FROM users WHERE id = ?");
$stmt_balance->bind_param('i', $team_owner_id);
$stmt_balance->execute();
$user_balance = (float)$stmt_balance->get_result()->fetch_assoc()['credit_balance'];

if ($user_balance < $cost) {
    echo json_encode(['error' => 'Insufficient credits for this AI generation. Cost: ' . $cost]);
    exit;
}

$mysqli->begin_transaction();
try {
    $update_stmt = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance - ? WHERE id = ?");
    $update_stmt->bind_param('di', $cost, $team_owner_id);
    $update_stmt->execute();

    $desc = "AI Content Generation ({$word_count} words)";
    $tx_stmt = $mysqli->prepare("INSERT INTO transactions (user_id, team_id, type, description, amount_credits, status) VALUES (?, ?, 'spend_ai', ?, ?, 'completed')");
    $tx_stmt->bind_param('iisd', $user_id, $_SESSION['team_id'], $desc, $cost);
    $tx_stmt->execute();

    $mysqli->commit();

    echo json_encode(['success' => true, 'content' => $generated_text, 'cost' => $cost]);

} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(['error' => 'An error occurred during transaction.']);
}
