/**
 * Notification System
 * Handles loading and displaying user notifications with 60-second polling
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('[Notifications] Initializing notification system...');

    // DOM Elements
    const notificationBell = document.querySelector('.notification-bell');
    const notificationDropdown = document.querySelector('.notification-dropdown');
    const notificationList = document.querySelector('.notification-list');
    const notificationCount = document.querySelector('.notification-count');
    const markAllReadBtn = document.querySelector('.mark-all-read');
    
    // State variables
    let isLoading = false;
    let dropdownVisible = false;
    let currentUnreadCount = 0;
    let pollInterval;

    // Initialize the notification system
    function init() {
        console.log('[Notifications] Setting up initial state');
        setupEventListeners();
        loadInitialNotifications();
        setupPolling();
    }

    function setupEventListeners() {
        console.log('[Notifications] Setting up event listeners');
        
        // Toggle dropdown
        if (notificationBell) {
            notificationBell.addEventListener('click', function(e) {
                e.stopPropagation();
                console.log('[Notifications] Bell clicked, toggling dropdown');
                toggleDropdown();
            });
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            if (dropdownVisible) {
                console.log('[Notifications] Click outside, closing dropdown');
                hideDropdown();
            }
        });
        
        // Mark all as read
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                console.log('[Notifications] Mark all as read clicked');
                markAllNotificationsAsRead();
            });
        }
    }

    function loadInitialNotifications() {
        console.log('[Notifications] Loading initial notifications');
        loadNotifications(true);
    }

    function setupPolling() {
        console.log('[Notifications] Setting up 60-second polling');
        // Clear any existing interval
        if (pollInterval) {
            clearInterval(pollInterval);
        }
        
        // Set up new interval (60 seconds)
        pollInterval = setInterval(() => {
            console.log('[Notifications] Polling for new notifications');
            loadNotifications(false).then((hasNew) => {
                if (hasNew && !dropdownVisible) {
                    console.log('[Notifications] New notifications found while closed');
                    pulseBell();
                    showDesktopNotification();
                }
            });
        }, 60000);
    }

    function toggleDropdown() {
        if (dropdownVisible) {
            hideDropdown();
        } else {
            showDropdown();
        }
    }

    function showDropdown() {
        if (isLoading) {
            console.log('[Notifications] Dropdown open prevented - already loading');
            return;
        }
        
        console.log('[Notifications] Showing dropdown');
        notificationDropdown.style.display = 'block';
        dropdownVisible = true;
        
        // Refresh if data is older than 30 seconds
        loadNotifications(true);
    }

    function hideDropdown() {
        console.log('[Notifications] Hiding dropdown');
        notificationDropdown.style.display = 'none';
        dropdownVisible = false;
    }

    async function loadNotifications(showLoading = true) {
        if (isLoading) {
            console.log('[Notifications] Load already in progress, skipping');
            return false;
        }
        
        isLoading = true;
        console.log('[Notifications] Starting notification load');
        
        if (showLoading && dropdownVisible) {
            showLoadingState();
        }
        
        try {
            console.log('[Notifications] Fetching from API');
            const response = await fetch(`../api/user/notify.php?t=${Date.now()}`);
            
            if (!response.ok) {
                console.error('[Notifications] API response not OK:', response.status, response.statusText);
                throw new Error(`Server returned ${response.status}`);
            }
            
            const data = await response.json();
            console.log('[Notifications] API response:', data);
            
            if (data.success) {
                console.log(`[Notifications] Success, found ${data.notifications.length} notifications, ${data.unreadCount} unread`);
                const countChanged = updateNotificationCount(data.unreadCount || 0);
                
                if (dropdownVisible) {
                    if (data.notifications && data.notifications.length > 0) {
                        renderNotifications(data.notifications);
                    } else {
                        showEmptyState();
                    }
                }
                
                return countChanged;
            } else {
                console.error('[Notifications] API returned error:', data.error);
                if (dropdownVisible) {
                    showErrorState(data.error || 'Failed to load notifications');
                }
                return false;
            }
        } catch (error) {
            console.error('[Notifications] Error loading notifications:', error);
            if (dropdownVisible) {
                showErrorState(error.message || 'Connection error');
            }
            return false;
        } finally {
            isLoading = false;
            console.log('[Notifications] Load completed');
        }
    }

    function showLoadingState() {
        console.log('[Notifications] Showing loading state');
        notificationList.innerHTML = `
            <div class="notification-loading">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading notifications...</p>
            </div>
        `;
    }

    function showEmptyState() {
        console.log('[Notifications] Showing empty state');
        notificationList.innerHTML = `
            <div class="notification-empty">
                <i class="fas fa-bell-slash"></i>
                <p>No notifications yet</p>
            </div>
        `;
    }

    function showErrorState(message) {
        console.error('[Notifications] Showing error state:', message);
        notificationList.innerHTML = `
            <div class="notification-error">
                <i class="fas fa-exclamation-triangle"></i>
                <p>${escapeHtml(message)}</p>
                <button class="retry-load">Try Again</button>
            </div>
        `;
        
        // Properly attach retry listener
        const retryBtn = notificationList.querySelector('.retry-load');
        if (retryBtn) {
            retryBtn.addEventListener('click', function() {
                console.log('[Notifications] Retry button clicked');
                loadNotifications(true);
            });
        }
    }

    function updateNotificationCount(count) {
        const previousCount = currentUnreadCount;
        currentUnreadCount = count;
        
        console.log(`[Notifications] Updating count from ${previousCount} to ${count}`);
        
        if (count > 0) {
            notificationCount.textContent = count > 99 ? '99+' : count;
            notificationCount.style.display = 'block';
        } else {
            notificationCount.style.display = 'none';
        }
        
        return (count !== previousCount);
    }

    function renderNotifications(notifications) {
        console.log(`[Notifications] Rendering ${notifications.length} notifications`);
        notificationList.innerHTML = '';
        
        const fragment = document.createDocumentFragment();
        
        notifications.forEach(notification => {
            const notificationItem = createNotificationElement(notification);
            fragment.appendChild(notificationItem);
        });
        
        notificationList.appendChild(fragment);
    }

    function createNotificationElement(notification) {
        const notificationItem = document.createElement('div');
        notificationItem.className = `notification-item ${notification.is_read ? '' : 'unread'}`;
        notificationItem.dataset.id = notification.id;
        
        const { iconClass, iconColor } = getNotificationIcon(notification.type);
        const timeAgo = formatTimeAgo(notification.created_at);
        
        notificationItem.innerHTML = `
            <div class="notification-icon" style="color: ${iconColor}">
                <i class="${iconClass}"></i>
            </div>
            <div class="notification-content">
                <p class="notification-title">${escapeHtml(notification.title)}</p>
                <p class="notification-text">${escapeHtml(notification.message)}</p>
                <p class="notification-time">${timeAgo}</p>
            </div>
        `;
        
        if (!notification.is_read) {
            notificationItem.addEventListener('click', function() {
                console.log(`[Notifications] Marking notification ${notification.id} as read`);
                markNotificationAsRead(notification.id);
                this.classList.remove('unread');
                updateNotificationCount(Math.max(0, currentUnreadCount - 1));
            });
        }
        
        return notificationItem;
    }

    function getNotificationIcon(type) {
        const icons = {
            password_changed: { iconClass: 'fas fa-key', iconColor: 'var(--success)' },
            mention: { iconClass: 'fas fa-at', iconColor: 'var(--primary)' },
            balance_added: { iconClass: 'fas fa-coins', iconColor: 'var(--warning)' },
            license_generate: { iconClass: 'fas fa-key', iconColor: 'var(--primary'},
            default: { iconClass: 'fas fa-bell', iconColor: 'var(--text-color)' }
        };
        return icons[type] || icons.default;
    }

    function pulseBell() {
        if (!notificationBell) return;
        console.log('[Notifications] Pulsing bell icon');
        notificationBell.classList.add('pulse');
        setTimeout(() => {
            notificationBell.classList.remove('pulse');
        }, 1000);
    }

    function showDesktopNotification() {
        if (!("Notification" in window)) {
            console.log('[Notifications] Desktop notifications not supported');
            return;
        }
        
        if (Notification.permission === "granted" && currentUnreadCount > 0) {
            console.log('[Notifications] Showing desktop notification');
            new Notification(`You have ${currentUnreadCount} new notification(s)`, {
                body: 'Click the bell icon to view them',
                icon: '/favicon.ico'
            });
        }
        else if (Notification.permission !== "denied") {
            console.log('[Notifications] Requesting notification permission');
            Notification.requestPermission().then(permission => {
                if (permission === "granted" && currentUnreadCount > 0) {
                    new Notification(`You have ${currentUnreadCount} new notification(s)`, {
                        body: 'Click the bell icon to view them',
                        icon: '/favicon.ico'
                    });
                }
            });
        }
    }

    async function markNotificationAsRead(notificationId) {
        try {
            console.log(`[Notifications] Marking notification ${notificationId} as read`);
            await fetch('../api/user/notify.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: notificationId })
            });
        } catch (error) {
            console.error('[Notifications] Error marking notification as read:', error);
        }
    }

    async function markAllNotificationsAsRead() {
        try {
            console.log('[Notifications] Marking all notifications as read');
            const response = await fetch('../api/user/notify.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mark_all_read: true })
            });
            
            if (response.ok) {
                console.log('[Notifications] All marked as read successfully');
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                });
                updateNotificationCount(0);
            }
        } catch (error) {
            console.error('[Notifications] Error marking all as read:', error);
            showErrorState('Failed to mark all as read');
        }
    }

    function formatTimeAgo(timestamp) {
        const now = new Date();
        const notificationTime = new Date(timestamp);
        const seconds = Math.floor((now - notificationTime) / 1000);
        
        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
        return `${Math.floor(seconds / 86400)}d ago`;
    }

    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe.toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Clean up on page unload
    window.addEventListener('beforeunload', () => {
        console.log('[Notifications] Cleaning up before unload');
        clearInterval(pollInterval);
    });

    // Request notification permission on first interaction
    document.body.addEventListener('click', function requestNotificationPermission() {
        if ("Notification" in window && Notification.permission === "default") {
            console.log('[Notifications] Requesting notification permission');
            Notification.requestPermission();
        }
        document.body.removeEventListener('click', requestNotificationPermission);
    }, { once: true });

    // Initialize the system
    init();
});