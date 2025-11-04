<?php
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

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
    <link rel="stylesheet" href="/css/dashboard_style.css">
</head>
<body>
    <?php include APP_ROOT . '/public_html/includes/header.php'; // We need to update this to show credits ?>

    <div class="user-container">
        <aside class="sidebar">
            <?php include APP_ROOT . '/public_html/includes/sidebar.php'; ?>
        </aside>
        <main class="main-content">
            <h1>Dashboard</h1>

            <?php if ($show_wizard): ?>
            <div id="wizard-modal" class="modal-overlay">
                <div class="modal-content">
                    <div id="wizard-step-1" class="wizard-step">
                        <h2>Welcome to the Platform!</h2>
                        <p>Let's get you started in just a few quick steps.</p>
                        <button onclick="nextStep(2)">Start Setup</button>
                    </div>
                    <div id="wizard-step-2" class="wizard-step" style="display:none;">
                        <h2>Step 1: Create a Contact List</h2>
                        <p>This is where you'll store your contacts. Give your first list a name.</p>
                        <input type="text" id="wizard-list-name" placeholder="e.g., Newsletter Subscribers">
                        <button onclick="createList()">Create List & Continue</button>
                    </div>
                    <div id="wizard-step-3" class="wizard-step" style="display:none;">
                        <h2>Step 2: Get Some Credits</h2>
                        <p>Our platform is pay-as-you-go. Buy credits to use for email, SMS, and other services.</p>
                        <a href="buy-credits.php" class="button-primary">Go to "Buy Credits"</a>
                        <button onclick="finishWizard()">I'll do this later</button>
                    </div>
                </div>
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

    <?php include APP_ROOT . '/public_html/includes/footer.php'; ?>

    <?php if ($show_wizard): ?>
    <script>
        function nextStep(step) {
            document.getElementById('wizard-step-' + (step - 1)).style.display = 'none';
            document.getElementById('wizard-step-' + step).style.display = 'block';
        }

        function createList() {
            const listName = document.getElementById('wizard-list-name').value;
            if (listName.trim() === '') {
                alert('Please enter a list name.');
                return;
            }

            fetch('ajax/wizard_create_list.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'list_name=' + encodeURIComponent(listName)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    nextStep(3);
                } else {
                    alert('Error: ' + (data.error || 'Could not create list.'));
                }
            });
        }

        function finishWizard() {
            fetch('ajax/wizard_complete.php', { method: 'POST' });
            document.getElementById('wizard-modal').style.display = 'none';
        }
    </script>
    <?php endif; ?>
</body>
</html>
