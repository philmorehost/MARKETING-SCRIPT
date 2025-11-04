<?php
require_once '../config/db.php';
require_once '../vendor/autoload.php';
require_once '../src/lib/functions.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error_message = '';
$success_message = '';
$invite_token = $_GET['invite_token'] ?? null;
$email_from_invite = '';
$team_id_from_invite = null;

if ($mysqli->connect_error) {
    die("Database connection error.");
}

if ($invite_token) {
    // Validate token
    $stmt = $mysqli->prepare("SELECT team_id, email FROM team_invitations WHERE token = ? AND status = 'pending' AND expires_at > NOW()");
    $stmt->bind_param('s', $invite_token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $invitation = $result->fetch_assoc();
        $email_from_invite = $invitation['email'];
        $team_id_from_invite = $invitation['team_id'];
    } else {
        $error_message = "This invitation is invalid or has expired.";
        $invite_token = null; // Invalidate the token to hide invite-specific logic
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // If this is from an invite, the email must match
    if ($invite_token && $email !== $email_from_invite) {
        $error_message = "Registration email must match the invitation email.";
    } elseif (empty($name) || empty($email) || empty($password)) {
        $error_message = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } else {
        // Check if email already exists
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error_message = "An account with this email already exists.";
        } else {
            // Start transaction
            $mysqli->begin_transaction();
            try {
                // Create user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                // Set team details if from a valid invite
                $team_id = $team_id_from_invite;
                $team_role = $team_id ? 'member' : null; // or 'owner' if it's the first user of a new team
                $status = 'active'; // Or 'pending' for email confirmation

                $stmt = $mysqli->prepare("INSERT INTO users (name, email, password, status, team_id, team_role) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssis', $name, $email, $hashed_password, $status, $team_id, $team_role);
                $stmt->execute();

                // If from invite, update invitation status
                if ($invite_token && $team_id) {
                    $stmt_update = $mysqli->prepare("UPDATE team_invitations SET status = 'accepted' WHERE token = ?");
                    $stmt_update->bind_param('s', $invite_token);
                    $stmt_update->execute();
                }

                $mysqli->commit();
                $success_message = "Registration successful! You can now log in.";
                // You might want to auto-login the user here and redirect to dashboard
                header("Refresh:3; url=login.php");

            } catch (mysqli_sql_exception $exception) {
                $mysqli->rollback();
                $error_message = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <link rel="stylesheet" href="css/public_style.css">
</head>
<body>
    <div class="auth-container">
        <h2><?php echo $invite_token ? 'Join Your Team' : 'Create a New Account'; ?></h2>
        <?php if ($error_message): ?><p class="message error"><?php echo $error_message; ?></p><?php endif; ?>
        <?php if ($success_message): ?><p class="message success"><?php echo $success_message; ?></p><?php endif; ?>

        <?php if (!$success_message): ?>
        <form action="/register?invite_token=<?php echo htmlspecialchars($invite_token ?? ''); ?>" method="post">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email_from_invite); ?>" <?php echo $invite_token ? 'readonly' : ''; ?> required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="button">Register</button>
        </form>
        <?php endif; ?>
        <div class="footer-links">
            <a href="login.php">Already have an account? Login</a>
        </div>
    </div>
</body>
</html>
