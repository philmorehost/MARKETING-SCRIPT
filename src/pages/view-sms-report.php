<?php
// src/pages/view-sms-report.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$campaign_id = $_GET['id'] ?? null;
// User ownership check...

$page_title = "SMS Report";

include __DIR__ . '/../includes/header_app.php';
?>
<div class="container app-content">
    <h1>SMS Campaign Report</h1>
    <p>Detailed report would be displayed here, including delivery rates, a list of failed numbers, etc.</p>
</div>
<?php
include __DIR__ . '/../includes/footer_app.php';
?>
