<?php
// src/pages/features.php
require_once __DIR__ . '/../lib/functions.php';

$page_title = "Features";

include __DIR__ . '/../includes/header_public.php';
?>

<div class="container page-content">
    <h1>Our Features</h1>
    <p>Explore the powerful tools included in our platform, designed to help you grow your business.</p>

    <div class="features-detailed-grid">
        <?php
        // This will be populated from the cms_features table
        $features = [
            [
                'icon' => 'fas fa-envelope-open-text',
                'title' => 'Advanced Email Marketing',
                'description' => 'Create, send, and track beautiful email campaigns with our easy-to-use editor. Personalize your messages, segment your audience, and get detailed analytics on opens, clicks, and bounces.'
            ],
            [
                'icon' => 'fas fa-mobile-alt',
                'title' => 'High-Impact SMS Campaigns',
                'description' => 'Reach your customers instantly with bulk SMS. Perfect for flash sales, appointment reminders, and time-sensitive promotions. Our platform ensures high deliverability rates.'
            ],
            [
                'icon' => 'fab fa-whatsapp-square',
                'title' => 'WhatsApp Messaging',
                'description' => 'Engage with customers on their favorite messaging app. Use pre-approved templates for notifications, alerts, and customer service conversations.'
            ],
            [
                'icon' => 'fas fa-user-check',
                'title' => 'Real-Time Email Verification',
                'description' => 'Protect your sender reputation and improve deliverability by cleaning your email lists. Our verifier checks for syntax errors, disposable domains, and invalid mail servers.'
            ],
            [
                'icon' => 'fas fa-file-signature',
                'title' => 'Drag & Drop Landing Page Builder',
                'description' => 'Build and publish stunning, responsive landing pages in minutes. No coding required. Capture leads directly into your contact lists and track conversions.'
            ],
            [
                'icon' => 'fas fa-users-cog',
                'title' => 'Team Management',
                'description' => 'Collaborate with your team members by sharing a single account. Manage permissions and share credits, contact lists, and campaigns seamlessly.'
            ],
             [
                'icon' => 'fas fa-qrcode',
                'title' => 'QR Code Generator',
                'description' => 'Bridge the gap between your offline and online marketing. Create custom QR codes that link to your landing pages, website, or special offers.'
            ],
             [
                'icon' => 'fab fa-facebook',
                'title' => 'Social Media Scheduler',
                'description' => 'Plan and automate your social media presence. Schedule posts for Facebook, Twitter, and LinkedIn to save time and engage your audience consistently.'
            ]
        ];

        foreach ($features as $feature) {
            echo '<div class="feature-detailed-item">';
            echo '<i class="' . $feature['icon'] . '"></i>';
            echo '<h3>' . $feature['title'] . '</h3>';
            echo '<p>' . $feature['description'] . '</p>';
            echo '</div>';
        }
        ?>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer_public.php';
?>
