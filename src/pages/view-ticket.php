<?php
require_once '../config/db.php';
$user_id = $_SESSION['user_id'] ?? 0;
$ticket_id = (int)($_GET['id'] ?? 0);
if ($user_id === 0 || $ticket_id === 0) {
    header('Location: login.php');
    exit;
}


// Verify ticket ownership
$stmt = $mysqli->prepare("SELECT subject, status FROM support_tickets WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $ticket_id, $user_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
if (!$ticket) {
    header('Location: support.php');
    exit;
}

// Handle new reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_reply'])) {
    $message = trim($_POST['message'] ?? '');
    if (!empty($message)) {
        $stmt = $mysqli->prepare("INSERT INTO support_ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $ticket_id, $user_id, $message);
        $stmt->execute();
        header("Location: view-ticket.php?id=$ticket_id"); // Refresh to show new reply
        exit;
    }
}

// Fetch all replies for this ticket
$replies_result = $mysqli->prepare("SELECT r.message, r.created_at, u.name as author FROM support_ticket_replies r LEFT JOIN users u ON r.user_id = u.id WHERE r.ticket_id = ? ORDER BY r.created_at ASC");
$replies_result->bind_param('i', $ticket_id);
$replies_result->execute();
$replies = $replies_result->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Viewing Ticket</title><link rel="stylesheet" href="css/dashboard_style.css"></head>
<body>
    <?php include APP_ROOT . '/public/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1><?php echo htmlspecialchars($ticket['subject']); ?></h1>
            <p>Status: <?php echo htmlspecialchars($ticket['status']); ?></p>

            <div class="ticket-replies">
                <?php while($reply = $replies->fetch_assoc()): ?>
                <div class="reply">
                    <p><strong><?php echo htmlspecialchars($reply['author'] ?? 'Guest'); ?></strong> said:</p>
                    <p><?php echo nl2br(htmlspecialchars($reply['message'])); ?></p>
                    <small><?php echo $reply['created_at']; ?></small>
                </div>
                <hr>
                <?php endwhile; ?>
            </div>

            <h2>Post a Reply</h2>
            <form action="/public/view-ticket?id=<?php echo $ticket_id; ?>" method="post">
                <input type="hidden" name="post_reply" value="1">
                <textarea name="message" rows="5" required></textarea><br>
                <button type="submit">Post Reply</button>
            </form>
        </main>
    </div>
    <?php include APP_ROOT . '/public/includes/footer.php'; ?>
</body>
</html>
