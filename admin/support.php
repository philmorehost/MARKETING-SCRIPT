<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login');
    exit;
}
$message = '';

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    $new_status = $_POST['status'];
    if ($new_status === 'open' || $new_status === 'closed') {
        $stmt = $mysqli->prepare("UPDATE support_tickets SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $new_status, $ticket_id);
        $stmt->execute();
        $message = "Ticket status updated.";
    }
}

// Fetch tickets
$filter = $_GET['filter'] ?? 'open';
$sql = "SELECT t.id, t.subject, t.status, t.created_at, u.email as user_email, t.guest_email
        FROM support_tickets t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.status = ? ORDER BY t.created_at DESC";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $filter);
$stmt->execute();
$tickets = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Support Tickets</title><link rel="stylesheet" href="../css/admin_style.css"></head>
<body>
    <?php include APP_ROOT . '/admin/includes/header.php'; ?>
    <div class="admin-container">
        <aside class="sidebar"><?php include APP_ROOT . '/admin/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Support Tickets</h1>
            <a href="?filter=open">Open</a> | <a href="?filter=closed">Closed</a>

            <table>
                <thead><tr><th>Subject</th><th>User</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                <?php while ($ticket = $tickets->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['user_email'] ?? $ticket['guest_email'] ?? 'N/A'); ?></td>
                        <td>
                            <form action="" method="post" style="display:inline;">
                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                <select name="status" onchange="this.form.submit()">
                                    <option value="open" <?php if($ticket['status']==='open') echo 'selected'; ?>>Open</option>
                                    <option value="closed" <?php if($ticket['status']==='closed') echo 'selected'; ?>>Closed</option>
                                </select>
                                <input type="hidden" name="change_status" value="1">
                            </form>
                        </td>
                        <td><?php echo $ticket['created_at']; ?></td>
                        <td><a href="view-admin-ticket.php?id=<?php echo $ticket['id']; ?>">View</a></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </main>
    </div>
    <?php include APP_ROOT . '/admin/includes/footer.php'; ?>
</body>
</html>
