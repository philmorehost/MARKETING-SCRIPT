<?php
require_once '../config/db.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($body) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please fill out all fields with valid information.";
    } else {
        // Create a support ticket from a guest
        $stmt = $mysqli->prepare("INSERT INTO support_tickets (guest_name, guest_email, subject) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $name, $email, $subject);
        if ($stmt->execute()) {
            $ticket_id = $stmt->insert_id;
            // Add the message as the first reply
            $reply_stmt = $mysqli->prepare("INSERT INTO support_ticket_replies (ticket_id, message) VALUES (?, ?)");
            $reply_stmt->bind_param('is', $ticket_id, $body);
            $reply_stmt->execute();

            $message = "Thank you for contacting us! Your ticket has been created. We will get back to you shortly.";
        } else {
            $message = "There was an error submitting your message. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contact Us</title>
    <link rel="stylesheet" href="css/public_style.css">
</head>
<body>

    <main class="page-content">
        <h1>Contact Us</h1>
        <p>Have a question? Fill out the form below and we'll get back to you.</p>

        <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>

        <form action="/public/contact" method="post">
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="subject">Subject</label>
                <input type="text" id="subject" name="subject" required>
            </div>
            <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" name="message" rows="6" required></textarea>
            </div>
            <button type="submit">Send Message</button>
        </form>
    </main>

    <?php include APP_ROOT . '/public/includes/site_footer.php'; ?>
</body>
</html>
