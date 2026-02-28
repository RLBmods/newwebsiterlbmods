<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/db/connection.php';
require_once __DIR__ . '/includes/logging.php';

// Fetch Site Settings
$settings_q = $con->query("SELECT * FROM site_settings LIMIT 1");
$settings = $settings_q->fetch_assoc();
$site_name = $settings['site_name'] ?? 'RLBMods';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security token invalid. Please try again.";
        header("Location: register.php");
        exit();
    }

    $name = trim($_POST['name']);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    $_SESSION['old_name'] = htmlspecialchars($name);
    $_SESSION['old_email'] = htmlspecialchars($email);

    // [VALIDATION LOGIC - Keep original]
    if (empty($name)) { $errors['name'] = "Username is required"; }
    elseif (strlen($name) < 3) { $errors['name'] = "Username must be at least 3 characters"; }
    elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) { $errors['name'] = "Only letters, numbers and underscores allowed"; }

    if (empty($email)) { $errors['email'] = "Email is required"; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['email'] = "Invalid email format"; }

    if (empty($password)) { $errors['password'] = "Password is required"; }
    elseif (strlen($password) < 8) { $errors['password'] = "Password must be at least 8 characters"; }

    if ($password !== $confirm_password) { $errors['confirm_password'] = "Passwords do not match"; }

    if (!isset($errors['email'])) {
        $stmt = $con->prepare("SELECT id FROM usertable WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) { $errors['email'] = "This email is already registered"; }
    }

    // If no errors, register user
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $verification_code = rand(100000, 999999);
        $status = "notverified";
        $created_at = date('Y-m-d H:i:s');
        $discordid = 0;

        $stmt = $con->prepare("INSERT INTO usertable (name, email, password, code, status, created_at, discordid) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssi", $name, $email, $hashed_password, $verification_code, $status, $created_at, $discordid);

        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            
            // --- PROFESSIONAL HTML EMAIL ---
            $to = $email;
            $subject = "Verify Your Account - $site_name";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: no-reply@rlbmods.com\r\n";

            $message = "
            <html>
            <body style='background-color: #0a0a0a; color: #ffffff; font-family: Arial, sans-serif; padding: 40px;'>
                <table width='100%' border='0' cellpadding='0' cellspacing='0'>
                    <tr>
                        <td align='center'>
                            <table width='600' style='background-color: #161616; border: 1px solid #333; border-radius: 10px; padding: 40px;'>
                                <tr><td align='center'><img src='{$settings['logo']}' width='150'></td></tr>
                                <tr><td><h2 style='color: #ff0000; text-align: center;'>Welcome to $site_name</h2></td></tr>
                                <tr><td style='color: #ffffff; font-size: 16px; padding: 20px 0;'>Hi $name,<br><br>Use the code below to verify your account:</td></tr>
                                <tr><td align='center' style='background: #000; border: 1px solid #ff0000; color: #ff0000; font-size: 32px; font-weight: bold; letter-spacing: 5px; padding: 20px; border-radius: 5px;'>$verification_code</td></tr>
                                <tr><td style='padding-top: 30px; font-size: 12px; color: #666; text-align: center;'>{$settings['copyright']}</td></tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>";

            if (mail($to, $subject, $message, $headers)) {
                $_SESSION['email'] = $email;
                $_SESSION['success'] = "Verification code sent to $email";
                header("Location: verify.php");
                exit();
            }
        }
    }
}

// Your Original HTML View Logic
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
$old_name = $_SESSION['old_name'] ?? '';
$old_email = $_SESSION['old_email'] ?? '';
unset($_SESSION['error'], $_SESSION['success'], $_SESSION['old_name'], $_SESSION['old_email']);
?>
<!-- ... paste your original Register HTML/CSS design here ... -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name;?> &bullet; Register</title>
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($site_icon); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/auth.css">
    <script src="js/auth.js" defer></script>
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-header">
            <h1>Create Account</h1>
            <p>Join our community today</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle alert-icon"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle alert-icon"></i>
            <span><?php echo htmlspecialchars($success); ?></span>
        </div>
        <?php endif; ?>
        
        <form class="auth-form" id="register-form" method="POST" action="register.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="input-group">
                <label for="name">Username</label>
                <input type="text" id="name" name="name" required 
                       value="<?php echo htmlspecialchars($old_name); ?>"
                       class="<?php echo (isset($errors['name']) ? 'input-error shake' : ''); ?>"
                       pattern="[a-zA-Z0-9_]+" minlength="3" maxlength="20">
                <?php if (isset($errors['name'])): ?>
                <span class="input-error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($errors['name']); ?>
                </span>
                <?php endif; ?>
            </div>
            
            <div class="input-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required
                       value="<?php echo htmlspecialchars($old_email); ?>"
                       class="<?php echo (isset($errors['email']) ? 'input-error shake' : ''); ?>"
                       maxlength="100">
                <?php if (isset($errors['email'])): ?>
                <span class="input-error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($errors['email']); ?>
                </span>
                <?php endif; ?>
            </div>
            
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       class="<?php echo (isset($errors['password']) ? 'input-error shake' : ''); ?>"
                       minlength="8" maxlength="72">
                <?php if (isset($errors['password'])): ?>
                <span class="input-error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($errors['password']); ?>
                </span>
                <?php endif; ?>
                <div class="password-requirements">
                    <small>Requirements: 8+ characters, uppercase, lowercase, and number</small>
                </div>
            </div>
            
            <div class="input-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required
                       class="<?php echo (isset($errors['confirm_password']) ? 'input-error shake' : ''); ?>">
                <?php if (isset($errors['confirm_password'])): ?>
                <span class="input-error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($errors['confirm_password']); ?>
                </span>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="btn-primary">Register</button>
        </form>
        
        <div class="auth-footer">
            <p>Already have an account? <a href="login.php">Login</a></p>
        </div>
    </div>
</body>
</html>