<?php
// Installer - Multi-Stage
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- Configuration ---
$required_php_version = '8.1.0';
$required_extensions = ['mysqli', 'curl', 'gd'];
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// --- Logic ---
$error_message = '';

if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Stage 2: Process Database Setup ---
    $db_host = $_POST['db_host'] ?? '';
    $db_name = $_POST['db_name'] ?? '';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';

    // Store in session to repopulate form on error
    $_SESSION['db_details'] = $_POST;

    // Try to connect
    @$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($mysqli->connect_error) {
        $error_message = "Database Connection Failed: " . $mysqli->connect_error;
    } else {
        // Connection successful, save to session for the next step
        $_SESSION['db_connected'] = true;

        // Create config file
        $config_content = "<?php
define('DB_HOST', '" . addslashes($db_host) . "');
define('DB_NAME', '" . addslashes($db_name) . "');
define('DB_USER', '" . addslashes($db_user) . "');
define('DB_PASS', '" . addslashes($db_pass) . "');
";
        if (file_put_contents('../config/db.php', $config_content) === false) {
             $error_message = "Error: Could not write to config/db.php. Please check file permissions.";
        } else {
             // Redirect to step 3
            header('Location: index.php?step=3');
            exit;
        }
    }
} elseif ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Stage 3: Process Admin Account Creation ---
    require_once '../config/db.php';

    $admin_email = $_POST['admin_email'] ?? '';
    $admin_password = $_POST['admin_password'] ?? '';

    if (empty($admin_email) || empty($admin_password) || !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email and password.";
    } else {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($mysqli->connect_error) {
            $error_message = "Database connection failed. Please go back and check your settings.";
        } else {
            // --- Database Schema Installation ---
            $schema_sql = file_get_contents('schema.sql');
            if ($mysqli->multi_query($schema_sql)) {
                 // Clear results from multi_query
                while ($mysqli->next_result()) {
                    if ($result = $mysqli->store_result()) {
                        $result->free();
                    }
                }

                // --- Create Admin User ---
                $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
                $stmt = $mysqli->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
                $admin_name = 'Administrator';
                $stmt->bind_param('sss', $admin_name, $admin_email, $hashed_password);

                if ($stmt->execute()) {
                    header('Location: index.php?step=4');
                    exit;
                } else {
                    $error_message = "Failed to create admin user: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error_message = "Failed to install database schema: " . $mysqli->error;
            }
            $mysqli->close();
        }
    }
}

// --- View ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Email Verifier - Installer</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 40px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        h1, h2 { text-align: center; color: #106297; }
        .status { padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .status.ok { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        ul { list-style: none; padding-left: 0; }
        li { padding: 5px 0; border-bottom: 1px solid #eee; }
        li:last-child { border-bottom: none; }
        .check::before { content: '✔'; color: green; margin-right: 10px; }
        .cross::before { content: '✖'; color: red; margin-right: 10px; }
        .button { display: inline-block; background-color: #106297; color: #fff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-size: 16px; border: none; cursor: pointer; }
        .button:disabled { background-color: #aaa; cursor: not-allowed; }
        .text-center { text-align: center; margin-top: 30px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
    </style>
</head>
<body>
    <h1>Installer</h1>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if ($step === 1): // --- Stage 1 View --- ?>
        <h2>Stage 1: Server Requirements</h2>
        <?php
            $php_version = phpversion();
            $php_version_ok = version_compare($php_version, $required_php_version, '>=');
            $loaded_extensions = get_loaded_extensions();
            $missing_extensions = [];
            foreach ($required_extensions as $ext) {
                if (!in_array($ext, $loaded_extensions)) {
                    $missing_extensions[] = $ext;
                }
            }
            $extensions_ok = empty($missing_extensions);
            $all_ok = $php_version_ok && $extensions_ok;
        ?>
        <div class="status <?php echo $php_version_ok ? 'ok' : 'error'; ?>">
            <strong>PHP Version:</strong>
            <?php if ($php_version_ok): ?>
                <span class="check">OK (<?php echo htmlspecialchars($php_version); ?>)</span>
            <?php else: ?>
                <span class="cross">Error (Required: <?php echo $required_php_version; ?>, Found: <?php echo htmlspecialchars($php_version); ?>)</span>
            <?php endif; ?>
        </div>
        <div class="status <?php echo $extensions_ok ? 'ok' : 'error'; ?>">
            <strong>PHP Extensions:</strong>
            <?php if ($extensions_ok): ?>
                <span class="check">All required extensions are installed.</span>
            <?php else: ?>
                <span class="cross">Missing extensions: <?php echo implode(', ', $missing_extensions); ?></span>
            <?php endif; ?>
        </div>
        <div class="text-center">
            <a href="index.php?step=2" class="button" style="<?php if (!$all_ok) echo 'pointer-events: none; background-color: #aaa;'; ?>">
                Proceed to Database Setup
            </a>
        </div>

    <?php elseif ($step === 2): // --- Stage 2 View --- ?>
        <h2>Stage 2: Database Setup</h2>
        <p>Please provide your database connection details.</p>
        <form action="index.php?step=2" method="post">
            <div class="form-group">
                <label for="db_host">Database Host</label>
                <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($_SESSION['db_details']['db_host'] ?? 'localhost'); ?>" required>
            </div>
            <div class="form-group">
                <label for="db_name">Database Name</label>
                <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($_SESSION['db_details']['db_name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="db_user">Database Username</label>
                <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($_SESSION['db_details']['db_user'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="db_pass">Database Password</label>
                <input type="password" id="db_pass" name="db_pass">
            </div>
            <div class="text-center">
                <button type="submit" class="button">Test Connection & Proceed</button>
            </div>
        </form>

    <?php elseif ($step === 3): // --- Stage 3 View --- ?>
        <h2>Stage 3: Create Admin Account</h2>
        <?php if (!isset($_SESSION['db_connected'])): ?>
            <div class="alert alert-danger">Database not connected. Please <a href="index.php?step=2">go back</a> and set up the database.</div>
        <?php else: ?>
            <form action="index.php?step=3" method="post">
                <div class="form-group">
                    <label for="admin_email">Admin Email</label>
                    <input type="email" id="admin_email" name="admin_email" required>
                </div>
                <div class="form-group">
                    <label for="admin_password">Admin Password</label>
                    <input type="password" id="admin_password" name="admin_password" required>
                </div>
                <div class="text-center">
                    <button type="submit" class="button">Create Admin & Finish Installation</button>
                </div>
            </form>
        <?php endif; ?>

    <?php elseif ($step === 4): // --- Stage 4 View --- ?>
        <h2>Installation Complete!</h2>
        <div class="alert alert-success">
            Congratulations! The platform has been installed successfully.
        </div>
        <div class="alert alert-danger">
            <strong>IMPORTANT:</strong> For security reasons, please DELETE the entire <strong>/install</strong> directory from your server immediately.
        </div>
        <div class="text-center">
            <a href="../login.php" class="button">Go to Admin Login</a>
        </div>
        <?php
            // Clean up session
            session_unset();
            session_destroy();
        ?>
    <?php endif; ?>

</body>
</html>
