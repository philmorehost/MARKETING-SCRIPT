<?php
$message = '';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($body) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please fill out all fields with valid information.";
    } else {
        $stmt = $mysqli->prepare("INSERT INTO support_tickets (guest_name, guest_email, subject) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $name, $email, $subject);
        if ($stmt->execute()) {
            $ticket_id = $stmt->insert_id;
            $reply_stmt = $mysqli->prepare("INSERT INTO support_ticket_replies (ticket_id, message) VALUES (?, ?)");
            $reply_stmt->bind_param('is', $ticket_id, $body);
            $reply_stmt->execute();
            $message = "Thank you for contacting us! Your ticket has been created. We will get back to you shortly.";
            $success = true;
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
    <title>Contact Us - <?php echo htmlspecialchars(get_setting('site_name', $mysqli)); ?></title>
    <link rel="stylesheet" href="/css/public_style.css">
</head>
<body>
    <?php include APP_ROOT . '/public/includes/site_header.php'; ?>

    <header class="page-header">
        <div class="container">
            <h1>Contact Us</h1>
            <p>Have a question or need support? Fill out the form below.</p>
        </div>
    </header>

    <section class="contact-form">
        <div class="container-narrow">
            <div class="card">
                <?php if ($message): ?>
                    <div class="message <?php echo $success ? 'success' : 'error'; ?>"><?php echo $message; ?></div>
                <?php endif; ?>

                <?php if (!$success): ?>
                <form action="/contact" method="post">
                    <div class="form-group"><label for="name">Name</label><input type="text" id="name" name="name" required></div>
                    <div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" required></div>
                    <div class="form-group"><label for="subject">Subject</label><input type="text" id="subject" name="subject" required></div>
                    <div class="form-group"><label for="message">Message</label><textarea id="message" name="message" rows="6" required></textarea></div>
                    <button type="submit">Send Message</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include APP_ROOT . '/public/includes/site_footer.php'; ?>
</body>
</html>
