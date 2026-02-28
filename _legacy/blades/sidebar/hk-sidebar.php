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
        
        // Define menu items with matching rules
        $menu_items = [
            [
                'url' => '/hk/dashboard',
                'icon' => 'fas fa-home',
                'text' => 'Dashboard',
                'match' => 'exact_path' // Match by filename only
            ],
            [
                'url' => '/hk/support',
                'icon' => 'fas fa-headset',
                'text' => 'Tickets',
                'match' => 'exact_path'
            ],
            [
                'url' => '/hk/users',
                'icon' => 'fas fa-user',
                'text' => 'Users',
                'match' => 'exact_path'
            ],
            [
                'url' => '/hk/products',
                'icon' => 'fas fa-shopping-basket',
                'text' => 'Products',
                'match' => 'exact_path'
            ],
            [
                'url' => '/hk/news',
                'icon' => 'fas fa-newspaper',
                'text' => 'News',
                'match' => 'exact_path'
            ],
            [
                'url' => '/hk/settings',
                'icon' => 'fas fa-cog',
                'text' => 'Settings',
                'match' => 'exact_path'
            ],
            [
                'url' => '/hk/licenses',
                'icon' => 'fas fa-key',
                'text' => 'Licenses',
                'match' => 'exact_path'
            ],
            [
                'url' => '/hk/stock-management',
                'icon' => 'fas fa-warehouse',
                'text' => 'Stock Management',
                'match' => 'exact_path'
            ],
            [
                'url' => '/hk/bans',
                'icon' => 'fas fa-ban',
                'text' => 'Bans',
                'match' => 'exact_path' // Must match full path exactly
            ],
            [
                'url' => '/hk/chat',
                'icon' => 'fas fa-comment',
                'text' => 'Chat',
                'match' => 'exact_path'
            ],
            [
                'url' => '/hk/downloads',
                'icon' => 'fas fa-download',
                'text' => 'Downloads',
                'match' => 'exact_path'
            ],
            [
                'url' => '/hk/transactions',
                'icon' => 'fas fa-receipt',
                'text' => 'Transactions',
                'match' => 'exact_path'
            ],
            [
                'url' => '/hk/logs',
                'icon' => 'fa fa-history',
                'text' => 'Logs',
                'match' => 'exact_path'
            ]
        ];
        
        // Generate menu items
        foreach ($menu_items as $item) {
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
            <form action="../logout.php" method="POST" class="logout-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <button type="submit" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </form>
        </div>
    </div>
</nav>