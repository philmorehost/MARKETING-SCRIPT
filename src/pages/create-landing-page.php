<?php
// src/pages/create-landing-page.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "Create Landing Page";
$cost_to_publish = get_setting('price_landing_page_publish', 100);

// Fetch contact lists
$team_id_condition = $user['team_id'] ? "team_id = " . $user['team_id'] : "user_id = " . $user['id'];
$lists_query = $mysqli->query("SELECT id, list_name FROM contact_lists WHERE $team_id_condition ORDER BY list_name ASC");
$contact_lists = $lists_query->fetch_all(MYSQLI_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $headline = trim($_POST['headline'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $list_id = $_POST['list_id'] ?? null;
    $status = $_POST['action'] === 'publish' ? 'published' : 'draft';

    $errors = [];
    if (empty($headline)) $errors[] = "Headline is required.";
    if (empty($list_id)) $errors[] = "You must select a contact list to add leads to.";

    if ($status === 'published' && $user['credit_balance'] < $cost_to_publish) {
        $errors[] = "You need $cost_to_publish credits to publish this page.";
    }

    if (empty($errors)) {
        $mysqli->begin_transaction();
        try {
            if ($status === 'published') {
                $mysqli->query("UPDATE users SET credit_balance = credit_balance - $cost_to_publish WHERE id = {$user['id']}");
            }

            $slug = uniqid(); // Simple unique slug
            $stmt = $mysqli->prepare("INSERT INTO landing_pages (user_id, team_id, list_id, page_slug, headline, content, status, cost_in_credits) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiissssd", $user['id'], $user['team_id'], $list_id, $slug, $headline, $content, $status, $cost_to_publish);
            $stmt->execute();

            if ($status === 'published') {
                 $description = "Published Landing Page: " . $headline;
                 $mysqli->query("INSERT INTO transactions (user_id, type, description, amount_credits, status) VALUES ({$user['id']}, 'spend_landing_page', '$description', $cost_to_publish, 'completed')");
            }

            $mysqli->commit();
            header('Location: /landing-pages');
            exit;
        } catch (Exception $e) {
            $mysqli->rollback();
            $errors[] = "An error occurred.";
        }
    }
}

include __DIR__ . '/../includes/header_app.php';
?>
<div class="container app-content">
    <h1>Create Landing Page</h1>
    <div class="card">
        <form action="/create-landing-page" method="POST">
            <div class="form-group">
                <label for="headline">Headline</label>
                <input type="text" name="headline" id="headline" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="content">Content</label>
                <textarea name="content" id="content" class="form-control" rows="8"></textarea>
            </div>
            <div class="form-group">
                <label for="list_id">Add Subscribers to List</label>
                <select name="list_id" id="list_id" class="form-control" required>
                    <option value="">-- Select a List --</option>
                    <?php foreach($contact_lists as $list): ?>
                    <option value="<?php echo $list['id']; ?>"><?php echo htmlspecialchars($list['list_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <p>Cost to publish: <?php echo $cost_to_publish; ?> credits</p>
            <button type="submit" name="action" value="publish" class="btn btn-success">Save & Publish</button>
            <button type="submit" name="action" value="draft" class="btn btn-secondary">Save as Draft</button>
        </form>
    </div>
</div>
<?php
include __DIR__ . '/../includes/footer_app.php';
?>
