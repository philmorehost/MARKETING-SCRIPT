<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Fetch user data
$stmt = $mysqli->prepare("SELECT credit_balance, first_login_wizard_complete FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$show_wizard = !$user['first_login_wizard_complete'];

// Placeholder stats
$stats = [
    'total_contacts' => 0,
    'emails_verified' => 0,
    'emails_sent' => 0,
    'sms_sent' => 0,
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="css/dashboard_style.css">
</head>
<body>
    <?php include 'includes/header.php'; // We need to update this to show credits ?>

    <div class="user-container">
        <aside class="sidebar">
            <?php include 'includes/sidebar.php'; ?>
        </aside>
        <main class="main-content">
            <h1>Dashboard</h1>

            <?php if ($show_wizard): ?>
            <div class="wizard">
                <h2>Welcome to the Platform!</h2>
                <p>Let's get you started.</p>
                <a href="contacts.php?action=create_list">Step 1: Create your first contact list</a><br>
                <a href="buy-credits.php">Step 2: Buy some credits to get started</a>
                <!-- Link to mark wizard as complete will be here -->
            </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="card main-credits-card">
                    <h3>Available Credits</h3>
                    <p><?php echo number_format($user['credit_balance'], 4); ?></p>
                    <a href="buy-credits.php" class="button">Buy More</a>
                </div>
                <div class="card">
                    <h3>Total Contacts</h3>
                    <p><?php echo $stats['total_contacts']; ?></p>
                </div>
                <div class="card">
                    <h3>Emails Verified</h3>
                    <p><?php echo $stats['emails_verified']; ?></p>
                </div>
                <div class="card">
                    <h3>Emails Sent</h3>
                    <p><?php echo $stats['emails_sent']; ?></p>
                </div>
                 <div class="card">
                    <h3>SMS Sent</h3>
                    <p><?php echo $stats['sms_sent']; ?></p>
                </div>
            </div>

            <!-- Charts will go here -->
            <div class="charts-section">

            </div>
        </main>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
