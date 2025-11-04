<?php
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];
$team_owner_id = $_SESSION['team_owner_id'];
$message = '';

// Fetch cost from settings
$price_per_qr = (float)get_setting('price_per_qr_code', $mysqli, 2);

// Handle QR Code Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_qr'])) {
    $name = trim($_POST['name'] ?? 'QR Code');
    $url = trim($_POST['url'] ?? '');

    if (filter_var($url, FILTER_VALIDATE_URL)) {
        $stmt_balance = $mysqli->prepare("SELECT credit_balance FROM users WHERE id = ?");
        $stmt_balance->bind_param('i', $team_owner_id);
        $stmt_balance->execute();
        $user_balance = (float)$stmt_balance->get_result()->fetch_assoc()['credit_balance'];

        if ($user_balance >= $price_per_qr) {
            $mysqli->begin_transaction();
            try {
                $upload_dir = APP_ROOT . '/public_html/uploads/qr/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
                $filename = uniqid('qr_') . '.png';
                $filepath = $upload_dir . $filename;
                $web_path = '/uploads/qr/' . $filename;

                $qrCode = QrCode::create($url);
                $writer = new PngWriter();
                $result = $writer->write($qrCode);
                $result->saveToFile($filepath);

                $update_credits_stmt = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance - ? WHERE id = ?");
                $update_credits_stmt->bind_param('di', $price_per_qr, $team_owner_id);
                $update_credits_stmt->execute();

                $stmt_insert = $mysqli->prepare("INSERT INTO qr_codes (user_id, team_id, name, url, image_path, cost_in_credits) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_insert->bind_param('iisssd', $user_id, $team_id, $name, $url, $web_path, $price_per_qr);
                $stmt_insert->execute();

                $mysqli->commit();
                $message = "QR Code generated successfully!";
            } catch (Exception $e) {
                $mysqli->rollback();
                $message = "An error occurred during generation: " . $e->getMessage();
            }
        } else {
            $message = "Insufficient credits. You need {$price_per_qr} credits.";
        }
    } else {
        $message = "Please enter a valid URL.";
    }
}

// Fetch existing QR codes
$codes_result = $mysqli->prepare("SELECT name, url, image_path, created_at FROM qr_codes WHERE team_id = ? ORDER BY id DESC");
$codes_result->bind_param('i', $team_id);
$codes_result->execute();
$codes = $codes_result->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head><title>QR Code Generator</title><link rel="stylesheet" href="/public/css/dashboard_style.css"></head>
<body>
    <?php include APP_ROOT . '/public_html/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public_html/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>QR Code Generator</h1>
            <?php if ($message): ?><div class="message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <div class="card">
                <h2>Create New QR Code</h2>
                <form action="/public/qr-codes" method="post">
                    <input type="hidden" name="generate_qr" value="1">
                    <p>Cost per QR Code: <strong><?php echo $price_per_qr; ?> credits</strong></p>
                    <div class="form-group"><label for="name">Name</label><input type="text" id="name" name="name" placeholder="e.g., Business Card Link" required></div>
                    <div class="form-group"><label for="url">URL</label><input type="url" id="url" name="url" placeholder="https://example.com" required></div>
                    <button type="submit">Generate QR Code</button>
                </form>
            </div>
            <hr>
            <h2>Your QR Codes</h2>
            <div class="qr-grid">
                <?php if ($codes->num_rows > 0): ?>
                    <?php while($code = $codes->fetch_assoc()): ?>
                    <div class="qr-item card">
                        <img src="/public<?php echo htmlspecialchars($code['image_path']); ?>" alt="QR Code">
                        <p><strong><?php echo htmlspecialchars($code['name']); ?></strong></p>
                        <p><small><?php echo htmlspecialchars($code['url']); ?></small></p>
                        <a href="/public<?php echo htmlspecialchars($code['image_path']); ?>" class="button-small" download>Download</a>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>You haven't generated any QR codes yet.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <?php include APP_ROOT . '/public_html/includes/footer.php'; ?>
</body>
</html>
