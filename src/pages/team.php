<?php
// src/pages/team.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "Team Management";

// Team creation for users who don't have one yet
if (empty($user['team_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_team') {
        $team_name = trim($_POST['team_name']);
        if (!empty($team_name)) {
            $mysqli->begin_transaction();
            try {
                $stmt = $mysqli->prepare("INSERT INTO teams (owner_user_id, team_name) VALUES (?, ?)");
                $stmt->bind_param("is", $user['id'], $team_name);
                $stmt->execute();
                $team_id = $stmt->insert_id;

                $update_user = $mysqli->prepare("UPDATE users SET team_id = ?, team_role = 'owner' WHERE id = ?");
                $update_user->bind_param("ii", $team_id, $user['id']);
                $update_user->execute();
                $mysqli->commit();
                header("Location: /team");
                exit;
            } catch (Exception $e) {
                $mysqli->rollback();
            }
        }
    }
} else {
    // Fetch team members
    $members_q = $mysqli->prepare("SELECT id, name, email, team_role FROM users WHERE team_id = ?");
    $members_q->bind_param("i", $user['team_id']);
    $members_q->execute();
    $team_members = $members_q->get_result()->fetch_all(MYSQLI_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'invite_member') {
        $invite_email = trim($_POST['invite_email']);
        if (filter_var($invite_email, FILTER_VALIDATE_EMAIL)) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+3 days'));
            $stmt = $mysqli->prepare("INSERT INTO team_invitations (team_id, email, token, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $user['team_id'], $invite_email, $token, $expires);
            $stmt->execute();
            // In a real app, you'd email the link: /accept-invite?token=$token
        }
    }
}

include __DIR__ . '/../includes/header_app.php';
?>
<div class="container app-content">
    <h1>Team Management</h1>

    <?php if (empty($user['team_id'])): ?>
        <div class="card">
            <h2>Create Your Team</h2>
            <p>Create a team to collaborate and share credits and resources.</p>
            <form method="POST">
                <input type="hidden" name="action" value="create_team">
                <div class="form-group">
                    <label>Team Name</label>
                    <input type="text" name="team_name" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Create Team</button>
            </form>
        </div>
    <?php else: ?>
        <div class="card">
            <h3>Team Members</h3>
            <table class="table">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Role</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($team_members as $member): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($member['name']); ?></td>
                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                        <td><?php echo ucfirst($member['team_role']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($user['team_role'] === 'owner'): ?>
        <div class="card">
            <h3>Invite New Member</h3>
            <form method="POST">
                <input type="hidden" name="action" value="invite_member">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="invite_email" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-success">Send Invitation</button>
            </form>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php
include __DIR__ . '/../includes/footer_app.php';
?>
