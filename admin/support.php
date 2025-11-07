<?php
$page_title = "Support Tickets";
require_once 'auth_admin.php';
require_once 'includes/header_admin.php';

// Fetch all tickets
$tickets_q = $mysqli->query("SELECT st.*, u.name as user_name FROM support_tickets st LEFT JOIN users u ON st.user_id = u.id ORDER BY st.created_at DESC");
$tickets = $tickets_q->fetch_all(MYSQLI_ASSOC);
?>
<div class="container-fluid">
    <h1>Support Tickets</h1>
    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>User</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($tickets as $ticket): ?>
                <tr>
                    <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                    <td><?php echo htmlspecialchars($ticket['user_name'] ?? $ticket['guest_email']); ?></td>
                    <td><?php echo ucfirst($ticket['status']); ?></td>
                    <td><?php echo $ticket['created_at']; ?></td>
                    <td><a href="view-admin-ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-info">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
require_once 'includes/footer_admin.php';
?>
