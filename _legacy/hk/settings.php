<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../db/connection.php';
require_once '../includes/logging.php';
require_once '../includes/get_user_info.php';

// Authentication
requireAuth();
requireStaff();

// Get user info
$userInfo = getUserInfo($_SESSION['user_id']);
if (!$userInfo) {
    header("Location: ../login.php");
    exit();
}

// Get current settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM site_settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError("Failed to fetch site settings: " . $e->getMessage());
    $settings = [
        'site_name' => 'RLBMODS',
        'site_domain' => '',
        'copyright' => 'Copyright &copy; 2023 RLBMODS. All rights reserved.',
        'favicon' => '/images/favicon.ico',
        'logo' => '/images/logo.png',
        'maintenance' => '0'
    ];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        switch ($section) {
            case 'branding':
                $stmt = $pdo->prepare("UPDATE site_settings SET 
                    site_name = ?, 
                    site_domain = ?,
                    copyright = ?
                    WHERE id = 1");
                $stmt->execute([
                    $_POST['site_name'],
                    $_POST['site_domain'],
                    $_POST['copyright']
                ]);
                break;
                
            case 'appearance':
                // Handle appearance settings
                break;
                
            case 'payment':
                // Handle payment settings
                break;
                
            case 'maintenance':
                $maintenance = isset($_POST['maintenance']) ? '1' : '0';
                $stmt = $pdo->prepare("UPDATE site_settings SET maintenance = ? WHERE id = 1");
                $stmt->execute([$maintenance]);
                break;
                
            // Add other sections as needed
        }
        
        $pdo->commit();
        $_SESSION['success_message'] = "Settings updated successfully!";
        header("Location: settings.php");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        logError("Failed to update settings: " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to update settings. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RLBMODS • Site Settings</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/hk/dashboard.css">
    <link rel="stylesheet" href="/css/hk/settings.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.11.2/css/all.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/heartbeat.js" defer></script>
    <script src="../js/notify.js" defer></script>
</head>
<body>
    <?php include_once('../blades/sidebar/hk-sidebar.php'); ?>
    
    <main class="main-content">
        <header class="main-header">
            <div class="header-left">
                <div class="breadcrumbs">
                    <a href="/" class="breadcrumb-item"><i class="fas fa-home"></i></a>
                    <span class="breadcrumb-separator">/</span>
                    <a href="dashboard.php" class="breadcrumb-item">Admin</a>
                    <span class="breadcrumb-separator">/</span>
                    <span class="breadcrumb-current">Site Settings</span>
                </div>
            </div>
            <?php include_once('../blades/notify/notify.php'); ?>
        </header>

        <div class="content-area-wrapper">
            <div class="hk-hero">
                <div class="banner-content">
                    <div class="hk-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <h1>Site Settings</h1>
                    <p class="hk-subtitle">Configure your website on the go.</p>
                </div>
            </div>

            <div class="settings-table-container">
                <!-- Navigation Sidebar -->
                <div class="settings-nav">
                    <div class="nav-group">
                        <div class="nav-header">Application</div>
                        <button class="nav-item active" data-section="branding">
                            <i class="fas fa-paint-brush"></i> Branding
                        </button>
                        <!-- <button class="nav-item" data-section="appearance">
                            <i class="fas fa-palette"></i> Appearance
                        </button> -->
                    </div>
                    
                    <!-- <div class="nav-group">
                        <div class="nav-header">Commerce</div>
                        <button class="nav-item" data-section="payment">
                            <i class="fas fa-credit-card"></i> Payment Gateway
                        </button>
                        <button class="nav-item" data-section="pricing">
                            <i class="fas fa-tags"></i> Pricing
                        </button>
                    </div> -->
                    
                    <!-- <div class="nav-group">
                        <div class="nav-header">Security</div>
                        <button class="nav-item" data-section="authentication">
                            <i class="fas fa-user-shield"></i> Authentication
                        </button>
                        <button class="nav-item" data-section="api">
                            <i class="fas fa-key"></i> API Settings
                        </button>
                    </div> -->
                    
                    <div class="nav-group">
                        <div class="nav-header">System</div>
                        <button class="nav-item" data-section="maintenance">
                            <i class="fas fa-tools"></i> Maintenance
                        </button>
                        <!-- <button class="nav-item" data-section="updates">
                            <i class="fas fa-cloud-download-alt"></i> Updates
                        </button> -->
                    </div>
                </div>
            
                <!-- Branding Section -->
                <form method="POST" class="settings-content-box" id="branding-section">
                    <input type="hidden" name="section" value="branding">
                    <div class="content-box-header">
                        <i class="fas fa-paint-brush"></i>
                        <h3>Branding Settings</h3>
                    </div>
                    <div class="content-box-body">
                        <!-- Logo Settings -->
                        <div class="settings-card">
                            <div class="card-header">
                                <h3>Logo Settings</h3>
                                <p>Customize your site's logo and branding</p>
                            </div>
                            <div class="card-body">
                                <div class="logo-upload-area">
                                    <div class="logo-preview-container">
                                        <div class="logo-preview">
                                            <img src="<?= htmlspecialchars($settings['logo'] ?? '/images/logo.png') ?>" alt="Current Logo">
                                            <div class="logo-overlay">
                                                <button type="button" class="btn-upload">Change</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="logo-actions">
                                        <button type="button" class="btn-secondary" id="upload-logo-btn">
                                            <i class="fas fa-upload"></i> Upload New Logo
                                        </button>
                                        <button type="button" class="btn-secondary" style="margin-left: 10px;" id="remove-logo-btn">
                                            <i class="fas fa-trash"></i> Remove Logo
                                        </button>
                                        <p class="hint">Recommended size: 300x100px, PNG format with transparent background</p>
                                        <input type="hidden" name="logo" id="logo-input" value="<?= htmlspecialchars($settings['logo'] ?? '') ?>">
                                    </div>
                                </div>
                                
                                <!-- Favicon Settings -->
                                <div class="form-group">
                                    <label>Favicon</label>
                                    <div class="logo-upload-area">
                                        <div class="logo-preview-container">
                                            <div class="logo-preview" style="width: 64px; height: 64px;">
                                                <img src="<?= htmlspecialchars($settings['favicon'] ?? '/images/favicon.ico') ?>" alt="Current Favicon">
                                                <div class="logo-overlay">
                                                    <button type="button" class="btn-upload">Change</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="logo-actions">
                                            <button type="button" class="btn-secondary" id="upload-favicon-btn">
                                                <i class="fas fa-upload"></i> Upload New Favicon
                                            </button>
                                            <button type="button" class="btn-secondary" style="margin-left: 10px;" id="remove-favicon-btn">
                                                <i class="fas fa-trash"></i> Remove Favicon
                                            </button>
                                            <p class="hint">Recommended size: 64x64px, ICO or PNG format</p>
                                            <input type="hidden" name="favicon" id="favicon-input" value="<?= htmlspecialchars($settings['favicon'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Site Identity -->
                        <div class="settings-card">
                            <div class="card-header">
                                <h3>Site Identity</h3>
                                <p>Set your website's name and description</p>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="site-name">Site Name</label>
                                    <input type="text" id="site-name" name="site_name" class="input-field" value="<?= htmlspecialchars($settings['site_name'] ?? 'RLBMODS') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="site-domain">Site Domain</label>
                                    <input type="text" id="site-domain" name="site_domain" class="input-field" value="<?= htmlspecialchars($settings['site_domain'] ?? '') ?>" placeholder="https://example.com">
                                </div>
                                
                                <div class="form-group">
                                    <label for="copyright">Copyright Text</label>
                                    <input type="text" id="copyright" name="copyright" class="input-field" value="<?= htmlspecialchars($settings['copyright'] ?? 'Copyright &copy; '.date('Y').' RLBMODS. All rights reserved.') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Save Button -->
                        <div class="form-group" style="text-align: right;">
                            <button type="submit" class="btn-save-settings">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Appearance Section -->
                <form method="POST" class="settings-content-box" id="appearance-section" style="display: none;">
                    <input type="hidden" name="section" value="appearance">
                    <div class="content-box-header">
                        <i class="fas fa-palette"></i>
                        <h3>Appearance Settings</h3>
                    </div>
                    <div class="content-box-body">
                        <!-- Theme Settings -->
                        <div class="settings-card">
                            <div class="card-header">
                                <h3>Theme Settings</h3>
                                <p>Customize your site's color scheme</p>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label>Primary Color</label>
                                    <div class="input-with-action">
                                        <input type="text" class="input-field color-picker" value="#6a3ee7" name="primary_color">
                                        <button type="button" class="btn-action color-picker-btn">
                                            <i class="fas fa-eye-dropper"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Dark Mode</label>
                                    <label class="checkbox-container">Enable Dark Mode
                                        <input type="checkbox" name="dark_mode" checked>
                                        <span class="checkmark"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Layout Settings -->
                        <div class="settings-card">
                            <div class="card-header">
                                <h3>Layout Settings</h3>
                                <p>Configure your site's layout options</p>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label>Sidebar Position</label>
                                    <select class="select-field" name="sidebar_position">
                                        <option value="left">Left</option>
                                        <option value="right">Right</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Default Dashboard View</label>
                                    <select class="select-field" name="dashboard_view">
                                        <option value="grid">Grid View</option>
                                        <option value="list">List View</option>
                                        <option value="compact">Compact View</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Save Button -->
                        <div class="form-group" style="text-align: right;">
                            <button type="submit" class="btn-save-settings">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Payment Gateway Section -->
                <form method="POST" class="settings-content-box" id="payment-section" style="display: none;">
                    <input type="hidden" name="section" value="payment">
                    <div class="content-box-header">
                        <i class="fas fa-credit-card"></i>
                        <h3>Payment Gateway Settings</h3>
                    </div>
                    <div class="content-box-body">
                        <!-- Stripe Settings -->
                        <div class="settings-card">
                            <div class="card-header">
                                <h3>Stripe Integration</h3>
                                <p>Configure your Stripe payment gateway</p>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="checkbox-container">Enable Stripe Payments
                                        <input type="checkbox" name="stripe_enabled" checked>
                                        <span class="checkmark"></span>
                                    </label>
                                </div>
                                
                                <div class="form-group">
                                    <label for="stripe-key">Publishable Key</label>
                                    <input type="text" id="stripe-key" name="stripe_publishable_key" class="input-field" placeholder="pk_test_...">
                                </div>
                                
                                <div class="form-group">
                                    <label for="stripe-secret">Secret Key</label>
                                    <div class="input-with-action">
                                        <input type="password" id="stripe-secret" name="stripe_secret_key" class="input-field" placeholder="sk_test_...">
                                        <button type="button" class="btn-action toggle-password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- PayPal Settings -->
                        <div class="settings-card">
                            <div class="card-header">
                                <h3>PayPal Integration</h3>
                                <p>Configure your PayPal payment gateway</p>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="checkbox-container">Enable PayPal Payments
                                        <input type="checkbox" name="paypal_enabled">
                                        <span class="checkmark"></span>
                                    </label>
                                </div>
                                
                                <div class="form-group">
                                    <label for="paypal-client">Client ID</label>
                                    <input type="text" id="paypal-client" name="paypal_client_id" class="input-field" placeholder="Client ID">
                                </div>
                                
                                <div class="form-group">
                                    <label for="paypal-secret">Secret Key</label>
                                    <div class="input-with-action">
                                        <input type="password" id="paypal-secret" name="paypal_secret_key" class="input-field" placeholder="Secret Key">
                                        <button type="button" class="btn-action toggle-password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Sandbox Mode</label>
                                    <label class="checkbox-container">Enable Sandbox Mode (for testing)
                                        <input type="checkbox" name="paypal_sandbox" checked>
                                        <span class="checkmark"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Save Button -->
                        <div class="form-group" style="text-align: right;">
                            <button type="submit" class="btn-save-settings">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Maintenance Section -->
                <form method="POST" class="settings-content-box" id="maintenance-section" style="display: none;">
                    <input type="hidden" name="section" value="maintenance">
                    <div class="content-box-header">
                        <i class="fas fa-tools"></i>
                        <h3>Maintenance Mode</h3>
                    </div>
                    <div class="content-box-body">
                        <div class="settings-card">
                            <div class="card-header">
                                <h3>Maintenance Settings</h3>
                                <p>Take your site offline for maintenance</p>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="checkbox-container">Enable Maintenance Mode
                                        <input type="checkbox" name="maintenance" <?= ($settings['maintenance'] ?? '0') === '1' ? 'checked' : '' ?>>
                                        <span class="checkmark"></span>
                                    </label>
                                    <p class="hint">When enabled, only administrators can access the site.</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="maintenance-message">Maintenance Message</label>
                                    <textarea id="maintenance-message" name="maintenance_message" class="textarea-field" rows="4">We're currently performing maintenance. Please check back soon.</textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Save Button -->
                        <div class="form-group" style="text-align: right;">
                            <button type="submit" class="btn-save-settings">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Add other sections similarly -->
                
            </div>
        </div>
        
        <footer class="main-footer">
            <p>&copy; <?= date('Y') ?> RLBMODS. All rights reserved.</p>
        </footer>
    </main>
    
    <!-- File Upload Modal -->
    <div class="modal-overlay" id="upload-modal" style="display: none;">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Upload File</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="upload-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Select File</label>
                        <input type="file" id="file-input" class="input-field" accept="image/*">
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-admin btn-cancel">Cancel</button>
                        <button type="button" class="btn-admin btn-submit" id="upload-submit">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="/js/hk/settings.js"></script>
    <script>
        const currentUser = {
            username: '<?= htmlspecialchars($userInfo['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>',
            role: '<?= htmlspecialchars($userInfo['role'] ?? '', ENT_QUOTES, 'UTF-8') ?>'
        };
        
        // Initialize settings
        const siteSettings = <?= json_encode($settings) ?>;
    </script>
</body>
</html>
<?php ob_end_flush(); ?>