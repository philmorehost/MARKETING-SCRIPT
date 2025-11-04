<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];
$team_owner_id = $_SESSION['team_owner_id'];
$message = '';

// Fetch team's contact lists
$lists_result = $mysqli->prepare("SELECT id, list_name FROM contact_lists WHERE team_id = ?");
$lists_result->bind_param('i', $team_id);
$lists_result->execute();
$lists = $lists_result->get_result();

// Fetch cost from settings
$price_to_publish = (float)get_setting('price_landing_page_publish', $mysqli, 50);

// Handle Page Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_page'])) {
    $headline = trim($_POST['headline'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $list_id = (int)$_POST['list_id'];
    $form_fields = $_POST['form_fields'] ?? []; // Array of fields like ['email', 'first_name']
    $image = $_FILES['image'] ?? null;

    // Validation
    if (empty($headline) || empty($content) || empty($list_id) || empty($form_fields)) {
        $message = "Headline, content, a contact list, and at least one form field are required.";
    } else {
        $stmt_balance = $mysqli->prepare("SELECT credit_balance FROM users WHERE id = ?");
        $stmt_balance->bind_param('i', $team_owner_id);
        $stmt_balance->execute();
        $user_balance = (float)$stmt_balance->get_result()->fetch_assoc()['credit_balance'];

        if ($user_balance >= $price_to_publish) {
            $mysqli->begin_transaction();
            try {
                $update_credits_stmt = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance - ? WHERE id = ?");
                $update_credits_stmt->bind_param('di', $price_to_publish, $team_owner_id);
                $update_credits_stmt->execute();

                $image_path = null;
                if ($image && $image['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = APP_ROOT . '/uploads/landing_pages/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $filename = uniqid('lp_', true) . '.' . pathinfo($image['name'], PATHINFO_EXTENSION);
                    if (move_uploaded_file($image['tmp_name'], $upload_dir . $filename)) {
                        $image_path = '/uploads/landing_pages/' . $filename;
                    }
                }

                $page_slug = uniqid();
                $fields_json = json_encode($form_fields);
                $stmt_insert = $mysqli->prepare("INSERT INTO landing_pages (user_id, team_id, list_id, page_slug, headline, content, image_path, form_fields_json, status, cost_in_credits) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'published', ?)");
                $stmt_insert->bind_param('iiisssssd', $user_id, $team_id, $list_id, $page_slug, $headline, $content, $image_path, $fields_json, $price_to_publish);
                $stmt_insert->execute();

                $mysqli->commit();
                $message = "Landing page published successfully! URL: <a href='/p/{$page_slug}' target='_blank'>/p/{$page_slug}</a>";

            } catch (Exception $e) {
                $mysqli->rollback();
                $message = "An error occurred during publishing: " . $e->getMessage();
            }
        } else {
            $message = "Insufficient credits to publish a landing page. You need {$price_to_publish} credits.";
        }
    }
}

// Fetch existing landing pages for the team
$pages_result = $mysqli->prepare("SELECT page_slug, headline, status, created_at, (SELECT COUNT(*) FROM landing_page_stats WHERE landing_page_id = landing_pages.id AND type = 'submission') as submissions FROM landing_pages WHERE team_id = ? ORDER BY created_at DESC");
$pages_result->bind_param('i', $team_id);
$pages_result->execute();
$pages = $pages_result->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Landing Page Builder</title><link rel="stylesheet" href="/css/dashboard_style.css"></head>
<body>
    <?php include APP_ROOT . '/public/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Landing Page Builder</h1>
            <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>
            <div class="card">
                <h2>Create New Page</h2>
                <form action="/landing-pages" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="create_page" value="1">
                    <p>One-time Cost to Publish: <strong><?php echo $price_to_publish; ?> credits</strong></p>
                    <div class="form-group"><label for="headline">Headline</label><input type="text" id="headline" name="headline" required></div>
                    <div class="form-group"><label for="content">Content</label><textarea id="content" name="content" rows="8" required></textarea></div>
                    <div class="form-group"><label for="image">Image/Logo</label><input type="file" id="image" name="image" accept="image/*"></div>
                    <div class="form-group"><label for="list_id">Add Leads to Contact List</label>
                        <select id="list_id" name="list_id" required>
                            <option value="">-- Select a List --</option>
                            <?php while($list = $lists->fetch_assoc()): ?><option value="<?php echo $list['id']; ?>"><?php echo htmlspecialchars($list['list_name']); ?></option><?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Form Fields to Display</label>
                        <label><input type="checkbox" name="form_fields[]" value="email" checked> Email</label>
                        <label><input type="checkbox" name="form_fields[]" value="first_name"> First Name</label>
                        <label><input type="checkbox" name="form_fields[]" value="phone_number"> Phone Number</label>
                    </div>
                    <button type="submit">Publish Page</button>
                </form>
            </div>
            <hr>
            <h2>Your Landing Pages</h2>
            <div class="table-container">
                <table>
                    <thead><tr><th>Headline</th><th>URL</th><th>Submissions</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php if ($pages->num_rows > 0): ?>
                        <?php while($page = $pages->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($page['headline']); ?></td>
                            <td><a href="/p/<?php echo $page['page_slug']; ?>" target="_blank">/p/<?php echo $page['page_slug']; ?></a></td>
                            <td><?php echo $page['submissions']; ?></td>
                            <td><?php echo htmlspecialchars($page['status']); ?></td>
                            <td><?php echo $page['created_at']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5">You haven't created any landing pages yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <?php include APP_ROOT . '/public/includes/footer.php'; ?>
</body>
</html>
