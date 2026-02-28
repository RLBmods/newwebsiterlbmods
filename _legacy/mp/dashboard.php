<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once '../includes/bootstrap.php';
requireAuth();
requireMedia();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $site_name ?> - Media Portal</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/mediaportal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.11.2/css/all.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/heartbeat.js" defer></script>
    <script src="../js/notify.js" defer></script>
    <link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="sidebar-overlay"></div>
    <?php include_once('../blades/sidebar/sidebar.php'); ?>
    
    <main class="main-content">
        <header class="main-header">
            <div class="header-left">
                <div class="breadcrumbs">
                    <a href="/"><i class="fas fa-home" style="color: #FF0004;"></i></a>
                    <span>/</span>
                    <span>Media Portal</span>
                </div>
            </div>
            <?php include_once('../blades/notify/notify.php'); ?>
        </header>
        
        <div class="content-area-wrapper">
            <div class="media-hero gaming-hero">
                <div class="hero-overlay"></div>
                <div class="media-hero-content">
                    <div class="media-icon">
                        <i class="fas fa-broadcast-tower"></i>
                    </div>
                    <h2>Content Creator Portal</h2>
                    <p>Manage your streaming activities and license keys</p>
                </div>
            </div>
            
            <section class="media-portal">
                <!-- Stream Management -->
                <div class="portal-card">
                    <div class="card-header">
                        <i class="fas fa-broadcast-tower"></i>
                        <h3>Stream Management</h3>
                    </div>
                    <div class="live-controls">
                        <div class="stream-status">
                            <div class="status-indicator offline"></div>
                            <span>Loading stream status...</span>
                        </div>
                        <div class="stream-actions">
                            <button class="btn-primary btn-go-live">
                                <i class="fas fa-broadcast-tower"></i> <span>Go Live</span>
                            </button>
                            <form class="end-stream-form" style="display: none;">
                                <input type="hidden" name="stream_id" value="">
                                <button type="submit" class="btn-danger btn-end-stream">
                                    <i class="fas fa-stop"></i> <span>End Stream</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- License Key Management -->
                <div class="portal-card">
                    <div class="card-header">
                        <i class="fas fa-key"></i>
                        <h3>License Key Management</h3>
                    </div>
                    <div class="license-keys-container">
                        <div class="key-management">
                            <div class="key-actions">
                                <button class="btn-primary btn-request-key">
                                    <i class="fas fa-plus"></i> <span>Request New Key</span>
                                </button>
                            </div>
                            <div class="key-status">
                                <div class="key-metric">
                                    <span class="metric-value">0</span>
                                    <span class="metric-label">Active Keys</span>
                                </div>
                                <div class="key-metric">
                                    <span class="metric-value">0</span>
                                    <span class="metric-label">Pending</span>
                                </div>
                                <div class="key-metric">
                                    <span class="metric-value">0</span>
                                    <span class="metric-label">Total Used</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="current-keys">
                            <h4>Your License Keys</h4>
                            <div class="active-keys">
                                <div class="no-keys">
                                    <i class="fas fa-key"></i>
                                    <p>Loading license keys...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="portal-card activity-feed">
                    <div class="card-header">
                        <i class="fas fa-clock"></i>
                        <h3>Recent Activity</h3>
                    </div>
                    <div class="activity-list">
                        <div class="activity-placeholder">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading activities...</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        
        <!-- Key Request Modal -->
        <div class="modal-overlay" id="keyRequestModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Request License Key</h3>
                    <button class="close-modal">&times;</button>
                </div>
                <form id="keyRequestForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="key-product">Product</label>
                            <select id="key-product" name="product" required>
                                <option value="" disabled selected>Select a product</option>
                                <option value="Fortnite - Public">Fortnite - Public</option>
                                <option value="Fortnite - Private">Fortnite - Private</option>
                                <option value="Temp Spoofer">Temp Spoofer</option>
                                <option value="BO6">BO6</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="key-purpose">Reason</label>
                            <select id="key-purpose" name="purpose" required>
                                <option value="" disabled selected>Select a reason</option>
                                <option value="stream">Live Stream</option>
                                <option value="video">Video Content</option>
                                <option value="event">Special Event</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="key-details">Additional Details</label>
                            <textarea id="key-details" name="details" placeholder="Explain how you'll use this key..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary btn-cancel-request">Cancel</button>
                        <button type="submit" class="btn-primary btn-submit-request">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Start Stream Modal -->
        <div class="modal-overlay" id="startStreamModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Start New Stream</h3>
                    <button class="close-modal">&times;</button>
                </div>
                <form id="startStreamForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Stream Title</label>
                            <input type="text" name="title" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Platform</label>
                            <select name="platform" required>
                                <option value="youtube" selected>YouTube</option>
                                <option value="twitch">Twitch</option>
                                <option value="tiktok">TikTok</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Stream URL</label>
                            <input type="url" name="url" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary btn-cancel-stream">Cancel</button>
                        <button type="submit" class="btn-primary btn-start-stream">Go Live</button>
                    </div>
                </form>
            </div>
        </div>
        
        <footer class="main-footer">
            <p>&copy; <?= date('Y') ?> RLBMODS. All rights reserved.</p>
        </footer>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
    <script src="../js/mediaportal.js"></script>
</body>
</html>