<?php
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$message = '';

// Handle New Ticket Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['message'] ?? '');

    if (!empty($subject) && !empty($body)) {
        $stmt = $mysqli->prepare("INSERT INTO support_tickets (user_id, subject) VALUES (?, ?)");
        $stmt->bind_param('is', $user_id, $subject);
        if ($stmt->execute()) {
            $ticket_id = $stmt->insert_id;
            $reply_stmt = $mysqli->prepare("INSERT INTO support_ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)");
            $reply_stmt->bind_param('iis', $ticket_id, $user_id, $body);
            $reply_stmt->execute();
            $message = "Support ticket created successfully.";
        } else {
            $message = "Error creating ticket.";
        }
    } else {
        $message = "Subject and message are required.";
    }
}

// Fetch user's tickets
$tickets_result = $mysqli->prepare("SELECT id, subject, status, created_at FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC");
$tickets_result->bind_param('i', $user_id);
$tickets_result->execute();
$tickets = $tickets_result->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Support Tickets</title><link rel="stylesheet" href="/css/dashboard_style.css"></head>
<body>
    <?php include APP_ROOT . '/public_html/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public_html/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Support Tickets</h1>
            <?php if ($message): ?><p><?php echo $message; ?></p><?php endif; ?>

            <h2>Create New Ticket</h2>
            <form action="/public/support" method="post">
                <input type="hidden" name="create_ticket" value="1">
                <input type="text" name="subject" placeholder="Subject" required><br>
                <textarea name="message" rows="5" placeholder="Describe your issue..." required></textarea><br>
                <button type="submit">Create Ticket</button>
            </form>

            <hr>
            <h2>Your Tickets</h2>
            <table>
                <thead><tr><th>Subject</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                <?php while ($ticket = $tickets->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['status']); ?></td>
                        <td><?php echo $ticket['created_at']; ?></td>
                        <td><a href="view-ticket.php?id=<?php echo $ticket['id']; ?>">View</a></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </main>
    </div>
    <?php include APP_ROOT . '/public_html/includes/footer.php'; ?>
</body>
</html>
