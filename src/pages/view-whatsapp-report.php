<?php
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];
$campaign_id = $_GET['id'] ?? 0;

if (!$campaign_id) {
    header('Location: whatsapp-reports.php');
    exit;
}

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Fetch campaign details, ensuring it belongs to the user's team
$stmt = $mysqli->prepare("SELECT * FROM whatsapp_campaigns WHERE id = ? AND team_id = ?");
$stmt->bind_param('ii', $campaign_id, $team_id);
$stmt->execute();
$result = $stmt->get_result();
$campaign = $result->fetch_assoc();

if (!$campaign) {
    echo "Campaign not found or you don't have permission to view it.";
    exit;
}

// Fetch individual message statuses for this campaign
$stmt = $mysqli->prepare("SELECT phone_number, status, api_message_id, updated_at FROM whatsapp_queue WHERE campaign_id = ? ORDER BY updated_at DESC");
$stmt->bind_param('i', $campaign_id);
$stmt->execute();
$messages = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WhatsApp Campaign Details - <?php echo htmlspecialchars($campaign['template_name']); ?></title>
    <link rel="stylesheet" href="css/dashboard_style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include 'includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Campaign Report: <?php echo htmlspecialchars($campaign['template_name']); ?></h1>
            <p><strong>Date:</strong> <?php echo $campaign['created_at']; ?></p>
            <p><strong>Total Cost:</strong> <?php echo number_format($campaign['cost_in_credits'], 4); ?> credits</p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($campaign['status']); ?></p>

            <hr>

            <h2>Recipient Statuses</h2>
            <table>
                <thead>
                    <tr>
                        <th>Phone Number</th>
                        <th>Status</th>
                        <th>Message ID</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($message = $messages->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($message['phone_number']); ?></td>
                        <td><?php echo htmlspecialchars($message['status']); ?></td>
                        <td><?php echo htmlspecialchars($message['api_message_id']); ?></td>
                        <td><?php echo $message['updated_at']; ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>

             <a href="whatsapp-reports.php">Back to WhatsApp Reports</a>
        </main>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
