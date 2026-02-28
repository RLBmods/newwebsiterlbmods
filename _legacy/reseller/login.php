<?php
// Ensure no output before headers
ob_start();


// Load dependencies
require_once'../includes/session.php';
require_once'../includes/functions.php';
require_once'../db/connection.php';
require_once'../includes/logging.php';

// Redirect if already logged in
if (isLoggedIn()) {
    // Check if user is verified
    $user_id = $_SESSION['user_id'];
    $stmt = $con->prepare("SELECT status FROM usertable WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($status);
    $stmt->fetch();
    $stmt->close();
    
    if ($status === "verified") {
        header("Location: dashboard.php");
        exit();
    } else {
        // User is logged in but not verified, redirect to verify.php
        $_SESSION['email'] = $_SESSION['user_email'];
        header("Location: verify.php");
        exit();
    }
}

// Check for session expiration redirect
if (isset($_GET['reason']) && $_GET['reason'] === 'session_expired') {
    $_SESSION['error'] = "Your session has expired. Please login again.";
    // Redirect to clean URL to prevent the message from showing again on refresh
    header("Location: login.php");
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function getClientIP() {
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            return $_SERVER[$key];
        }
    }
    return '0.0.0.0';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        logSecurityEvent("CSRF token validation failed", "Invalid CSRF token during login attempt", [
            'expected_token' => $_SESSION['csrf_token'],
            'received_token' => $_POST['csrf_token'] ?? 'none',
            'ip' => getClientIP()
        ], "failed");
        
        $_SESSION['error'] = "Security token invalid. Please try again.";
        header("Location: login.php");
        exit();
    }

    // Email validation
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        logSecurityEvent("Invalid email format", "Login attempt with invalid email format", [
            'email_attempted' => $_POST['email'],
            'ip' => getClientIP()
        ], "warning");
        
        $_SESSION['error'] = "Please enter a valid email address.";
        $_SESSION['email_error'] = "Invalid email format";
        $_SESSION['old_email'] = htmlspecialchars($_POST['email']);
        header("Location: login.php");
        exit();
    }

    $password = $_POST['password'];
    $current_ip = getClientIP();

    // Fetch user from database
    $stmt = $con->prepare("SELECT * FROM usertable WHERE email = ?");
    $stmt->bind_param("s", $email);
    
    if (!$stmt->execute()) {
        logSecurityEvent("Database error", "Failed to execute login query", [
            'email' => $email,
            'error' => $stmt->error,
            'ip' => $current_ip
        ], "failed");
        
        $_SESSION['error'] = "Database error. Please try again later.";
        header("Location: login.php");
        exit();
    }

    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Check if account is verified
        if ($user['status'] !== 'verified') {
            // Account is not verified, redirect to verification page
            $_SESSION['email'] = $email;
            $_SESSION['error'] = "Please verify your email address before logging in.";
            logSecurityEvent("Unverified login attempt", "User attempted to login before verifying email", [
                'user_id' => $user['id'],
                'username' => $user['name'],
                'email' => $email,
                'ip' => $current_ip
            ], "warning");
            
            header("Location: verify.php");
            exit();
        }
        
        // Check if user is banned
        if (isUserBanned($user['id'])) {
            $ban = getActiveBan($user['id']);
            
            // Log banned login attempt
            logSecurityEvent("Banned user login attempt", "Banned user attempted to login", [
                'user_id' => $user['id'],
                'username' => $user['name'],
                'email' => $email,
                'ip' => $current_ip,
                'ban_id' => $ban['id'] ?? null,
                'is_permanent' => $ban['is_permanent'] ?? null,
                'expires_at' => $ban['expires_at'] ?? null
            ], "warning");
            
            // Build detailed ban message
            $ban_message = "Your account has been banned.";
            
            // Add reason
            $reason = !empty($ban['reason']) ? htmlspecialchars($ban['reason']) : 'No reason specified';
            $ban_message .= "<br><strong>Reason:</strong> " . $reason;
            
            // Add duration
            if ($ban['is_permanent']) {
                $ban_message .= "<br><strong>Duration:</strong> Permanent";
            } else if (!empty($ban['expires_at'])) {
                $expires = date('F j, Y, g:i a', strtotime($ban['expires_at']));
                $now = new DateTime();
                $expiry_date = new DateTime($ban['expires_at']);
                $time_left = $now->diff($expiry_date);
                
                $time_left_str = '';
                if ($time_left->d > 0) $time_left_str .= $time_left->d . ' days ';
                if ($time_left->h > 0) $time_left_str .= $time_left->h . ' hours ';
                if ($time_left->i > 0) $time_left_str .= $time_left->i . ' minutes ';
                if ($time_left_str === '') $time_left_str = 'Less than 1 minute';
                
                $ban_message .= "<br><strong>Duration:</strong> Temporary (Expires: " . $expires . ")";
                $ban_message .= "<br><strong>Time Left:</strong> " . trim($time_left_str);
            } else {
                $ban_message .= "<br><strong>Duration:</strong> Unknown";
            }
            
            // Add banned by information
            $banned_by = !empty($ban['banned_by_username']) 
                ? htmlspecialchars($ban['banned_by_username']) 
                : (isset($ban['banned_by_name']) ? htmlspecialchars($ban['banned_by_name']) : 'Unknown');
            $ban_message .= "<br><strong>Banned By:</strong> " . $banned_by;
            
            // Store the formatted ban message
            $_SESSION['ban_error'] = $ban_message;
            
            header("Location: login.php");
            exit();
        }

        // Account lock check
        if ($user['login_attempts'] >= 5 && strtotime($user['last_login_attempt']) > strtotime('-15 minutes')) {
            logSecurityEvent("Account locked", "Login attempt to locked account", [
                'user_id' => $user['id'],
                'username' => $user['name'],
                'email' => $email,
                'ip' => $current_ip,
                'attempts' => $user['login_attempts'],
                'last_attempt' => $user['last_login_attempt']
            ], "warning");
            
            $_SESSION['error'] = "Account temporarily locked. Try again in 15 minutes or reset your password.";
            header("Location: login.php");
            exit();
        }

        // Password verification
        if (password_verify($password, $user['password'])) {
            // IP change handling
            $ip_update_fields = '';
            $params = [];
            $types = '';
            
            if ($user['current_ip'] !== $current_ip) {
                $ip_update_fields = ', current_ip = ?, last_ip = ?';
                $params = [$current_ip, $user['current_ip'], $user['id']];
                $types = 'ssi';
            } else {
                $params = [$user['id']];
                $types = 'i';
            }
        
            // Update user record
            $update_query = "UPDATE usertable SET login_attempts = 0, last_login_attempt = NULL, last_login = NOW(), last_activity = NOW()" . $ip_update_fields . " WHERE id = ?";
            $update_stmt = $con->prepare($update_query);
            $update_stmt->bind_param($types, ...$params);
            $update_stmt->execute();
            $update_stmt->close();
        
            // Session regeneration
            session_regenerate_id(true);

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // Log successful login
 if ($user['role'] !== 'reseller') {
        // Log non-reseller login attempt
        logSecurityEvent(
            "Non-reseller login attempt",
            "User logged in successfully but is not a reseller",
            [
                'user_id' => $user['id'],
                'username' => $user['name'],
                'email' => $email,
                'role' => $user['role'],
                'ip' => $current_ip
            ],
            "warning"
        );

        // Set message and redirect
        $_SESSION['error'] = "You are not a reseller. Redirecting to main panel...";
        header("Refresh:3; url=https://panel.rlbmods.com"); // 3-second delay
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta http-equiv="refresh" content="3;url=https://panel.rlbmods.com">
            <title>Redirecting...</title>
            <link rel="stylesheet" href="https://panel.rlbmods.com/css/auth.css">
        </head>
        <body class="auth-page">
            <div class="auth-container">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle alert-icon"></i>
                    <span>You are not a reseller. Redirecting to main panel...</span>
                </div>
            </div>
        </body>
        </html>';
        exit();
    }

    // Log successful login
    logUserAction(
        $user['id'],
        "Successful login",
        "User logged in successfully from IP: " . $current_ip,
        [
            'ip_address' => $current_ip,
            'previous_ip' => $user['current_ip'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'login_method' => 'password'
        ],
        'success'
    );

            // Redirect to dashboard
            $_SESSION['success'] = "Login successful! Redirecting to your dashboard...";
            header("Location: dashboard.php");
            exit();
        } else {
            // Handle failed attempt
            $attempts = $user['login_attempts'] + 1;
            $update_stmt = $con->prepare("UPDATE usertable SET login_attempts = ?, last_login_attempt = NOW() WHERE id = ?");
            $update_stmt->bind_param("ii", $attempts, $user['id']);
            $update_stmt->execute();
            $update_stmt->close();

            // Log failed attempt with user details
            logSecurityEvent(
                "Failed login attempt",
                "Invalid password provided for user",
                [
                    'user_id' => $user['id'],
                    'username' => $user['name'],
                    'email' => $email,
                    'ip_address' => $current_ip,
                    'attempt_number' => $attempts,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                    'account_status' => ($attempts >= 5) ? 'locked' : 'active'
                ],
                "failed",
                $user['name'],  // Explicitly pass username
                $email         // Explicitly pass email
            );

            // Set error message
            $remaining_attempts = 5 - $attempts;
            $_SESSION['error'] = $remaining_attempts > 0 
                ? "Invalid password. {$remaining_attempts} attempts remaining."
                : "Account locked. Try again in 15 minutes or reset your password.";
            
            $_SESSION['password_error'] = "Incorrect password";
            $_SESSION['old_email'] = htmlspecialchars($email);
            header("Location: login.php");
            exit();
        }
    } else {
        // Unknown email attempt
        logSecurityEvent(
            "Login attempt with unknown email",
            "No account found with provided email",
            [
                'email' => $email,
                'ip_address' => $current_ip,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ],
            "warning"
        );
        
        $_SESSION['error'] = "No account found with that email address.";
        $_SESSION['email_error'] = "Account not found";
        $_SESSION['old_email'] = htmlspecialchars($email);
        header("Location: login.php");
        exit();
    }
}

// Display messages
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
$old_email = $_SESSION['old_email'] ?? '';
unset($_SESSION['error'], $_SESSION['success'], $_SESSION['old_email'],
      $_SESSION['email_error'], $_SESSION['password_error']);

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_name); ?> &bullet; Login</title>
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($site_icon); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://panel.rlbmods.com/css/auth.css">
<script src="https://panel.rlbmods.com/js/auth.js" defer></script>
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-header">
            <h1>Welcome Back</h1>
            <p>Login to access your Reseller Portal</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle alert-icon"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['ban_error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-ban alert-icon"></i>
            <span style="white-space: pre-line;"><?php echo $_SESSION['ban_error']; ?></span>
        </div>
        <?php 
            unset($_SESSION['ban_error']);
        endif; 
        ?>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle alert-icon"></i>
            <span><?php echo htmlspecialchars($success); ?></span>
        </div>
        <?php endif; ?>
        
        <form class="auth-form" id="login-form" method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="input-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required
                       value="<?php echo htmlspecialchars($old_email); ?>"
                       class="<?php echo isset($_SESSION['email_error']) ? 'input-error' : ''; ?>">
                <?php if (isset($_SESSION['email_error'])): ?>
                <span class="input-error-message"><?php echo htmlspecialchars($_SESSION['email_error']); ?></span>
                <?php endif; ?>
            </div>
            
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       class="<?php echo isset($_SESSION['password_error']) ? 'input-error' : ''; ?>">
                <?php if (isset($_SESSION['password_error'])): ?>
                <span class="input-error-message"><?php echo htmlspecialchars($_SESSION['password_error']); ?></span>
                <?php endif; ?>
                <a href="forgot_password.php" class="forgot-password">Forgot password?</a>
            </div>
            
            <button type="submit" class="btn-primary">Login</button>
            
            <?php if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 3): ?>
            <div class="alert alert-warning" style="margin-top: 15px;">
                <i class="fas fa-lock alert-icon"></i>
                <span>Multiple failed attempts may temporarily lock your account.</span>
            </div>
            <?php endif; ?>
        </form>
        

    </div>
</body>
</html>