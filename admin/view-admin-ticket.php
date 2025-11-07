<?php
$page_title = "View Ticket";
require_once 'auth_admin.php';

$ticket_id = $_GET['id'] ?? null;
// Handle replies... (similar to user-facing side)

// Fetch ticket and replies...
$ticket_q = $mysqli->query("SELECT * FROM support_tickets WHERE id = $ticket_id");
$ticket = $ticket_q->fetch_assoc();
$replies_q = $mysqli->query("SELECT r.*, u.name as author FROM support_ticket_replies r LEFT JOIN users u ON r.user_id = u.id WHERE r.ticket_id = $ticket_id ORDER BY r.created_at ASC");
$replies = $replies_q->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header_admin.php';
?>
<div class="container-fluid">
    <a href="support.php">&larr; Back to all tickets</a>
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
            <textarea name="message" class="form-control" rows="5"></textarea>
            <button type="submit" class="btn btn-primary">Submit Reply</button>
        </form>
    </div>
</div>
<?php
require_once 'includes/footer_admin.php';
?>
