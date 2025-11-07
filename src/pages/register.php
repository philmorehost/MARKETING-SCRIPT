<?php
// src/pages/register.php
$page_title = "Sign Up";
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($name)) $errors[] = "Name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters long.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

    // Check if email already exists
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $errors[] = "An account with this email already exists.";
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // For simplicity, we'll auto-confirm for now.
        // In a real app, you would send a confirmation email and set status to 'pending'.
        $status = 'active';

        $stmt = $mysqli->prepare("INSERT INTO users (name, email, password, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $hashed_password, $status);

        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            $invite_token = $_POST['invite_token'] ?? null;
            if ($invite_token) {
                // Find and process invitation
                $invite_q = $mysqli->prepare("SELECT id, team_id FROM team_invitations WHERE token = ? AND status = 'pending' AND expires_at > NOW()");
                $invite_q->bind_param("s", $invite_token);
                $invite_q->execute();
                $invitation = $invite_q->get_result()->fetch_assoc();
                if ($invitation) {
                    $team_id = $invitation['team_id'];
                    $update_user = $mysqli->prepare("UPDATE users SET team_id = ?, team_role = 'member' WHERE id = ?");
                    $update_user->bind_param("ii", $team_id, $user_id);
                    $update_user->execute();

                    $update_invite = $mysqli->prepare("UPDATE team_invitations SET status = 'accepted' WHERE id = ?");
                    $update_invite->bind_param("i", $invitation['id']);
                    $update_invite->execute();
                }
            }

            $success = true;
            // TODO: Send welcome email.
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
    }
}

include __DIR__ . '/../includes/header_public.php';
?>

<div class="container page-content">
    <div class="auth-form">
        <h2>Create Your Account</h2>
        <?php if ($success): ?>
            <div class="alert alert-success">
                Registration successful! You can now <a href="/login">log in</a>.
            </div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form action="/register" method="POST">
                <input type="hidden" name="invite_token" value="<?php echo htmlspecialchars($_GET['invite_token'] ?? ''); ?>">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" name="name" id="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>
                <button type="submit" class="cta-button">Create Account</button>
            </form>
        <?php endif; ?>
        <p class="auth-switch">Already have an account? <a href="/login">Log in here</a>.</p>

        <div class="social-login">
            <p>Or</p>
            <a href="/google-login.php" class="btn-google">
                <i class="fab fa-google"></i> Sign up with Google
            </a>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer_public.php';
?>
<style>
.auth-form {
    max-width: 400px;
    margin: 40px auto;
    padding: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border-radius: 8px;
}
</style>
