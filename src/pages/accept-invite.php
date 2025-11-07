<?php
// src/pages/accept-invite.php
require_once __DIR__ . '/../lib/functions.php';
// No auth check here, as a non-logged-in user might be accepting an invite.

$token = $_GET['token'] ?? null;
$error = '';
$success = false;

if (!$token) {
    $error = "Invalid invitation link.";
} else {
    // Look up the invitation
    $stmt = $mysqli->prepare("SELECT * FROM team_invitations WHERE token = ? AND status = 'pending' AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $invitation = $stmt->get_result()->fetch_assoc();

    if (!$invitation) {
        $error = "This invitation is invalid or has expired.";
    } else {
        // If the user is not logged in, redirect them to register.
        // The registration page can then handle the token.
        if (!isset($_SESSION['user_id'])) {
            header('Location: /register?invite_token=' . $token);
            exit;
        }

        // User is logged in, add them to the team.
        $user_id = $_SESSION['user_id'];
        $team_id = $invitation['team_id'];

        $update_user = $mysqli->prepare("UPDATE users SET team_id = ?, team_role = 'member' WHERE id = ?");
        $update_user->bind_param("ii", $team_id, $user_id);

        if ($update_user->execute()) {
            // Mark invitation as accepted
            $update_invite = $mysqli->prepare("UPDATE team_invitations SET status = 'accepted' WHERE id = ?");
            $update_invite->bind_param("i", $invitation['id']);
            $update_invite->execute();
            $success = true;
        } else {
            $error = "Failed to join the team.";
        }
    }
}

include __DIR__ . '/../includes/header_public.php';
?>

<div class="container page-content">
    <div class="auth-form">
        <h2>Team Invitation</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success">
                You have successfully joined the team!
                <a href="/dashboard" class="btn btn-primary">Go to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer_public.php';
?>
