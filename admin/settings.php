<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = $_POST['settings'] ?? [];
    foreach ($settings as $key => $value) {
        // Use an "UPSERT" (UPDATE or INSERT) query
        $stmt = $mysqli->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->bind_param('sss', $key, $value, $value);
        $stmt->execute();
    }
    $message = "Settings saved successfully!";
}

// Fetch all settings
$settings_result = $mysqli->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

function get_setting($key, $default = '') {
    global $settings;
    return $settings[$key] ?? $default;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Site Settings</title>
    <link rel="stylesheet" href="../public/css/admin_style.css">
</head>
<body>
    <?php include APP_ROOT . '/admin/includes/header.php'; ?>
    <div class="admin-container">
        <aside class="sidebar">
            <?php include APP_ROOT . '/admin/includes/sidebar.php'; ?>
        </aside>
        <main class="main-content">
            <h1>Site Settings</h1>
            <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>

            <form action="" method="post">

                <h2>General</h2>
                <label>Site Name:</label>
                <input type="text" name="settings[site_name]" value="<?php echo htmlspecialchars(get_setting('site_name')); ?>">
                <label>Site Currency:</label>
                <input type="text" name="settings[site_currency]" value="<?php echo htmlspecialchars(get_setting('site_currency', 'USD')); ?>">

                <h2>Payments</h2>
                <label>Bank Details (for POP):</label>
                <textarea name="settings[bank_details]"><?php echo htmlspecialchars(get_setting('bank_details')); ?></textarea>
                <label>Paystack Secret Key:</label>
                <input type="text" name="settings[paystack_secret_key]" value="<?php echo htmlspecialchars(get_setting('paystack_secret_key')); ?>">

                <h2>Service Pricing (in Credits)</h2>
                <label>Cost per Email Verification:</label>
                <input type="number" step="0.0001" name="settings[price_per_verification]" value="<?php echo htmlspecialchars(get_setting('price_per_verification', '1')); ?>">
                <label>Cost per Email Send:</label>
                <input type="number" step="0.0001" name="settings[price_per_email_send]" value="<?php echo htmlspecialchars(get_setting('price_per_email_send', '1')); ?>">
                <label>Cost per SMS Page:</label>
                <input type="number" step="0.0001" name="settings[price_per_sms_page]" value="<?php echo htmlspecialchars(get_setting('price_per_sms_page', '5')); ?>">
                 <label>Cost per WhatsApp Message:</label>
                <input type="number" step="0.0001" name="settings[price_per_whatsapp]" value="<?php echo htmlspecialchars(get_setting('price_per_whatsapp', '10')); ?>">
                <label>Cost to Publish Landing Page:</label>
                <input type="number" step="0.0001" name="settings[price_landing_page_publish]" value="<?php echo htmlspecialchars(get_setting('price_landing_page_publish', '50')); ?>">
                <label>Cost per 1000 AI Content Words:</label>
                <input type="number" step="0.0001" name="settings[price_per_ai_word]" value="<?php echo htmlspecialchars(get_setting('price_per_ai_word', '10')); ?>">
                <label>Cost per Social Post:</label>
                <input type="number" step="0.0001" name="settings[price_per_social_post]" value="<?php echo htmlspecialchars(get_setting('price_per_social_post', '2')); ?>">
                 <label>Cost per QR Code:</label>
                <input type="number" step="0.0001" name="settings[price_per_qr_code]" value="<?php echo htmlspecialchars(get_setting('price_per_qr_code', '1')); ?>">

                <h2>API Settings</h2>
                <h3>Social & API Logins</h3>
                <label>Google Client ID:</label><input type="text" name="settings[google_client_id]" value="<?php echo htmlspecialchars(get_setting('google_client_id')); ?>">
                <label>Google Client Secret:</label><input type="text" name="settings[google_client_secret]" value="<?php echo htmlspecialchars(get_setting('google_client_secret')); ?>">

                <h3>SMS API</h3>
                <label>PhilmoreSMS API Key:</label><input type="text" name="settings[philmorsms_api_key]" value="<?php echo htmlspecialchars(get_setting('philmorsms_api_key')); ?>">
                <label>PhilmoreSMS Sender ID:</label><input type="text" name="settings[philmorsms_sender_id]" value="<?php echo htmlspecialchars(get_setting('philmorsms_sender_id')); ?>">

                <h3>WhatsApp API</h3>
                <label>Provider:</label>
                <select name="settings[whatsapp_provider]">
                    <option value="none" <?php if(get_setting('whatsapp_provider') == 'none') echo 'selected'; ?>>None</option>
                    <option value="gupshup" <?php if(get_setting('whatsapp_provider') == 'gupshup') echo 'selected'; ?>>Gupshup</option>
                    <option value="meta" <?php if(get_setting('whatsapp_provider') == 'meta') echo 'selected'; ?>>Meta (Official)</option>
                </select>
                <label>Gupshup API Key:</label><input type="text" name="settings[gupshup_api_key]" value="<?php echo htmlspecialchars(get_setting('gupshup_api_key')); ?>">
                <label>Gupshup Source Number:</label><input type="text" name="settings[gupshup_source_number]" value="<?php echo htmlspecialchars(get_setting('gupshup_source_number')); ?>">
                <label>Meta API Token:</label><input type="text" name="settings[meta_api_token]" value="<?php echo htmlspecialchars(get_setting('meta_api_token')); ?>">
                <label>Meta Phone Number ID:</label><input type="text" name="settings[meta_phone_number_id]" value="<?php echo htmlspecialchars(get_setting('meta_phone_number_id')); ?>">
                <label>Meta WABA ID:</label><input type="text" name="settings[meta_waba_id]" value="<?php echo htmlspecialchars(get_setting('meta_waba_id')); ?>">

                <h3>AI API</h3>
                 <label>Provider:</label>
                <select name="settings[ai_provider]">
                    <option value="none" <?php if(get_setting('ai_provider') == 'none') echo 'selected'; ?>>None</option>
                    <option value="google_gemini" <?php if(get_setting('ai_provider') == 'google_gemini') echo 'selected'; ?>>Google Gemini</option>
                    <option value="openai" <?php if(get_setting('ai_provider') == 'openai') echo 'selected'; ?>>OpenAI</option>
                </select>
                <label>API Key:</label><input type="text" name="settings[ai_provider_key]" value="<?php echo htmlspecialchars(get_setting('ai_provider_key')); ?>">

                <br><br>
                <button type="submit">Save Settings</button>
            </form>
        </main>
    </div>
    <?php include APP_ROOT . '/admin/includes/footer.php'; ?>
</body>
</html>
