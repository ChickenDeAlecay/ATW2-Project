<?php
require_once 'auth.php';

// If already logged in, redirect to main page
if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'login') {
            $result = $auth->login($_POST['email'], $_POST['password']);
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'error';
            
            if ($result['success']) {
                header('Location: index.php');
                exit;
            }
        } elseif ($_POST['action'] === 'register') {
            $result = $auth->register($_POST['email'], $_POST['display_name'], $_POST['password']);
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'error';
            
            if ($result['success']) {
                $message .= ' You can now log in.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bristol Trees - Login</title>
    <link rel="stylesheet" href="stylesheet.css">
</head>
<body>
    <div class="auth-container">
        <h2>Bristol Trees Map</h2>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="auth-tabs">
            <button class="auth-tab active" onclick="showTab('login')">Login</button>
            <button class="auth-tab" onclick="showTab('register')">Register</button>
        </div>
        
        <!-- Login Form -->
        <form class="auth-form active" id="login-form" method="POST">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
                <label for="login-email">Email:</label>
                <input type="email" id="login-email" name="email" required>
            </div>
            <div class="form-group">
                <label for="login-password">Password:</label>
                <input type="password" id="login-password" name="password" required>
            </div>
            <button type="submit" class="btn full-width">Login</button>
        </form>
        
        <!-- Register Form -->
        <form class="auth-form" id="register-form" method="POST">
            <input type="hidden" name="action" value="register">
            <div class="form-group">
                <label for="register-email">Email:</label>
                <input type="email" id="register-email" name="email" required>
            </div>
            <div class="form-group">
                <label for="register-display-name">Display Name:</label>
                <input type="text" id="register-display-name" name="display_name" required maxlength="100">
            </div>
            <div class="form-group">
                <label for="register-password">Password:</label>
                <input type="password" id="register-password" name="password" required minlength="6">
            </div>
            <button type="submit" class="btn full-width">Register</button>
        </form>
        
        <div class="back-link">
            <a href="index.php">← Back to Map</a>
        </div>
    </div>
    
    <script>
        function showTab(tab) {
            // Update tab buttons
            document.querySelectorAll('.auth-tab').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Update forms
            document.querySelectorAll('.auth-form').forEach(form => form.classList.remove('active'));
            document.getElementById(tab + '-form').classList.add('active');
        }
    </script>
</body>
</html>