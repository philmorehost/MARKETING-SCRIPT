<?php
// src/pages/sms-campaigns.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "Bulk SMS Campaigns";

// Fetch contact lists
$team_id_condition = $user['team_id'] ? "team_id = " . $user['team_id'] : "user_id = " . $user['id'];
$lists_query = $mysqli->query("SELECT id, list_name FROM contact_lists WHERE $team_id_condition ORDER BY list_name ASC");
$contact_lists = $lists_query->fetch_all(MYSQLI_ASSOC);

$cost_per_sms_page = get_setting('price_per_sms_page', 5);
$sender_id = get_setting('philmorsms_sender_id');

include __DIR__ . '/../includes/header_app.php';
?>

<div class="container app-content">
    <h1>Bulk SMS Service</h1>
    <div class="card">
        <form action="/send-sms-campaign" method="POST" id="smsCampaignForm">
            <div class="form-group">
                <label for="sender_id">Sender ID</label>
                <input type="text" name="sender_id" id="sender_id" class="form-control" value="<?php echo htmlspecialchars($sender_id); ?>" required>
            </div>

            <div class="form-group">
                <label for="message">Message</label>
                 <button type="button" class="btn btn-sm btn-info" onclick="openAiModal()">Generate with AI</button>
                <textarea name="message" id="message" class="form-control" rows="5" required></textarea>
                <div id="sms-counter">Characters: 0 | Pages: 1 | Credits per recipient: <?php echo $cost_per_sms_page; ?></div>
            </div>

            <div class="form-group">
                <label>Recipients</label>
                <p class="form-hint">Select contact lists or paste numbers below (one per line).</p>
                <div class="recipient-lists">
                    <?php foreach($contact_lists as $list): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="recipient_lists[]" value="<?php echo $list['id']; ?>" id="list_<?php echo $list['id']; ?>">
                        <label class="form-check-label" for="list_<?php echo $list['id']; ?>">
                            <?php echo htmlspecialchars($list['list_name']); ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <textarea name="manual_recipients" class="form-control" rows="5" placeholder="2348012345678..."></textarea>
            </div>

            <button type="submit" class="btn btn-success">Send SMS</button>
        </form>
    </div>
</div>

<script>
document.getElementById('message').addEventListener('input', function () {
    const message = this.value;
    const charCount = message.length;
    const sms_pages = charCount <= 160 ? 1 : Math.ceil(charCount / 153);
    const cost = sms_pages * <?php echo $cost_per_sms_page; ?>;

    document.getElementById('sms-counter').innerText = `Characters: ${charCount} | Pages: ${sms_pages} | Credits per recipient: ${cost}`;
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
    const prompt = document.getElementById('ai-prompt').value;
    const resultContainer = document.getElementById('ai-result-container');
    resultContainer.innerHTML = `<p><em>Generating content for: "${prompt}"...</em></p><p>Generated content would appear here.</p>`;
    // This is a simplified version. A real version would then let the user insert this text.
}
</script>

<?php
include __DIR__ . '/../includes/footer_app.php';
?>
