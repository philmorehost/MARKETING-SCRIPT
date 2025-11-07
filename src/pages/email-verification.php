<?php
// src/pages/email-verification.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "Bulk Email Verification";
$errors = [];
$success = false;

$cost_per_verification = get_setting('price_per_verification', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $job_name = trim($_POST['job_name'] ?? 'Verification Job');
    $emails_textarea = trim($_POST['emails_list'] ?? '');
    $email_file = $_FILES['email_csv'] ?? null;

    $emails = [];
    if (!empty($emails_textarea)) {
        $emails = array_filter(array_map('trim', explode("\n", $emails_textarea)));
    } elseif ($email_file && $email_file['error'] === UPLOAD_ERR_OK) {
        if (($handle = fopen($email_file['tmp_name'], "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Assuming email is in the first column
                if (filter_var($data[0], FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $data[0];
                }
            }
            fclose($handle);
        }
    }

    $total_emails = count($emails);
    if ($total_emails === 0) {
        $errors[] = "Please provide at least one email address to verify.";
    }

    $total_cost = $total_emails * $cost_per_verification;

    if ($user['credit_balance'] < $total_cost) {
        $errors[] = "Insufficient credits. You need " . number_format($total_cost) . " credits, but you only have " . number_format($user['credit_balance']) . ".";
    }

    if (empty($errors)) {
        // 1. Deduct credits
        $mysqli->begin_transaction();
        try {
            $deduct_stmt = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance - ? WHERE id = ?");
            $deduct_stmt->bind_param("di", $total_cost, $user['id']);
            $deduct_stmt->execute();

            // 2. Create verification job
            $job_stmt = $mysqli->prepare("INSERT INTO verification_jobs (user_id, team_id, job_name, total_emails, cost_in_credits) VALUES (?, ?, ?, ?, ?)");
            $job_stmt->bind_param("iisid", $user['id'], $user['team_id'], $job_name, $total_emails, $total_cost);
            $job_stmt->execute();
            $job_id = $job_stmt->insert_id;

            // 3. Add emails to the queue
            $queue_stmt = $mysqli->prepare("INSERT INTO verification_queue (job_id, email_address) VALUES (?, ?)");
            foreach ($emails as $email) {
                $queue_stmt->bind_param("is", $job_id, $email);
                $queue_stmt->execute();
            }

            // 4. Record transaction
            $trans_stmt = $mysqli->prepare("INSERT INTO transactions (user_id, type, description, amount_credits, status) VALUES (?, 'spend_verify', ?, ?, 'completed')");
            $description = "Email verification job: " . $job_name;
            $trans_stmt->bind_param("isd", $user['id'], $description, $total_cost);
            $trans_stmt->execute();

            $mysqli->commit();
            $success = true;

        } catch (Exception $e) {
            $mysqli->rollback();
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}

include __DIR__ . '/../includes/header_app.php';
?>
<div class="container app-content">
    <h1>Bulk Email Verification</h1>
    <p>Clean your email lists to improve deliverability. Cost: <?php echo $cost_per_verification; ?> credit(s) per email.</p>

    <?php if ($success): ?>
        <div class="alert alert-success">
            Your verification job has been successfully queued! You can track its progress on your dashboard.
        </div>
    <?php else: ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="/email-verification" method="POST" enctype="multipart/form-data" class="card">
            <div class="form-group">
                <label for="job_name">Job Name (Optional)</label>
                <input type="text" id="job_name" name="job_name" placeholder="e.g., My Newsletter List">
            </div>

            <div class="form-group">
                <label for="emails_list">Paste Emails</label>
                <p class="form-hint">Paste one email address per line.</p>
                <textarea name="emails_list" id="emails_list" rows="10" placeholder="test1@example.com&#10;test2@example.com"></textarea>
            </div>

            <div class="text-center" style="margin: 20px 0;">OR</div>

            <div class="form-group">
                <label for="email_csv">Upload a CSV File</label>
                 <p class="form-hint">Upload a CSV file with one column containing email addresses.</p>
                <input type="file" name="email_csv" id="email_csv" accept=".csv">
            </div>

            <button type="submit" class="btn btn-primary">Verify List</button>
        </form>
    <?php endif; ?>
</div>
<?php
include __DIR__ . '/../includes/footer_app.php';
?>
