<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /public/login');
    exit;
}
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];
$team_owner_id = $_SESSION['team_owner_id'];
$message = '';

$price_per_verification = (float)get_setting('price_per_verification', $mysqli, 0.5);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_emails'])) {
    $job_name = trim($_POST['job_name'] ?? 'Verification Job');
    $emails_raw = trim($_POST['emails'] ?? '');
    $emails = [];

    // Handle pasted emails
    if (!empty($emails_raw)) {
        $emails = array_merge($emails, preg_split('/[\s,]+/', $emails_raw));
    }

    // Handle CSV upload
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, "r");
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (isset($data[0])) { // Assuming email is in the first column
                $emails[] = $data[0];
            }
        }
        fclose($handle);
    }

    $emails = array_unique(array_filter(array_map('trim', $emails), function($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }));
    $email_count = count($emails);

    if ($email_count > 0) {
        $total_cost = $email_count * $price_per_verification;

        $stmt_balance = $mysqli->prepare("SELECT credit_balance FROM users WHERE id = ?");
        $stmt_balance->bind_param('i', $team_owner_id);
        $stmt_balance->execute();
        $user_balance = (float)$stmt_balance->get_result()->fetch_assoc()['credit_balance'];

        if ($user_balance >= $total_cost) {
            $mysqli->begin_transaction();
            try {
                $update_credits_stmt = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance - ? WHERE id = ?");
                $update_credits_stmt->bind_param('di', $total_cost, $team_owner_id);
                $update_credits_stmt->execute();

                $stmt_job = $mysqli->prepare("INSERT INTO verification_jobs (user_id, team_id, job_name, total_emails, cost_in_credits) VALUES (?, ?, ?, ?, ?)");
                $stmt_job->bind_param('iisid', $user_id, $team_id, $job_name, $email_count, $total_cost);
                $stmt_job->execute();
                $job_id = $stmt_job->insert_id;

                $queue_stmt = $mysqli->prepare("INSERT INTO verification_queue (job_id, email_address) VALUES (?, ?)");
                foreach ($emails as $email) {
                    $queue_stmt->bind_param('is', $job_id, $email);
                    $queue_stmt->execute();
                }

                $mysqli->commit();
                $message = "Verification job '{$job_name}' has been queued with {$email_count} unique, valid emails.";
            } catch (Exception $e) {
                $mysqli->rollback();
                $message = "An error occurred while queuing the job.";
            }
        } else {
            $message = "Insufficient credits. You need {$total_cost} credits, but you only have {$user_balance}.";
        }
    } else {
        $message = "Please enter at least one valid email address.";
    }
}

// Fetch past verification jobs for the team
$jobs_result = $mysqli->prepare("SELECT job_name, total_emails, cost_in_credits, status, created_at FROM verification_jobs WHERE team_id = ? ORDER BY created_at DESC");
$jobs_result->bind_param('i', $team_id);
$jobs_result->execute();
$jobs = $jobs_result->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Email Verification</title><link rel="stylesheet" href="/public/css/dashboard_style.css"></head>
<body>
    <?php include APP_ROOT . '/public/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Bulk Email Verification</h1>
            <?php if ($message): ?><div class="message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <div class="card">
                <h2>New Verification Job</h2>
                <form action="/public/email-verification" method="post">
                    <input type="hidden" name="verify_emails" value="1">
                    <div class="form-group"><label for="job_name">Job Name</label><input type="text" id="job_name" name="job_name" required></div>
                    <div class="form-group"><label for="emails">Paste Emails</label><textarea id="emails" name="emails" rows="10" placeholder="Paste emails here, one per line or separated by commas."></textarea></div>
                    <p><strong>OR</strong></p>
                     <div class="form-group"><label for="csv_file">Upload CSV</label><input type="file" id="csv_file" name="csv_file" accept=".csv"></div>
                    <p>Cost per email: <strong><?php echo $price_per_verification; ?> credits</strong></p>
                    <div id="cost-estimator">Emails detected: 0 | Estimated Cost: 0.00 credits</div>
                    <button type="submit">Queue Verification Job</button>
                </form>
            </div>
            <hr>
            <h2>Your Verification Jobs</h2>
            <div class="table-container">
                <table>
                    <thead><tr><th>Job Name</th><th>Emails</th><th>Cost</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php while($job = $jobs->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($job['job_name']); ?></td>
                        <td><?php echo $job['total_emails']; ?></td>
                        <td><?php echo number_format($job['cost_in_credits'], 4); ?></td>
                        <td><?php echo htmlspecialchars($job['status']); ?></td>
                        <td><?php echo $job['created_at']; ?></td>
                        <td>
                            <?php if ($job['status'] === 'Completed'): ?>
                            <a href="/public/download-verification-results?job_id=<?php echo $job['id']; ?>" class="button-secondary">Download</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <script>
        const emailsTextarea = document.getElementById('emails');
        const costDiv = document.getElementById('cost-estimator');
        const pricePerEmail = <?php echo $price_per_verification; ?>;

        emailsTextarea.addEventListener('input', () => {
            const text = emailsTextarea.value;
            const emails = text.split(/[\s,]+/).filter(e => e.length > 2 && e.includes('@'));
            const emailCount = emails.length;
            const totalCost = (emailCount * pricePerEmail).toFixed(4);
            costDiv.textContent = `Emails detected: ${emailCount} | Estimated Cost: ${totalCost} credits`;
        });
    </script>
    <?php include APP_ROOT . '/public/includes/footer.php'; ?>
</body>
</html>
