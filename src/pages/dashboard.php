<?php
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $mysqli->prepare("SELECT credit_balance, first_login_wizard_complete FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$show_wizard = !$user['first_login_wizard_complete'];

// Fetch user's team ID
$stmt_team = $mysqli->prepare("SELECT team_id FROM users WHERE id = ?");
$stmt_team->bind_param('i', $user_id);
$stmt_team->execute();
$team_result = $stmt_team->get_result()->fetch_assoc();
$team_id = $team_result['team_id'];

// Function to fetch a single count
function get_count($mysqli, $sql, $team_id) {
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $team_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['c'];
}

// Fetch real stats using prepared statements
$stats = [
    'total_contacts' => get_count($mysqli, "SELECT COUNT(*) as c FROM contacts WHERE team_id = ?", $team_id),
    'emails_verified' => get_count($mysqli, "SELECT COUNT(*) as c FROM verification_queue vq JOIN verification_jobs vj ON vq.job_id = vj.id WHERE vj.team_id = ? AND vq.status = 'valid'", $team_id),
    'emails_sent' => get_count($mysqli, "SELECT COUNT(*) as c FROM campaign_queue cq JOIN campaigns cmp ON cq.campaign_id = cmp.id WHERE cmp.team_id = ? AND cq.status = 'sent'", $team_id),
    'sms_sent' => get_count($mysqli, "SELECT COUNT(*) as c FROM sms_queue sq JOIN sms_campaigns sc ON sq.sms_campaign_id = sc.id WHERE sc.team_id = ? AND sq.status = 'sent'", $team_id),
    'whatsapps_sent' => get_count($mysqli, "SELECT COUNT(*) as c FROM whatsapp_queue wq JOIN whatsapp_campaigns wc ON wq.campaign_id = wc.id WHERE wc.team_id = ? AND wq.status = 'sent'", $team_id),
    'active_landing_pages' => get_count($mysqli, "SELECT COUNT(*) as c FROM landing_pages WHERE team_id = ? AND status = 'published'", $team_id),
    'social_posts_sent' => get_count($mysqli, "SELECT COUNT(*) as c FROM social_posts_queue WHERE team_id = ? AND status = 'sent'", $team_id),
    'qr_codes_generated' => get_count($mysqli, "SELECT COUNT(*) as c FROM qr_codes WHERE team_id = ?", $team_id),
];

// Fetch recent verification jobs
$jobs_stmt = $mysqli->prepare("SELECT id, job_name as name, created_at, status, (SELECT COUNT(*) FROM verification_queue WHERE job_id = vj.id) as total, (SELECT COUNT(*) FROM verification_queue WHERE job_id = vj.id AND status != 'pending') as processed FROM verification_jobs vj WHERE team_id = ? ORDER BY created_at DESC LIMIT 5");
$jobs_stmt->bind_param('i', $team_id);
$jobs_stmt->execute();
$verification_jobs_result = $jobs_stmt->get_result();
$verification_jobs = [];
while ($job = $verification_jobs_result->fetch_assoc()) {
    $job['progress'] = $job['total'] > 0 ? round(($job['processed'] / $job['total']) * 100) : 0;

    // Fetch stats for the pie chart for the most recent job
    if (empty($verification_jobs)) {
        $stats_stmt = $mysqli->prepare("SELECT status, COUNT(*) as c FROM verification_queue WHERE job_id = ? GROUP BY status");
        $stats_stmt->bind_param('i', $job['id']);
        $stats_stmt->execute();
        $stats_result = $stats_stmt->get_result();
        $job['stats'] = [];
        while($row = $stats_result->fetch_assoc()) {
            $job['stats'][$row['status']] = $row['c'];
        }
    }

    $verification_jobs[] = $job;
}


// Fetch chart data for the last 30 days
$chart_sql = "SELECT type, SUM(amount_credits) as total_credits FROM transactions WHERE team_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND type LIKE 'spend_%' GROUP BY type";
$chart_stmt = $mysqli->prepare($chart_sql);
$chart_stmt->bind_param('i', $team_id);
$chart_stmt->execute();
$chart_result = $chart_stmt->get_result();
$spending_data = [];
while($row = $chart_result->fetch_assoc()){
    $spending_data[str_replace('spend_', '', $row['type'])] = $row['total_credits'];
}

$chart_data = [
    'labels' => ['Email', 'SMS', 'Verification', 'WhatsApp', 'Landing Page', 'AI', 'Social Post', 'QR Code'],
    'data' => [
        $spending_data['email'] ?? 0,
        $spending_data['sms'] ?? 0,
        $spending_data['verify'] ?? 0,
        $spending_data['whatsapp'] ?? 0,
        $spending_data['landing_page'] ?? 0,
        $spending_data['ai'] ?? 0,
        $spending_data['social_post'] ?? 0,
        $spending_data['qr_code'] ?? 0,
    ]
];


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="css/dashboard_style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include APP_ROOT . '/public/includes/header.php'; ?>

    <div class="user-container">
        <aside class="sidebar">
            <?php include APP_ROOT . '/public/includes/sidebar.php'; ?>
        </aside>
        <main class="main-content">
            <h1>Dashboard</h1>

            <?php if ($show_wizard): ?>
            <div id="wizard-modal" class="modal-overlay" style="display: flex;">
                <div class="modal-content">
                    <div id="wizard-step-1" class="wizard-step">
                        <h2>Welcome to the Platform!</h2>
                        <p>Let's get you started in just a few quick steps.</p>
                        <button onclick="nextStep(2)">Start Setup</button>
                    </div>
                    <div id="wizard-step-2" class="wizard-step" style="display:none;">
                        <h2>Step 1: Create a Contact List</h2>
                        <p>This is where you'll store your contacts. Give your first list a name.</p>
                        <input type="text" id="wizard-list-name" placeholder="e.g., Newsletter Subscribers">
                        <button onclick="createList()">Create List & Continue</button>
                    </div>
                    <div id="wizard-step-3" class="wizard-step" style="display:none;">
                        <h2>Step 2: Get Some Credits</h2>
                        <p>Our platform is pay-as-you-go. Buy credits to use for email, SMS, and other services.</p>
                        <a href="buy-credits.php" class="button-primary">Go to "Buy Credits"</a>
                        <button onclick="finishWizard()">I'll do this later</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="card main-credits-card">
                    <h3>Available Credits</h3>
                    <p><?php echo number_format($user['credit_balance'], 4); ?></p>
                    <a href="buy-credits.php" class="button">Buy More</a>
                </div>
                <div class="card">
                    <h3>Total Contacts</h3>
                    <p><?php echo $stats['total_contacts']; ?></p>
                </div>
                <div class="card">
                    <h3>Emails Verified</h3>
                    <p><?php echo $stats['emails_verified']; ?></p>
                </div>
                <div class="card">
                    <h3>Emails Sent</h3>
                    <p><?php echo $stats['emails_sent']; ?></p>
                </div>
                <div class="card">
                    <h3>SMS Sent</h3>
                    <p><?php echo number_format($stats['sms_sent']); ?></p>
                </div>
                 <div class="card">
                    <h3>WhatsApp Messages Sent</h3>
                    <p><?php echo number_format($stats['whatsapps_sent']); ?></p>
                </div>
                 <div class="card">
                    <h3>Active Landing Pages</h3>
                    <p><?php echo number_format($stats['active_landing_pages']); ?></p>
                </div>
                 <div class="card">
                    <h3>Social Posts Sent</h3>
                    <p><?php echo number_format($stats['social_posts_sent']); ?></p>
                </div>
                 <div class="card">
                    <h3>QR Codes Generated</h3>
                    <p><?php echo number_format($stats['qr_codes_generated']); ?></p>
                </div>
            </div>

            <div class="charts-section">
                <div class="chart-container card">
                    <h3>Credit Spending (Last 30 Days)</h3>
                    <canvas id="creditSpendingChart"></canvas>
                </div>
                <div class="chart-container card">
                    <h3>Last Verification Job</h3>
                    <canvas id="verificationPieChart"></canvas>
                </div>
            </div>

            <div class="recent-jobs-section card">
                <div class="card-header">
                    <h2>My Verification Jobs</h2>
                    <input type-="text" id="job-search" placeholder="Search jobs...">
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Job Name</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>% Complete</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($verification_jobs)): ?>
                        <tr>
                            <td colspan="5">You haven't run any verification jobs yet.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($verification_jobs as $job): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($job['name']); ?></td>
                                <td><?php echo date("Y-m-d H:i", strtotime($job['created_at'])); ?></td>
                                <td><span class="status-badge status-<?php echo strtolower($job['status']); ?>"><?php echo htmlspecialchars($job['status']); ?></span></td>
                                <td><?php echo $job['progress']; ?>%</td>
                                <td>
                                    <?php if ($job['status'] === 'Completed'): ?>
                                    <a href="download_verification.php?job_id=<?php echo $job['id']; ?>" class="button-secondary">Download Results</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <?php include APP_ROOT . '/public/includes/footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Credit Spending Chart
        const creditCtx = document.getElementById('creditSpendingChart').getContext('2d');
        new Chart(creditCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_data['labels']); ?>,
                datasets: [{
                    label: 'Credits Spent',
                    data: <?php echo json_encode($chart_data['data']); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Verification Pie Chart
        const verificationCtx = document.getElementById('verificationPieChart').getContext('2d');
        new Chart(verificationCtx, {
            type: 'pie',
            data: {
                labels: ['Valid', 'Invalid', 'Risky'],
                datasets: [{
                    label: 'Verification Results',
                    data: [
                        <?php echo $verification_jobs[0]['stats']['valid'] ?? 0; ?>,
                        <?php echo $verification_jobs[0]['stats']['invalid'] ?? 0; ?>,
                        <?php echo $verification_jobs[0]['stats']['risky'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(255, 206, 86, 0.7)'
                    ],
                    borderColor: '#fff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true
            }
        });
    });

    <?php if ($show_wizard): ?>
        function nextStep(step) {
            document.getElementById('wizard-step-' + (step - 1)).style.display = 'none';
            document.getElementById('wizard-step-' + step).style.display = 'block';
        }

        function createList() {
            const listName = document.getElementById('wizard-list-name').value;
            if (listName.trim() === '') {
                alert('Please enter a list name.');
                return;
            }
            // Note: This requires an AJAX endpoint to exist
            fetch('ajax/wizard_create_list.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'list_name=' + encodeURIComponent(listName)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    nextStep(3);
                } else {
                    alert('Error: ' + (data.error || 'Could not create list.'));
                }
            })
            .catch(() => alert('An unknown error occurred.'));
        }

        function finishWizard() {
             // Note: This requires an AJAX endpoint to exist
            fetch('ajax/wizard_complete.php', { method: 'POST' })
            .then(() => {
                document.getElementById('wizard-modal').style.display = 'none';
            });
        }
    <?php endif; ?>
    </script>
</body>
</html>
