<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$message = '';

// --- Get User's Team Info ---
$stmt = $mysqli->prepare("SELECT team_id, team_role FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$team_id = $user['team_id'];
$team_role = $user['team_role'];

// --- Handle Invite ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_member']) && $team_role === 'owner') {
    $invite_email = trim($_POST['email'] ?? '');
    if (filter_var($invite_email, FILTER_VALIDATE_EMAIL)) {
        // In a real app, you would send an email with a unique invite token.
        // For now, we'll just show a success message.
        $message = "Invitation sent to {$invite_email}.";
    } else {
        $message = "Invalid email address.";
    }
}

// --- Fetch Team Members ---
$members = [];
if ($team_id) {
    $stmt = $mysqli->prepare("SELECT id, name, email, team_role FROM users WHERE team_id = ?");
    $stmt->bind_param('i', $team_id);
    $stmt->execute();
    $members_result = $stmt->get_result();
    while($row = $members_result->fetch_assoc()) {
        $members[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Team Management</title></head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include 'includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Team Management</h1>
            <?php if ($message): ?><p><?php echo $message; ?></p><?php endif; ?>

            <?php if ($team_role === 'owner'): ?>
            <h2>Invite New Member</h2>
            <form action="team.php" method="post">
                <input type="hidden" name="invite_member" value="1">
                <input type="email" name="email" placeholder="new.member@example.com" required>
                <button type="submit">Send Invitation</button>
            </form>
            <hr>
            <?php endif; ?>

            <h2>Your Team</h2>
            <table>
                <thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead>
                <tbody>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($member['name']); ?></td>
                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                        <td><?php echo htmlspecialchars($member['team_role']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </main>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
