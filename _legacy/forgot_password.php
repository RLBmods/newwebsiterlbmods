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

$errors = [];
$success = '';
$showCodeForm = false;
$showPasswordForm = false;
$email = '';

/**
 * Styled Email Helper
 */
function sendStyledEmail($to, $subject, $title, $body, $settings) {
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
                        <tr><td><h2 style='color: #ff0000; text-align: center;'>$title</h2></td></tr>
                        <tr><td style='color: #ffffff; font-size: 16px; padding: 20px 0;'>$body</td></tr>
                        <tr><td style='padding-top: 30px; font-size: 12px; color: #666; text-align: center;'>{$settings['copyright']}</td></tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>";
    return mail($to, $subject, $message, $headers);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security token invalid.";
        header("Location: forgot_password.php");
        exit();
    }

    // Handle "Send Reset Code"
    if (isset($_POST['send_reset_code'])) {
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $stmt = $con->prepare("SELECT id, name, reset_request_time FROM usertable WHERE email = ? AND banned = 0 LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if ($user['reset_request_time'] && time() - strtotime($user['reset_request_time']) < 60) {
                $errors['email'] = "Please wait 60 seconds.";
            } else {
                $reset_code = rand(100000, 999999);
                $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                $up = $con->prepare("UPDATE usertable SET reset_code = ?, reset_expiry = ?, reset_request_time = NOW() WHERE email = ?");
                $up->bind_param("iss", $reset_code, $expiry, $email);
                
                if ($up->execute()) {
                    $body = "Hello {$user['name']},<br><br>Your password reset code is:<br><br>
                             <div style='background:#000; color:#ff0000; padding:20px; font-size:30px; font-weight:bold; text-align:center; border:1px solid #ff0000;'>$reset_code</div>";
                    if (sendStyledEmail($email, "Password Reset Code", "Reset Your Password", $body, $settings)) {
                        $success = "Reset code sent to your email.";
                    }
                    $_SESSION['reset_email'] = $email;
                    $showCodeForm = true;
                }
            }
        } else { $errors['email'] = "Email not found."; }
    }

    // Handle "Verify Code"
    if (isset($_POST['verify_reset_code'])) {
        // Concatenate array if JS sent it as array, or handle as string
        $code = is_array($_POST['reset_code']) ? implode('', $_POST['reset_code']) : $_POST['reset_code'];
        $email = $_SESSION['reset_email'] ?? '';
        
        $stmt = $con->prepare("SELECT id FROM usertable WHERE email = ? AND reset_code = ? AND reset_expiry > NOW() LIMIT 1");
        $stmt->bind_param("si", $email, $code);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $_SESSION['allow_password_reset'] = true;
            $showPasswordForm = true;
        } else {
            $errors['reset_code'] = "Invalid or expired code.";
            $showCodeForm = true;
        }
    }

    // Handle "Reset Password"
    if (isset($_POST['reset_password'])) {
        $new_password = $_POST['new_password'];
        $email = $_SESSION['reset_email'] ?? '';
        if (strlen($new_password) < 8) {
            $errors['new_password'] = "Min 8 characters.";
            $showPasswordForm = true;
        } elseif ($new_password !== $_POST['confirm_password']) {
            $errors['confirm_password'] = "Passwords do not match.";
            $showPasswordForm = true;
        } else {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $con->prepare("UPDATE usertable SET password = ?, reset_code = NULL, reset_expiry = NULL WHERE email = ?");
            $stmt->bind_param("ss", $hashed, $email);
            if ($stmt->execute()) {
                sendStyledEmail($email, "Password Changed", "Security Alert", "Your password has been changed successfully.", $settings);
                unset($_SESSION['reset_email'], $_SESSION['allow_password_reset']);
                $_SESSION['success'] = "Password updated! You can now login.";
                header("Location: login.php");
                exit();
            }
        }
    }
}

// Display messages
$error = $_SESSION['error'] ?? '';
$success = $success ?: ($_SESSION['success'] ?? '');
$old_email = $_SESSION['reset_email'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_name); ?> &bullet; Forgot Password</title>
    <link rel="icon" href="<?php echo $settings['favicon']; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/auth.css">
    <style>
        /* KEEPING YOUR EXACT STYLE */
        .code-inputs { display: flex; justify-content: space-evenly; margin-bottom: 15px; }
        .code-inputs input { width: 48px; height: 48px; text-align: center; font-size: 24px; border: 1px solid #ccc; border-radius: 4px; }
        .resend-container { text-align: center; margin-top: 15px; }
        #resend-btn { background: none; border: none; color: #ff0000; cursor: pointer; font-size: 0.9rem; }
        #resend-btn:disabled { color: #999; cursor: not-allowed; }
        .timer { color: #666; font-size: 0.8rem; margin-top: 5px; }
        .password-strength { display: flex; gap: 5px; margin-top: 10px; }
        .strength-bar { height: 5px; flex-grow: 1; background: #ddd; border-radius: 2px; }
    </style>
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-header">
            <h1>Reset Password</h1>
            <p>Recover access to your account</p>
        </div>
        
        <?php if ($error): ?> <div class="alert alert-error"><span><?php echo $error; ?></span></div> <?php endif; ?>
        <?php if ($success): ?> <div class="alert alert-success"><span><?php echo $success; ?></span></div> <?php endif; ?>
        
        <?php if (!$showCodeForm && !$showPasswordForm): ?>
            <!-- Form 1: Email -->
            <form class="auth-form" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required value="<?php echo htmlspecialchars($old_email); ?>">
                </div>
                <button type="submit" name="send_reset_code" class="btn-primary">Send Reset Code</button>
            </form>
        <?php elseif ($showCodeForm): ?>
            <!-- Form 2: Verification -->
            <form class="auth-form" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="code-inputs">
                    <input type="text" name="reset_code[]" maxlength="1" required>
                    <input type="text" name="reset_code[]" maxlength="1" required>
                    <input type="text" name="reset_code[]" maxlength="1" required>
                    <input type="text" name="reset_code[]" maxlength="1" required>
                    <input type="text" name="reset_code[]" maxlength="1" required>
                    <input type="text" name="reset_code[]" maxlength="1" required>
                </div>
                <button type="submit" name="verify_reset_code" class="btn-primary">Verify Code</button>
                <div class="resend-container">
                    <button type="button" id="resend-btn" disabled>Resend Code</button>
                    <div class="timer" id="timer">60 seconds</div>
                </div>
            </form>
        <?php elseif ($showPasswordForm): ?>
            <!-- Form 3: Password Update -->
            <form class="auth-form" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="input-group">
                    <label>New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
                    <div class="password-strength">
                        <div class="strength-bar" id="strength-1"></div>
                        <div class="strength-bar" id="strength-2"></div>
                        <div class="strength-bar" id="strength-3"></div>
                        <div class="strength-bar" id="strength-4"></div>
                    </div>
                </div>
                <div class="input-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" name="reset_password" class="btn-primary">Reset Password</button>
            </form>
        <?php endif; ?>
        
        <div class="auth-footer">
            <p><a href="login.php">Back to Login</a></p>
        </div>
    </div>

    <!-- KEEPING YOUR EXACT JAVASCRIPT -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('.code-inputs input');
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                if (e.target.value.length === 1 && index < inputs.length - 1) inputs[index + 1].focus();
            });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !input.value && index > 0) inputs[index - 1].focus();
            });
        });

        const newPass = document.getElementById('new_password');
        if(newPass) {
            newPass.addEventListener('input', function() {
                let val = this.value;
                let strength = 0;
                if (val.length >= 8) strength++;
                if (/[A-Z]/.test(val)) strength++;
                if (/[0-9]/.test(val)) strength++;
                if (/[^A-Za-z0-9]/.test(val)) strength++;
                
                for(let i=1; i<=4; i++) {
                    document.getElementById('strength-'+i).style.backgroundColor = (i <= strength) ? '#ff0000' : '#ddd';
                }
            });
        }

        const timerDisp = document.getElementById('timer');
        const resendBtn = document.getElementById('resend-btn');
        if(timerDisp) {
            let left = 60;
            let interval = setInterval(() => {
                left--;
                timerDisp.textContent = left + " seconds";
                if(left <= 0) {
                    clearInterval(interval);
                    resendBtn.disabled = false;
                }
            }, 1000);
        }
    });
    </script>
</body>
</html>