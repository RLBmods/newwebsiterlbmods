<?php
// maintenance.php
require_once __DIR__ . '/includes/session.php';

// Allow staff/admin to bypass maintenance
if (isset($_SESSION['user_role'])) {
    $exemptRoles = ['support', 'developer', 'manager', 'founder'];
    if (in_array($_SESSION['user_role'], $exemptRoles)) {
        header("Location: /");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .maintenance-container {
            max-width: 600px;
            margin: 100px auto;
            text-align: center;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .maintenance-icon {
            font-size: 60px;
            color: #6a3ee7;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            margin-bottom: 30px;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-icon">
            <i class="fas fa-tools"></i>
        </div>
        <h1>Under Maintenance</h1>
        <p>We're currently performing scheduled maintenance. Please check back soon.</p>
        <p>Estimated downtime: 30 minutes</p>
        
        <?php if (!isLoggedIn()): ?>
            <p>Staff members can <a href="/login.php">login</a> to access the site during maintenance.</p>
        <?php endif; ?>
    </div>
</body>
</html>