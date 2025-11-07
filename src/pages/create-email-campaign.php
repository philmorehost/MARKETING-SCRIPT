<?php
// src/pages/create-email-campaign.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "Create Email Campaign";

// Fetch contact lists for the recipient selector
$team_id_condition = $user['team_id'] ? "team_id = " . $user['team_id'] : "user_id = " . $user['id'];
$lists_query = $mysqli->query("SELECT id, list_name FROM contact_lists WHERE $team_id_condition ORDER BY list_name ASC");
$contact_lists = $lists_query->fetch_all(MYSQLI_ASSOC);

$cost_per_email = get_setting('price_per_email_send', 1);

include __DIR__ . '/../includes/header_app.php';
?>

<div class="container app-content">
    <h1>Create Email Campaign</h1>

    <div class="card">
        <form action="/send-email-campaign" method="POST" id="emailCampaignForm">

            <div class="form-group">
                <label for="subject">Campaign Subject</label>
                <input type="text" name="subject" id="subject" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Email Content</label>
                <div>
                    <button type="button" class="btn btn-sm btn-info" onclick="openAiModal()">Generate with AI</button>
                </div>
                <textarea name="html_content" id="emailEditor"></textarea>
            </div>

            <div class="form-group">
                <label>Select Recipient Lists</label>
                <?php foreach($contact_lists as $list): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="recipient_lists[]" value="<?php echo $list['id']; ?>" id="list_<?php echo $list['id']; ?>">
                    <label class="form-check-label" for="list_<?php echo $list['id']; ?>">
                        <?php echo htmlspecialchars($list['list_name']); ?>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="campaign-summary">
                <p><strong>Cost:</strong> <span id="email-cost">0</span> credits (<?php echo $cost_per_email; ?> credit(s) per recipient)</p>
                <p><strong>Available Credits:</strong> <?php echo number_format($user['credit_balance']); ?></p>
            </div>

            <button type="submit" name="action" value="send" class="btn btn-success">Send Now</button>
            <button type="submit" name="action" value="schedule" class="btn btn-primary">Schedule</button>
        </form>
    </div>
</div>

<!-- TinyMCE -->
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    tinymce.init({
        selector: '#emailEditor',
        height: 500,
        menubar: false,
        plugins: [
            'advlist autolink lists link image charmap print preview anchor',
            'searchreplace visualblocks code fullscreen',
            'insertdatetime media table paste code help wordcount'
        ],
        toolbar: 'undo redo | formatselect | ' +
        'bold italic backcolor | alignleft aligncenter ' +
        'alignright alignjustify | bullist numlist outdent indent | ' +
        'removeformat | help',
        content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }'
    });
</script>

<!-- AI Modal -->
<div id="aiModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAiModal()">&times;</span>
        <h2>AI Content Helper</h2>
        <div class="form-group">
            <label for="ai-prompt">Enter your prompt</label>
            <textarea id="ai-prompt" rows="3" class="form-control"></textarea>
        </div>
        <button type="button" class="btn btn-primary" onclick="generateAiContent()">Generate</button>
        <hr>
        <div id="ai-result-container"></div>
    </div>
</div>

<script>
function openAiModal() { document.getElementById('aiModal').style.display = 'block'; }
function closeAiModal() { document.getElementById('aiModal').style.display = 'none'; }
function generateAiContent() {
    // In a real app, this would make an AJAX call to a backend endpoint
    // that interacts with the AI API and deducts credits.
    const prompt = document.getElementById('ai-prompt').value;
    const resultContainer = document.getElementById('ai-result-container');
    resultContainer.innerHTML = `<p><em>Generating content for: "${prompt}"...</em></p><p>Generated content would appear here.</p>`;
}
</script>

<?php
include __DIR__ . '/../includes/footer_app.php';
?>
