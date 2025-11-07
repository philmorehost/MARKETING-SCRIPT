<?php
// src/pages/terms.php
require_once __DIR__ . '/../lib/functions.php';

$page_title = "Terms of Service";

include __DIR__ . '/../includes/header_public.php';
?>

<div class="container page-content">
    <h1><?php echo get_content('terms_of_service_title', 'Terms of Service'); ?></h1>
    <div class="cms-content">
        <?php echo get_content('terms_of_service_content', '<p>Your terms of service content goes here. You can edit this from the admin dashboard.</p>'); ?>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer_public.php';
?>
