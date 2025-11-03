<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}
$admin_id = $_SESSION['user_id'];
$ticket_id = (int)($_GET['id'] ?? 0);
require_once '../config/db.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

$stmt = $mysqli->prepare("SELECT subject, status FROM support_tickets WHERE id = ?");
$stmt->bind_param('i', $ticket_id);
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
        // When admin replies, ticket should be re-opened
        $mysqli->query("UPDATE support_tickets SET status = 'open' WHERE id = $ticket_id");

        $stmt = $mysqli->prepare("INSERT INTO support_ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $ticket_id, $admin_id, $message);
        $stmt->execute();
        header("Location: view-admin-ticket.php?id=$ticket_id");
        exit;
    }
}

// Fetch all replies for this ticket
$replies_result = $mysqli->prepare("SELECT r.message, r.created_at, u.name as author, t.guest_name FROM support_ticket_replies r LEFT JOIN users u ON r.user_id = u.id JOIN support_tickets t ON r.ticket_id = t.id WHERE r.ticket_id = ? ORDER BY r.created_at ASC");
$replies_result->bind_param('i', $ticket_id);
$replies_result->execute();
$replies = $replies_result->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Admin: View Ticket</title></head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="admin-container">
        <aside class="sidebar"><?php include 'includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1><?php echo htmlspecialchars($ticket['subject']); ?></h1>
            <div class="ticket-replies">
                <?php while($reply = $replies->fetch_assoc()): ?>
                <div class="reply">
                    <p><strong><?php echo htmlspecialchars($reply['author'] ?? $reply['guest_name'] ?? 'Guest'); ?></strong> said:</p>
                    <p><?php echo nl2br(htmlspecialchars($reply['message'])); ?></p>
                    <small><?php echo $reply['created_at']; ?></small>
                </div><hr>
                <?php endwhile; ?>
            </div>

            <h2>Post a Reply</h2>
            <form action="view-admin-ticket.php?id=<?php echo $ticket_id; ?>" method="post">
                <input type="hidden" name="post_reply" value="1">
                <textarea name="message" rows="5" required></textarea><br>
                <button type="submit">Post Reply</button>
            </form>
        </main>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
