<?php
require_once '../config/db.php';
require_once '../src/helpers.php'; // Assuming a helpers file for functions like generating tokens

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$invite_link = '';

// --- Get User's Team Info ---
$stmt = $mysqli->prepare("SELECT team_id, team_role FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
if ($user_result->num_rows === 0) {
    die("Error: User not found.");
}
$user = $user_result->fetch_assoc();
$team_id = $user['team_id'];
$team_role = $user['team_role'];

// --- Handle Invite ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_member']) && $team_role === 'owner' && $team_id) {
    $invite_email = trim($_POST['email'] ?? '');
    if (filter_var($invite_email, FILTER_VALIDATE_EMAIL)) {
        // Check if user is already in a team or has a pending invite
        $stmt_check = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_check->bind_param('s', $invite_email);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $message = "Error: A user with this email already exists.";
        } else {
            // Generate a secure unique token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+3 days'));

            $stmt_insert = $mysqli->prepare("INSERT INTO team_invitations (team_id, email, token, expires_at) VALUES (?, ?, ?, ?)");
            $stmt_insert->bind_param('isss', $team_id, $invite_email, $token, $expires_at);

            if ($stmt_insert->execute()) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                $invite_link = "{$protocol}://{$host}/register.php?invite_token={$token}";
                $message = "Invitation created successfully. Share this link with the new member:";
            } else {
                $message = "Error: Could not create invitation.";
            }
        }
    } else {
        $message = "Invalid email address provided.";
    }
}

// --- Fetch Team Members ---
$members = [];
if ($team_id) {
    $stmt = $mysqli->prepare("SELECT id, name, email, team_role FROM users WHERE team_id = ? ORDER BY name");
    $stmt->bind_param('i', $team_id);
    $stmt->execute();
    $members_result = $stmt->get_result();
    while($row = $members_result->fetch_assoc()) {
        $members[] = $row;
    }
}

// --- Fetch Pending Invitations ---
$invitations = [];
if ($team_id && $team_role === 'owner') {
    $stmt = $mysqli->prepare("SELECT id, email, status, expires_at FROM team_invitations WHERE team_id = ? AND status = 'pending' ORDER BY created_at DESC");
    $stmt->bind_param('i', $team_id);
    $stmt->execute();
    $invitations_result = $stmt->get_result();
    while($row = $invitations_result->fetch_assoc()) {
        $invitations[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Team Management</title><link rel="stylesheet" href="css/dashboard_style.css"></head>
<body>
    <?php include APP_ROOT . '/public/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Team Management</h1>
            <?php if ($message): ?>
                <p class="notice"><?php echo htmlspecialchars($message); ?></p>
                <?php if ($invite_link): ?>
                    <p><strong><input type="text" value="<?php echo htmlspecialchars($invite_link); ?>" readonly style="width: 100%; padding: 8px;"></strong></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($team_role === 'owner'): ?>
            <h2>Invite New Member</h2>
            <form action="/team" method="post" class="form-simple">
                <input type="hidden" name="invite_member" value="1">
                <div class="form-group">
                    <label for="email">Member's Email</label>
                    <input type="email" id="email" name="email" placeholder="new.member@example.com" required>
                </div>
                <button type="submit">Create Invitation Link</button>
            </form>
            <hr>

            <h2>Pending Invitations</h2>
            <?php if (empty($invitations)): ?>
                <p>No pending invitations.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>Email</th><th>Status</th><th>Expires On</th></tr></thead>
                <tbody>
                <?php foreach ($invitations as $invite): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($invite['email']); ?></td>
                        <td><?php echo htmlspecialchars($invite['status']); ?></td>
                        <td><?php echo htmlspecialchars($invite['expires_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <hr>

            <?php endif; ?>

            <h2>Your Team Members</h2>
            <table>
                <thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead>
                <tbody>
                <?php if (empty($members)): ?>
                    <tr><td colspan="3">Your team hasn't been set up yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($members as $member): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($member['name']); ?></td>
                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                        <td><?php echo htmlspecialchars($member['team_role']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </main>
    </div>
    <?php include APP_ROOT . '/public/includes/footer.php'; ?>
</body>
</html>
