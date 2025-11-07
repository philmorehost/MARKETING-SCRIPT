<?php
// src/pages/dashboard.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "Dashboard";

// --- Data Fetching for Stats ---
$stats = [
    'contacts' => 0,
    'emails_verified' => 0,
    'emails_sent' => 0,
    'sms_sent' => 0,
    'whatsapps_sent' => 0,
    'landing_pages' => 0,
    'social_posts' => 0,
    'qr_codes' => 0,
];
$team_id_condition = $user['team_id'] ? "team_id = " . $user['team_id'] : "user_id = " . $user['id'];

// This can be optimized into a single query with UNION ALL
$stats['contacts'] = $mysqli->query("SELECT COUNT(*) FROM contacts WHERE $team_id_condition")->fetch_row()[0];
$stats['emails_verified'] = $mysqli->query("SELECT SUM(total_emails) FROM verification_jobs WHERE $team_id_condition")->fetch_row()[0] ?? 0;
// ... add other queries for emails_sent, sms_sent etc. later


// Fetch recent verification jobs
$recent_jobs_query = $mysqli->query("SELECT * FROM verification_jobs WHERE $team_id_condition ORDER BY created_at DESC LIMIT 5");
$recent_jobs = $recent_jobs_query->fetch_all(MYSQLI_ASSOC);


include __DIR__ . '/../includes/header_app.php';
?>

<div class="container app-content">

    <?php // --- First-Time Login Wizard --- ?>
    <?php if (!$user['first_login_wizard_complete']): ?>
    <div class="wizard-overlay">
        <div class="wizard-card">
            <h2 id="wizard-title">Welcome to the Platform!</h2>
            <div id="wizard-step-1">
                <p>Let's get you started. First, create a contact list to store your audience.</p>
                <input type="text" id="wizard-list-name" placeholder="e.g., Newsletter Subscribers">
                <button onclick="createList()" class="btn btn-primary">Create List</button>
            </div>
            <div id="wizard-step-2" style="display:none;">
                 <p>Great! Your list has been created. The next step is to add credits to your account so you can start using our services.</p>
                 <a href="/buy-credits" class="btn btn-primary">Buy Credits</a>
            </div>
             <a href="#" onclick="skipWizard()" class="wizard-skip">I'll do this later</a>
        </div>
    </div>
    <?php endif; ?>


    <h1>Dashboard</h1>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Available Credits</h3>
            <p><?php echo number_format($user['credit_balance'], 2); ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Contacts</h3>
            <p><?php echo number_format($stats['contacts']); ?></p>
        </div>
        <div class="stat-card">
            <h3>Emails Verified</h3>
            <p><?php echo number_format($stats['emails_verified']); ?></p>
        </div>
        <div class="stat-card">
            <h3>Emails Sent</h3>
            <p><?php echo number_format($stats['emails_sent']); ?></p>
        </div>
    </div>

    <div class="charts-section">
        <div class="card">
            <h3>Credit Spending (Last 30 Days)</h3>
            <canvas id="creditSpendingChart"></canvas>
        </div>
    </div>

    <div class="recent-activity">
        <div class="card">
            <h3>Recent Verification Jobs</h3>
            <table>
                <thead>
                    <tr>
                        <th>Job Name</th>
                        <th>Date</th>
                        <th>Total Emails</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_jobs)): ?>
                        <tr><td colspan="5">You haven't run any verification jobs yet.</td></tr>
                    <?php else: ?>
                        <?php foreach($recent_jobs as $job): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($job['job_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($job['created_at'])); ?></td>
                            <td><?php echo number_format($job['total_emails']); ?></td>
                            <td><span class="status-badge <?php echo $job['status']; ?>"><?php echo ucfirst($job['status']); ?></span></td>
                            <td>
                                <?php if ($job['status'] === 'completed'): ?>
                                    <a href="/download-verification.php?job_id=<?php echo $job['id']; ?>" class="btn btn-sm">Download</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Dummy data for chart - this would be fetched via AJAX
const creditSpendingData = {
    labels: ['Verification', 'Email', 'SMS', 'WhatsApp'],
    datasets: [{
        label: 'Credits Spent',
        data: [1250, 1980, 850, 400],
        backgroundColor: [
            'rgba(255, 99, 132, 0.7)',
            'rgba(54, 162, 235, 0.7)',
            'rgba(255, 206, 86, 0.7)',
            'rgba(75, 192, 192, 0.7)'
        ],
    }]
};
new Chart(document.getElementById('creditSpendingChart'), {
    type: 'doughnut',
    data: creditSpendingData,
});


function createList() {
    const listName = document.getElementById('wizard-list-name').value;
    if (!listName) { alert('Please enter a list name.'); return; }

    fetch('/ajax/wizard_create_list', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ list_name: listName })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('wizard-step-1').style.display = 'none';
            document.getElementById('wizard-step-2').style.display = 'block';
            document.getElementById('wizard-title').innerText = "Step 2: Fund Your Account";
        } else {
            alert(data.message || 'Could not create list.');
        }
    });
}

function skipWizard() {
     fetch('/ajax/wizard_complete', { method: 'POST' })
    .then(() => {
        document.querySelector('.wizard-overlay').style.display = 'none';
    });
}

</script>

<?php
include __DIR__ . '/../includes/footer_app.php';
?>
