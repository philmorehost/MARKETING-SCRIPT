<?php
// src/pages/view-ticket.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$ticket_id = $_GET['id'] ?? null;
// User ownership check...

// Handle replies
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply') {
    $message = trim($_POST['message'] ?? '');
    if(!empty($message)) {
        $stmt = $mysqli->prepare("INSERT INTO support_ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $ticket_id, $user['id'], $message);
        $stmt->execute();
        header("Location: /view-ticket?id=$ticket_id");
        exit;
    }
}

// Fetch ticket and replies...
$ticket_q = $mysqli->query("SELECT * FROM support_tickets WHERE id = $ticket_id");
$ticket = $ticket_q->fetch_assoc();
$replies_q = $mysqli->query("SELECT r.*, u.name as author FROM support_ticket_replies r LEFT JOIN users u ON r.user_id = u.id WHERE r.ticket_id = $ticket_id ORDER BY r.created_at ASC");
$replies = $replies_q->fetch_all(MYSQLI_ASSOC);

$page_title = "View Ticket";
include __DIR__ . '/../includes/header_app.php';
?>
<div class="container app-content">
    <a href="/support">&larr; Back to all tickets</a>
    <h1><?php echo htmlspecialchars($ticket['subject']); ?></h1>

    <div class="ticket-replies">
        <?php foreach($replies as $reply): ?>
        <div class="reply-card">
            <strong><?php echo htmlspecialchars($reply['author'] ?? 'Admin'); ?></strong>
            <p><?php echo nl2br(htmlspecialchars($reply['message'])); ?></p>
            <small><?php echo $reply['created_at']; ?></small>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h3>Post a Reply</h3>
        <form method="POST">
            <input type="hidden" name="action" value="reply">
            <textarea name="message" class="form-control" rows="5"></textarea>
            <button type="submit" class="btn btn-primary">Submit Reply</button>
        </form>
    </div>
</div>
<?php
include __DIR__ . '/../includes/footer_app.php';
?>
