<?php
// src/pages/qr-codes.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$page_title = "QR Code Generator";
$cost_per_qr = get_setting('price_per_qr_code', 25);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    $name = trim($_POST['name'] ?? 'QR Code');
    $url = trim($_POST['url'] ?? '');

    if (filter_var($url, FILTER_VALIDATE_URL) && $user['credit_balance'] >= $cost_per_qr) {
        $mysqli->begin_transaction();
        try {
            // Generate QR Code
            $qr_code = QrCode::create($url);
            $writer = new PngWriter();
            $result = $writer->write($qr_code);

            $upload_dir = APP_ROOT . '/public/uploads/qrcodes/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename = uniqid('qr_') . '.png';
            $file_path = $upload_dir . $filename;
            $relative_path = '/uploads/qrcodes/' . $filename;
            file_put_contents($file_path, $result->getString());

            // Deduct credits & save to DB
            $mysqli->query("UPDATE users SET credit_balance = credit_balance - $cost_per_qr WHERE id = {$user['id']}");
            $stmt = $mysqli->prepare("INSERT INTO qr_codes (user_id, team_id, name, url, image_path, cost_in_credits) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssd", $user['id'], $user['team_id'], $name, $url, $relative_path, $cost_per_qr);
            $stmt->execute();
            $mysqli->query("INSERT INTO transactions (user_id, type, description, amount_credits, status) VALUES ({$user['id']}, 'spend_qr_code', 'QR Code: $name', $cost_per_qr, 'completed')");

            $mysqli->commit();
            header('Location: /qr-codes');
            exit;
        } catch(Exception $e) {
            $mysqli->rollback();
        }
    }
}


// Fetch existing QR codes
$team_id_condition = $user['team_id'] ? "team_id = " . $user['team_id'] : "user_id = " . $user['id'];
$qr_codes = $mysqli->query("SELECT * FROM qr_codes WHERE $team_id_condition ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);


include __DIR__ . '/../includes/header_app.php';
?>
<div class="container app-content">
    <h1>QR Code Generator</h1>
    <div class="card">
        <h3>Create New QR Code</h3>
        <p>Cost: <?php echo $cost_per_qr; ?> credits</p>
        <form method="POST">
            <input type="hidden" name="action" value="generate">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>URL</label>
                <input type="url" name="url" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Generate QR Code</button>
        </form>
    </div>

    <div class="card">
        <h3>My QR Codes</h3>
        <div class="qr-code-grid">
            <?php foreach($qr_codes as $code): ?>
            <div class="qr-code-item">
                <img src="/public<?php echo $code['image_path']; ?>">
                <p><?php echo htmlspecialchars($code['name']); ?></p>
                <a href="/public<?php echo $code['image_path']; ?>" download>Download</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php
include __DIR__ . '/../includes/footer_app.php';
?>
