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

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect if already logged in and verified
if (isLoggedIn()) {
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
    }
}

// Check if email is in session (user came from registration)
if (!isset($_SESSION['email'])) {
    $_SESSION['error'] = "Please register first or request a new verification code.";
    header("Location: register.php");
    exit();
}

$email = $_SESSION['email'];
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security token invalid. Please try again.";
        header("Location: verify.php");
        exit();
    }

    // Check if resending verification code
    if (isset($_POST['resend'])) {
        // Generate new verification code
        $new_verification_code = rand(100000, 999999);
        
        // Update the code in database
        $stmt = $con->prepare("UPDATE usertable SET code = ? WHERE email = ?");
        $stmt->bind_param("ss", $new_verification_code, $email);
        
        if ($stmt->execute()) {
            // Get user info for logging
            $stmt2 = $con->prepare("SELECT id, name FROM usertable WHERE email = ?");
            $stmt2->bind_param("s", $email);
            $stmt2->execute();
            $stmt2->bind_result($user_id, $name);
            $stmt2->fetch();
            $stmt2->close();
            
            // Send verification email
            $to = $email;
            $subject = "New Verification Code";
            $message = "Hi $name,\n\n";
            $message .= "Your new verification code is: $new_verification_code\n\n";
            $message .= "Enter this code on our website to activate your account.\n\n";
            $message .= "Thanks,\nThe RLBMods Team";
            $headers = "From: no-reply@rlbmods.com\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            if (mail($to, $subject, $message, $headers)) {
                $_SESSION['success'] = "New verification code sent to $email";
                logAction($user_id, $name, $email, $_SERVER['REMOTE_ADDR'], "New Verification Code Sent");
            } else {
                $_SESSION['error'] = "Failed to send verification email. Please try again.";
                logAction($user_id, $name, $email, $_SERVER['REMOTE_ADDR'], "New Verification Email Failed");
            }
        } else {
            $_SESSION['error'] = "Failed to generate new code. Please try again.";
        }
        
        header("Location: verify.php");
        exit();
    }
    
    // Handle verification code submission
    if (isset($_POST['verification_code'])) {
        $verification_code = trim($_POST['verification_code']);
        
        // Validate code
        if (empty($verification_code)) {
            $errors['verification_code'] = "Verification code is required";
        } elseif (!preg_match('/^[0-9]{6}$/', $verification_code)) {
            $errors['verification_code'] = "Verification code must be 6 digits";
        }
        
        // If no errors, verify code
        if (empty($errors)) {
            $stmt = $con->prepare("SELECT id, name, code FROM usertable WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->bind_result($user_id, $name, $db_code);
            $stmt->fetch();
            $stmt->close();
            
            if ($verification_code === $db_code) {
                // Code matches, verify account
                $status = "verified";
                $stmt = $con->prepare("UPDATE usertable SET status = ?, code = 0 WHERE email = ?");
                $stmt->bind_param("ss", $status, $email);
                
                if ($stmt->execute()) {
                    // Log successful verification
                    logAction($user_id, $name, $email, $_SERVER['REMOTE_ADDR'], "Account Verified");
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $name;
                    $_SESSION['email'] = $email;
                    
                    // Clear the email from session
                    unset($_SESSION['email']);
                    
                    $_SESSION['success'] = "Your account has been verified successfully!";
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $errors['verification_code'] = "Verification failed. Please try again.";
                    logAction($user_id, $name, $email, $_SERVER['REMOTE_ADDR'], "Verification Update Failed");
                }
            } else {
                $errors['verification_code'] = "Invalid verification code";
                logAction($user_id, $name, $email, $_SERVER['REMOTE_ADDR'], "Invalid Verification Code Attempt");
            }
        }
    }
}

// Get messages
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';

// Clear messages
unset($_SESSION['error'], $_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name;?> &bullet; Verify Account</title>
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($site_icon); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/auth.css">
    <script src="js/auth.js" defer></script>
    <style>
        .verification-inputs {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 15px;
        }
        .verification-inputs input {
            width: 45px;
            height: 55px;
            text-align: center;
            font-size: 24px;
            border: 2px solid #ddd;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .verification-inputs input:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 5px rgba(74, 144, 226, 0.5);
            outline: none;
        }
        .resend-container {
            text-align: center;
            margin-top: 20px;
        }
        #countdown {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-header">
            <h1>Verify Your Email</h1>
            <p>We've sent a 6-digit verification code to<br><strong><?php echo htmlspecialchars($email); ?></strong></p>
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
        
        <form class="auth-form" id="verify-form" method="POST" action="verify.php">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="input-group">
                <label for="verification-code">Verification Code</label>
                <div class="verification-inputs">
                    <input type="text" id="digit1" name="digit1" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="one-time-code" required>
                    <input type="text" id="digit2" name="digit2" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" id="digit3" name="digit3" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" id="digit4" name="digit4" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" id="digit5" name="digit5" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" id="digit6" name="digit6" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                </div>
                <input type="hidden" id="verification_code" name="verification_code">
                <?php if (isset($errors['verification_code'])): ?>
                <span class="input-error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($errors['verification_code']); ?>
                </span>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="btn-primary">Verify Account</button>
        </form>
        
        <div class="resend-container">
            <form method="POST" action="verify.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="resend" value="1">
                <p>Didn't receive the code? <button type="submit" style="background: none; border: none; color: #4a90e2; cursor: pointer; text-decoration: underline;">Resend code</button></p>
            </form>
            <div id="countdown">You can request a new code in <span id="countdown-timer">60</span> seconds</div>
        </div>
        
        <div class="auth-footer">
            <p>Want to use a different email? <a href="register.php">Register again</a></p>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.verification-inputs input');
            const hiddenInput = document.getElementById('verification_code');
            const form = document.getElementById('verify-form');
            
            // Focus first input on load
            inputs[0].focus();
            
            // Handle input
            inputs.forEach((input, index) => {
                input.addEventListener('input', function() {
                    // Auto-tab to next input
                    if (this.value.length === 1 && index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                    
                    // Update hidden input value
                    updateHiddenInput();
                });
                
                input.addEventListener('keydown', function(e) {
                    // Handle backspace
                    if (e.key === 'Backspace' && this.value === '' && index > 0) {
                        inputs[index - 1].focus();
                    }
                });
            });
            
            function updateHiddenInput() {
                hiddenInput.value = Array.from(inputs).map(input => input.value).join('');
            }
            
            // Form validation
            form.addEventListener('submit', function(e) {
                updateHiddenInput();
                
                if (hiddenInput.value.length !== 6) {
                    e.preventDefault();
                    alert('Please enter the complete 6-digit code');
                    inputs[0].focus();
                }
            });
            
            // Countdown timer for resend
            let countdown = 60;
            const countdownElement = document.getElementById('countdown-timer');
            const resendButton = document.querySelector('button[type="submit"]');
            
            const timer = setInterval(function() {
                countdown--;
                countdownElement.textContent = countdown;
                
                if (countdown <= 0) {
                    clearInterval(timer);
                    document.getElementById('countdown').style.display = 'none';
                }
            }, 1000);
        });
    </script>
</body>
</html>