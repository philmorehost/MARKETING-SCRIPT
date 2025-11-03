<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

require_once '../config/db.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
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
    <?php include 'includes/header.php'; ?>
    <div class="admin-container">
        <aside class="sidebar">
            <?php include 'includes/sidebar.php'; ?>
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

                <!-- Add other settings as needed from the brief -->

                <br><br>
                <button type="submit">Save Settings</button>
            </form>
        </main>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
