<?php
// src/pages/view-whatsapp-report.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$campaign_id = $_GET['id'] ?? null;
// User ownership check...

$page_title = "WhatsApp Report";

include __DIR__ . '/../includes/header_app.php';
?>
<div class="container app-content">
    <h1>WhatsApp Campaign Report</h1>
    <p>Detailed report would be displayed here, including delivery and read rates, etc.</p>
</div>
<?php
include __DIR__ . '/../includes/footer_app.php';
?>
