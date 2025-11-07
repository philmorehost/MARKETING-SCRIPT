<?php
$page_title = "Site Settings";
require_once 'auth_admin.php';

// Fetch all settings
$settings_result = $mysqli->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

function get_s($key, $default = '') {
    global $settings;
    return $settings[$key] ?? $default;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_settings = $_POST['settings'];
    $update_stmt = $mysqli->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    foreach ($posted_settings as $key => $value) {
        $update_stmt->bind_param("ss", $key, $value);
        $update_stmt->execute();
    }

    // Refresh settings
    $settings_result = $mysqli->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $success = true;
}

require_once 'includes/header_admin.php';
?>
<div class="container-fluid">
    <h1>Site Settings</h1>

    <?php if (isset($success) && $success): ?>
        <div class="alert alert-success">Settings saved successfully!</div>
    <?php endif; ?>

    <form action="settings.php" method="POST" class="card">
        <div class="tabs">
            <button type="button" class="tab-link active" onclick="openTab(event, 'general')">General</button>
            <button type="button" class="tab-link" onclick="openTab(event, 'pricing')">Service Pricing</button>
            <button type="button" class="tab-link" onclick="openTab(event, 'apis')">APIs & Integrations</button>
        </div>

        <div id="general" class="tab-content active">
            <h3>General Settings</h3>
            <div class="form-group">
                <label>Site Name</label>
                <input type="text" name="settings[site_name]" value="<?php echo get_s('site_name'); ?>" class="form-control">
            </div>
             <div class="form-group">
                <label>Bank Name</label>
                <input type="text" name="settings[bank_name]" value="<?php echo get_s('bank_name'); ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>Bank Account Number</label>
                <input type="text" name="settings[bank_account_number]" value="<?php echo get_s('bank_account_number'); ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>Bank Account Name</label>
                <input type="text" name="settings[bank_account_name]" value="<?php echo get_s('bank_account_name'); ?>" class="form-control">
            </div>
        </div>

        <div id="pricing" class="tab-content">
            <h3>Service Pricing (in Credits)</h3>
            <div class="form-group">
                <label>Cost per Email Verification</label>
                <input type="number" step="0.01" name="settings[price_per_verification]" value="<?php echo get_s('price_per_verification', 1); ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>Cost per Email Send</label>
                <input type="number" step="0.01" name="settings[price_per_email_send]" value="<?php echo get_s('price_per_email_send', 1); ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>Cost per SMS Page (160 chars)</label>
                <input type="number" step="0.01" name="settings[price_per_sms_page]" value="<?php echo get_s('price_per_sms_page', 5); ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>Cost per WhatsApp Message</label>
                <input type="number" step="0.01" name="settings[price_per_whatsapp]" value="<?php echo get_s('price_per_whatsapp', 10); ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>Cost to Publish Landing Page</label>
                <input type="number" step="1" name="settings[price_landing_page_publish]" value="<?php echo get_s('price_landing_page_publish', 100); ?>" class="form-control">
            </div>
        </div>

        <div id="apis" class="tab-content">
            <h3>API & Integration Settings</h3>
            <h4>Paystack</h4>
            <div class="form-group">
                <label>Paystack Public Key</label>
                <input type="text" name="settings[paystack_public_key]" value="<?php echo get_s('paystack_public_key'); ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>Paystack Secret Key</label>
                <input type="text" name="settings[paystack_secret_key]" value="<?php echo get_s('paystack_secret_key'); ?>" class="form-control">
            </div>
            <h4>Google</h4>
            <div class="form-group">
                <label>Google Client ID</label>
                <input type="text" name="settings[google_client_id]" value="<?php echo get_s('google_client_id'); ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>Google Client Secret</label>
                <input type="text" name="settings[google_client_secret]" value="<?php echo get_s('google_client_secret'); ?>" class="form-control">
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
</div>

<script>
function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }
    tablinks = document.getElementsByClassName("tab-link");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
}
</script>

<?php
require_once 'includes/footer_admin.php';
?>
