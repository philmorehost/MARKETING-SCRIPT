<?php
session_start();
require_once '../config/db.php';
require_once '../src/lib/functions.php';
require_once '../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$message = '';

// Fetch cost from settings
$price_per_qr = (float)get_setting('price_per_qr_code', $mysqli, 2);

// Handle QR Code Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_qr'])) {
    $name = trim($_POST['name'] ?? 'QR Code');
    $url = trim($_POST['url'] ?? '');

    if (filter_var($url, FILTER_VALIDATE_URL)) {
        $stmt = $mysqli->prepare("SELECT credit_balance FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user_balance = $stmt->get_result()->fetch_assoc()['credit_balance'];

        if ($user_balance >= $price_per_qr) {
            $mysqli->begin_transaction();
            try {
                // --- QR Image Generation ---
                $upload_dir = 'uploads/qr/';
                $filename = uniqid('qr_') . '.png';
                $filepath = $upload_dir . $filename;

                $qrCode = QrCode::create($url);
                $writer = new PngWriter();
                $result = $writer->write($qrCode);
                $result->saveToFile($filepath);
                // --- End QR Image Generation ---

                $update_credits_stmt = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance - ? WHERE id = ?");
                $update_credits_stmt->bind_param('di', $price_per_qr, $user_id);
                $update_credits_stmt->execute();

                $stmt = $mysqli->prepare("INSERT INTO qr_codes (user_id, name, url, image_path, cost_in_credits) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param('isssd', $user_id, $name, $url, $filepath, $price_per_qr);
                $stmt->execute();

                $mysqli->commit();
                $message = "QR Code generated successfully!";

            } catch (Exception $e) {
                $mysqli->rollback();
                $message = "An error occurred during generation.";
            }
        } else {
            $message = "Insufficient credits.";
        }
    } else {
        $message = "Please enter a valid URL.";
    }
}

// Fetch existing QR codes
$codes_result = $mysqli->prepare("SELECT name, url, image_path, created_at FROM qr_codes WHERE user_id = ? ORDER BY id DESC");
$codes_result->bind_param('i', $user_id);
$codes_result->execute();
$codes = $codes_result->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head><title>QR Code Generator</title><link rel="stylesheet" href="css/dashboard_style.css"></head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include 'includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>QR Code Generator</h1>
            <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>

            <h2>Create New QR Code</h2>
            <form action="qr-codes.php" method="post">
                <input type="hidden" name="generate_qr" value="1">
                <p>Cost per QR Code: <?php echo $price_per_qr; ?> credits</p>
                <input type="text" name="name" placeholder="Name (e.g., Business Card)" required><br>
                <input type="url" name="url" placeholder="https://example.com" required><br>
                <button type="submit">Generate</button>
            </form>

            <hr>
            <h2>Your QR Codes</h2>
            <div class="qr-grid">
                <?php while($code = $codes->fetch_assoc()): ?>
                <div class="qr-item">
                    <img src="<?php echo htmlspecialchars($code['image_path']); ?>" alt="QR Code" width="150">
                    <p><strong><?php echo htmlspecialchars($code['name']); ?></strong></p>
                    <p><small><?php echo htmlspecialchars($code['url']); ?></small></p>
                    <a href="<?php echo htmlspecialchars($code['image_path']); ?>" download>Download</a>
                </div>
                <?php endwhile; ?>
            </div>
        </main>
    </div>
</body>
</html>
