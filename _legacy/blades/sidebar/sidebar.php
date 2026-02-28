<nav class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <img src="<?php echo htmlspecialchars($site_logo, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $site_name; ?>" height="63" width="210">
        </div>
    </div>
    
    <div class="sidebar-menu">
        <?php
        // Get current page URL path
        $current_url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Define menu items with matching rules and required roles
        $menu_items = [
            [
                'url' => 'dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'text' => 'Dashboard',
                'match' => 'basename',
                'roles' => null // Available to all
            ],
            [
                'url' => 'support',
                'icon' => 'fas fa-headset',
                'text' => 'Support',
                'match' => 'basename',
                'roles' => null
            ],
            [
                'url' => 'profile',
                'icon' => 'fas fa-user',
                'text' => 'Profile',
                'match' => 'basename',
                'roles' => null
            ],
            [
                'url' => 'giveaway',
                'icon' => 'fas fa-gift',
                'text' => 'Giveaway',
                'match' => 'basename',
                'roles' => null
            ],

            [
                'url' => 'topup',
                'icon' => 'fas fa-coins',
                'text' => 'Topup',
                'match' => 'basename',
                'roles' => null
            ],
            [
                'url' => 'shop',
                'icon' => 'fas fa-shopping-basket',
                'text' => 'Shop',
                'match' => 'basename',
                'roles' => null
            ],
            [
                'url' => 'download',
                'icon' => 'fal fa-download',
                'text' => 'Download',
                'match' => 'basename',
                'roles' => ['customer','media', 'developer', 'manager', 'founder']
            ],
            // [
            //     'url' => 'activation',
            //     'icon' => 'fal fa-key',
            //     'text' => 'Activation',
            //     'match' => 'basename',
            //     'roles' => ['customer','media', 'developer', 'manager', 'founder']
            // ],
            [
                'url' => '/mp/dashboard',
                'icon' => 'fal fa-video',
                'text' => 'Media Portal',
                'match' => 'exact_path',
                'roles' => ['media', 'developer', 'manager', 'founder'] // Only for media role
            ],
            [
                'url' => '/reseller/dashboard',
                'icon' => 'fal fa-handshake',
                'text' => 'Reseller Portal',
                'match' => 'exact_path',
                'roles' => ['reseller', 'developer', 'manager', 'founder'] // Only for resellers
            ],
            [
                'url' => '/hk/dashboard',
                'icon' => 'fal fa-eye',
                'text' => 'Admin Area',
                'match' => 'exact_path',
                'roles' => ['support', 'developer', 'manager', 'founder'] // For admin roles
            ]
        ];
        
        // Generate menu items with role checks
        foreach ($menu_items as $item) {
            // Check if user has required role (if specified)
            if (isset($item['roles']) && $item['roles'] !== null) {
                $has_role = false;
                foreach ($item['roles'] as $required_role) {
                    $user_roles = is_array($_SESSION['user_role'] ?? null) ? $_SESSION['user_role'] : [$_SESSION['user_role'] ?? ''];
if (in_array($required_role, $user_roles)) {                        $has_role = true;
                        break;
                    }
                }
                if (!$has_role) {
                    continue; // Skip this menu item if user doesn't have required role
                }
            }
            
            $is_active = false;
            $current_base = basename(preg_replace('/\.(html|php)$/', '', $current_url));
            $item_base = basename(preg_replace('/\.(html|php)$/', '', $item['url']));
            
            switch ($item['match']) {
                case 'basename':
                    $is_active = ($current_base === $item_base);
                    break;
                
                case 'exact_path':
                    // Remove extensions and compare full paths
                    $normalized_current = preg_replace('/\.(html|php)$/', '', $current_url);
                    $normalized_item = preg_replace('/\.(html|php)$/', '', $item['url']);
                    $is_active = ($normalized_current === $normalized_item);
                    break;
            }
            
            echo '<a href="' . htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') . '" class="menu-item' . ($is_active ? ' active' : '') . '">';
            echo '<i class="' . htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') . '"></i>';
            echo '<span>' . htmlspecialchars($item['text'], ENT_QUOTES, 'UTF-8') . '</span>';
            echo '</a>';
        }
        ?>
    </div>

    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="avatar">
                <?php if (isset($profile_picture) && !empty($profile_picture)): ?>
                    <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture" class="profile-pic">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <span class="username"><?php echo htmlspecialchars($username); ?></span>
            </div>
            <form action="/logout.php" method="POST" class="logout-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <button type="submit" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </form>
        </div>
    </div>
</nav>