<?php
// src/pages/support.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "Support";

// Handle new ticket creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_ticket') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if(!empty($subject) && !empty($message)) {
        $mysqli->begin_transaction();
        try {
            $stmt = $mysqli->prepare("INSERT INTO support_tickets (user_id, team_id, subject) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $user['id'], $user['team_id'], $subject);
            $stmt->execute();
            $ticket_id = $stmt->insert_id;

            $reply_stmt = $mysqli->prepare("INSERT INTO support_ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)");
            $reply_stmt->bind_param("iis", $ticket_id, $user['id'], $message);
            $reply_stmt->execute();

            $mysqli->commit();
            header("Location: /view-ticket?id=$ticket_id");
            exit;
        } catch (Exception $e) {
            $mysqli->rollback();
        }
    }
}

// Fetch user's tickets
$team_id_condition = $user['team_id'] ? "team_id = " . $user['team_id'] : "user_id = " . $user['id'];
$tickets_q = $mysqli->query("SELECT * FROM support_tickets WHERE $team_id_condition ORDER BY created_at DESC");
$tickets = $tickets_q->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header_app.php';
?>
<div class="container app-content">
    <div class="page-header">
        <h1>Support Tickets</h1>
        <button class="btn btn-primary" onclick="document.getElementById('createTicketModal').style.display='block'">Create New Ticket</button>
    </div>

    <div class="card">
        <table class="table">
            <thead>
                <tr><th>Subject</th><th>Status</th><th>Last Updated</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach($tickets as $ticket): ?>
                <tr>
                    <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                    <td><?php echo ucfirst($ticket['status']); ?></td>
                    <td><?php echo $ticket['created_at']; ?></td>
                    <td><a href="/view-ticket?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-info">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="createTicketModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('createTicketModal').style.display='none'">&times;</span>
        <h2>Create New Ticket</h2>
        <form method="POST">
            <input type="hidden" name="action" value="create_ticket">
            <div class="form-group">
                <label>Subject</label>
                <input type="text" name="subject" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Message</label>
                <textarea name="message" rows="5" class="form-control" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Submit Ticket</button>
        </form>
    </div>
</div>
<?php
include __DIR__ . '/../includes/footer_app.php';
?>
