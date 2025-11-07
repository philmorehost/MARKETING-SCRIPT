<?php
// src/pages/landing-pages.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "Landing Pages";

// Fetch user's landing pages
$team_id_condition = $user['team_id'] ? "team_id = " . $user['team_id'] : "user_id = " . $user['id'];
$pages_query = $mysqli->query("SELECT * FROM landing_pages WHERE $team_id_condition ORDER BY created_at DESC");
$landing_pages = $pages_query->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header_app.php';
?>

<div class="container app-content">
    <div class="page-header">
        <h1>Landing Pages</h1>
        <a href="/create-landing-page" class="btn btn-primary">Create New Page</a>
    </div>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>Page Name</th>
                    <th>Public URL</th>
                    <th>Status</th>
                    <th>Leads</th>
                    <th>Created On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($landing_pages)): ?>
                    <tr><td colspan="6">You haven't created any landing pages yet.</td></tr>
                <?php else: foreach ($landing_pages as $page): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($page['headline']); ?></td>
                        <td><a href="/p/<?php echo $page['page_slug']; ?>" target="_blank">/p/<?php echo $page['page_slug']; ?></a></td>
                        <td><span class="status-badge <?php echo $page['status']; ?>"><?php echo ucfirst($page['status']); ?></span></td>
                        <td>0</td>
                        <td><?php echo date('M d, Y', strtotime($page['created_at'])); ?></td>
                        <td>
                            <a href="/edit-landing-page?id=<?php echo $page['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer_app.php';
?>
