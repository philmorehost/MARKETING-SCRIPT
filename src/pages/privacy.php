<?php
// src/pages/privacy.php
require_once __DIR__ . '/../lib/functions.php';

$page_title = "Privacy Policy";

include __DIR__ . '/../includes/header_public.php';
?>

<div class="container page-content">
    <h1><?php echo get_content('privacy_policy_title', 'Privacy Policy'); ?></h1>
    <div class="cms-content">
        <?php echo get_content('privacy_policy_content', '<p>Your privacy policy content goes here. You can edit this from the admin dashboard.</p>'); ?>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer_public.php';
?>
