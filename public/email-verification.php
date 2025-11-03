<?php
session_start();
require_once '../config/db.php';
require_once '../src/lib/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$message = '';

$price_per_verification = (float)get_setting('price_per_verification', $mysqli, 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_emails'])) {
    $job_name = trim($_POST['job_name'] ?? 'Verification Job');
    $emails_raw = trim($_POST['emails'] ?? '');
    $emails = preg_split('/[\s,]+/', $emails_raw);
    $emails = array_filter($emails, function($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    });
    $email_count = count($emails);

    if ($email_count > 0) {
        $total_cost = $email_count * $price_per_verification;

        $stmt = $mysqli->prepare("SELECT credit_balance FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user_balance = $stmt->get_result()->fetch_assoc()['credit_balance'];

        if ($user_balance >= $total_cost) {
            $mysqli->begin_transaction();
            try {
                $mysqli->query("UPDATE users SET credit_balance = credit_balance - $total_cost WHERE id = $user_id");

                $stmt = $mysqli->prepare("INSERT INTO verification_jobs (user_id, job_name, total_emails, cost_in_credits) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('isid', $user_id, $job_name, $email_count, $total_cost);
                $stmt->execute();
                $job_id = $stmt->insert_id;

                $queue_stmt = $mysqli->prepare("INSERT INTO verification_queue (job_id, email_address) VALUES (?, ?)");
                foreach ($emails as $email) {
                    $queue_stmt->bind_param('is', $job_id, $email);
                    $queue_stmt->execute();
                }

                $mysqli->commit();
                $message = "Verification job '{$job_name}' has been queued with {$email_count} emails.";

            } catch (Exception $e) {
                $mysqli->rollback();
                $message = "An error occurred.";
            }
        } else {
            $message = "Insufficient credits.";
        }
    } else {
        $message = "Please enter at least one valid email address.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Email Verification</title></head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include 'includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Bulk Email Verification</h1>
            <?php if ($message): ?><p><?php echo $message; ?></p><?php endif; ?>

            <form action="email-verification.php" method="post">
                <input type="hidden" name="verify_emails" value="1">
                <input type="text" name="job_name" placeholder="Job Name" required><br>
                <textarea name="emails" rows="10" placeholder="Paste emails here, one per line or separated by commas."></textarea><br>
                <p>Cost per email: <?php echo $price_per_verification; ?> credits</p>
                <button type="submit">Verify Emails</button>
            </form>

            <!-- We will list jobs here -->
        </main>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
