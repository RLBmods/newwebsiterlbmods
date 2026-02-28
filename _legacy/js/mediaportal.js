document.addEventListener('DOMContentLoaded', function() {
    // Initialize Notyf for notifications
    const notyf = new Notyf({
        duration: 5000,
        position: { x: 'right', y: 'top' },
        types: [
            {
                type: 'success',
                background: '#4CAF50',
                icon: { className: 'fas fa-check-circle', tagName: 'i', color: '#fff' },
                dismissible: true
            },
            {
                type: 'error',
                background: '#F44336',
                icon: { className: 'fas fa-exclamation-circle', tagName: 'i', color: '#fff' },
                dismissible: true
            }
        ]
    });

    // DOM Elements
    const dom = {
        // Modals
        keyRequestModal: document.getElementById('keyRequestModal'),
        startStreamModal: document.getElementById('startStreamModal'),
        
        // Buttons
        requestKeyBtn: document.querySelector('.btn-request-key'),
        goLiveBtn: document.querySelector('.btn-go-live'),
        endStreamBtn: document.querySelector('.btn-end-stream'),
        
        // Forms
        keyRequestForm: document.getElementById('keyRequestForm'),
        startStreamForm: document.getElementById('startStreamForm'),
        endStreamForm: document.querySelector('.end-stream-form'),
        
        // Status elements
        statusIndicator: document.querySelector('.status-indicator'),
        statusText: document.querySelector('.stream-status span'),
        
        // Key elements
        activeKeysContainer: document.querySelector('.active-keys'),
        keyMetrics: document.querySelectorAll('.key-metric .metric-value'),
        
        // Activity elements
        activityList: document.querySelector('.activity-list')
    };

    // Timer variables
    let streamTimerInterval = null;
    let autoRefreshInterval = null;
    let currentStreamId = null; // Track currently active stream

    // Initialize application
    initModals();
    initStreamControls();
    initLicenseKeys();
    initActivityFeed();
    startAutoRefresh();

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        if (streamTimerInterval) clearInterval(streamTimerInterval);
        if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    });

    // =====================
    // AUTO REFRESH SYSTEM
    // =====================
    function startAutoRefresh() {
        // Refresh everything immediately
        refreshAll();
        
        // Then set up periodic refresh (every 30 seconds)
        autoRefreshInterval = setInterval(refreshAll, 30000);
    }

    async function refreshAll() {
        try {
            await Promise.all([
                updateStreamUI(),
                updateKeyList(),
                updateActivityFeed()
            ]);
        } catch (error) {
            console.error('Auto-refresh error:', error);
        }
    }

    // Helper Functions
    function initModals() {
        // Key Request Modal
        if (dom.requestKeyBtn && dom.keyRequestModal) {
            dom.requestKeyBtn.addEventListener('click', () => {
                dom.keyRequestModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                updateKeyLimits();
            });
        }

        // Close modals when clicking outside or close button
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal || e.target.classList.contains('close-modal') || 
                    e.target.classList.contains('btn-cancel-request') || 
                    e.target.classList.contains('btn-cancel-stream')) {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        });
    }

    // =====================
    // STREAM TIMER FUNCTIONS
    // =====================
    function clearStreamTimer() {
        if (streamTimerInterval) {
            clearInterval(streamTimerInterval);
            streamTimerInterval = null;
        }
        currentStreamId = null;
        dom.statusText.textContent = 'Currently Offline';
    }

    function formatDuration(startTime) {
        const start = new Date(startTime);
        const now = new Date();
        const diff = now - start; // difference in milliseconds
        
        const hours = Math.floor(diff / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        
        return `${hours}h ${minutes}m`;
    }

    function startStreamTimer(startTime, streamId) {
        // Clear any existing interval
        clearStreamTimer();
        
        // Store current stream ID
        currentStreamId = streamId;
        
        // Update immediately
        updateStreamTimer(startTime);
        
        // Update every 60 seconds (60000 milliseconds)
        streamTimerInterval = setInterval(() => {
            updateStreamTimer(startTime);
        }, 60000);
    }

    function updateStreamTimer(startTime) {
        const duration = formatDuration(startTime);
        dom.statusText.textContent = `Live Now (${duration})`;
    }

    // =====================
    // STREAM MANAGEMENT
    // =====================
    async function getCurrentStream() {
        try {
            const response = await fetch('/api/streams/status.php');
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            if (!data.success) throw new Error(data.error || 'Failed to get stream status');
            
            return data.data;
        } catch (error) {
            console.error('Stream status error:', error);
            throw error;
        }
    }

    async function startNewStream(streamData) {
        try {
            const response = await fetch('/api/streams/start.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(streamData)
            });
            
            const data = await response.json();
            if (!data.success) throw new Error(data.error || 'Failed to start stream');
            
            return data;
        } catch (error) {
            console.error('Start stream error:', error);
            throw error;
        }
    }

    async function endCurrentStream(streamId) {
        try {
            const response = await fetch('/api/streams/end.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ stream_id: streamId })
            });
            
            const data = await response.json();
            if (!data.success) throw new Error(data.error || 'Failed to end stream');
            
            return data;
        } catch (error) {
            console.error('End stream error:', error);
            throw error;
        }
    }

    async function updateStreamUI() {
        try {
            const stream = await getCurrentStream();
            
            if (stream) {
                // Only update timer if this is a new stream
                if (currentStreamId !== stream.id) {
                    startStreamTimer(stream.started_at, stream.id);
                }
                
                // Update UI for live stream
                dom.statusIndicator.classList.replace('offline', 'live');
                dom.goLiveBtn.style.display = 'none';
                dom.endStreamForm.style.display = 'block';
                dom.endStreamForm.querySelector('input[name="stream_id"]').value = stream.id;
            } else {
                // Update UI for offline
                dom.statusIndicator.classList.replace('live', 'offline');
                dom.goLiveBtn.style.display = 'block';
                dom.endStreamForm.style.display = 'none';
                clearStreamTimer();
            }
        } catch (error) {
            console.error('Update stream UI error:', error);
            notyf.error('Failed to load stream status');
        }
    }

    function initStreamControls() {
        // Go Live button
        if (dom.goLiveBtn && dom.startStreamModal) {
            dom.goLiveBtn.addEventListener('click', () => {
                dom.startStreamModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            });
        }

        // Start Stream Form
        if (dom.startStreamForm) {
            dom.startStreamForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submitBtn = dom.startStreamForm.querySelector('[type="submit"]');
                const originalText = submitBtn.innerHTML;
                
                try {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting...';
                    
                    const formData = {
                        title: dom.startStreamForm.querySelector('[name="title"]').value,
                        description: dom.startStreamForm.querySelector('[name="description"]').value,
                        platform: dom.startStreamForm.querySelector('[name="platform"]').value,
                        url: dom.startStreamForm.querySelector('[name="url"]').value
                    };
                    
                    const data = await startNewStream(formData);
                    
                    notyf.success(data.message);
                    dom.startStreamModal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                    dom.startStreamForm.reset();
                    
                    await updateStreamUI();
                    updateActivityFeed();
                    
                } catch (error) {
                    notyf.error(error.message);
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        }

        // End Stream Form
        if (dom.endStreamForm) {
            dom.endStreamForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submitBtn = dom.endStreamForm.querySelector('[type="submit"]');
                const originalText = submitBtn.innerHTML;
                
                try {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ending...';
                    
                    const streamId = dom.endStreamForm.querySelector('[name="stream_id"]').value;
                    const data = await endCurrentStream(streamId);
                    
                    notyf.success(data.message);
                    clearStreamTimer(); // Explicitly clear the timer
                    await updateStreamUI();
                    updateActivityFeed();
                    
                } catch (error) {
                    notyf.error(error.message);
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        }
        
        // Initialize stream UI
        updateStreamUI();
    }

    // =====================
    // LICENSE KEY MANAGEMENT
    // =====================
    async function requestLicenseKey(formData) {
        try {
            const response = await fetch('/api/keys/request.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            if (!data.success) throw new Error(data.error || 'Failed to request key');
            
            return data;
        } catch (error) {
            console.error('License key request error:', error);
            throw error;
        }
    }

    async function getLicenseKeys() {
        try {
            const response = await fetch('/api/keys/list.php');
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            if (!data.success) throw new Error(data.error || 'Failed to get keys');
            
            return data.keys || [];
        } catch (error) {
            console.error('Get license keys error:', error);
            throw error;
        }
    }

    async function getKeyLimits(product) {
        try {
            const response = await fetch(`/api/keys/limits.php?product=${encodeURIComponent(product)}`);
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            if (!data.success) throw new Error(data.error || 'Failed to get key limits');
            
            return data;
        } catch (error) {
            console.error('Get key limits error:', error);
            throw error;
        }
    }

    function renderLicenseKeys(keys) {
        if (!keys || keys.length === 0) {
            dom.activeKeysContainer.innerHTML = `
                <div class="no-keys">
                    <i class="fas fa-key"></i>
                    <p>No license keys found</p>
                </div>
            `;
            return;
        }

        dom.activeKeysContainer.innerHTML = keys.map(key => `
            <div class="key-card ${key.status}">
                <div class="key-meta">
                    <span class="key-product">Product: ${escapeHtml(key.product)}</span>
                    <span>Issued: ${formatDate(key.created_at)}</span>
                    ${key.status === 'approved' && key.expires_at ? 
                        `<span>Expires: ${formatDate(key.expires_at)}</span>` : ''}
                </div>
                <div class="key-value ${key.status !== 'approved' ? 'blurred' : ''}">
                    ${escapeHtml(key.key_value)}
                </div>
                <div class="key-purpose">
                    Reason: <i class="fas fa-${getPurposeIcon(key.purpose)}"></i>
                    ${escapeHtml(key.purpose)}
                </div>
                <div class="key-actions">
                    <button class="copy-key-btn ${key.status !== 'approved' ? 'disabled' : ''}" 
                        ${key.status !== 'approved' ? 'disabled' : ''}
                        data-key="${escapeHtml(key.key_value)}">
                        <i class="fas fa-copy"></i> <span>Copy</span>
                    </button>
                </div>
            </div>
        `).join('');

        initCopyButtons();
    }

    async function updateKeyLimits() {
        const productSelect = document.getElementById('key-product');
        if (!productSelect || !productSelect.value) return;

        try {
            const data = await getKeyLimits(productSelect.value);
            
            const modalHeader = document.querySelector('#keyRequestModal .modal-header');
            const existingLimitInfo = document.querySelector('.key-limit-info');
            if (existingLimitInfo) existingLimitInfo.remove();

            const limitInfo = document.createElement('div');
            limitInfo.className = 'key-limit-info';
            limitInfo.innerHTML = `
                <p><i class="fas fa-info-circle"></i> Used ${data.weekly_count}/3 weekly and ${data.monthly_count}/12 monthly keys</p>
                ${data.weekly_count >= 3 || data.monthly_count >= 12 ? 
                    '<p class="text-warning"><i class="fas fa-exclamation-triangle"></i> Limit reached for this product</p>' : ''}
            `;

            modalHeader.appendChild(limitInfo);
        } catch (error) {
            console.error('Update key limits error:', error);
            notyf.error('Failed to load key limits');
        }
    }

    async function updateKeyList() {
        try {
            const keys = await getLicenseKeys();
            renderLicenseKeys(keys);
            updateKeyCounts();
        } catch (error) {
            console.error('Update key list error:', error);
            notyf.error('Failed to load license keys');
        }
    }

    function updateKeyCounts() {
        const pendingCount = document.querySelectorAll('.key-card.pending').length;
        const approvedCount = document.querySelectorAll('.key-card.approved').length;
        const totalCount = pendingCount + approvedCount;

        dom.keyMetrics.forEach(metric => {
            const label = metric.parentElement.querySelector('.metric-label').textContent;
            if (label === 'Active Keys') metric.textContent = approvedCount;
            if (label === 'Pending') metric.textContent = pendingCount;
            if (label === 'Total Used') metric.textContent = totalCount;
        });
    }

    function initLicenseKeys() {
        // Key request form
        if (dom.keyRequestForm) {
            dom.keyRequestForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submitBtn = dom.keyRequestForm.querySelector('[type="submit"]');
                const originalText = submitBtn.innerHTML;
                
                try {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Requesting...';
                    
                    const formData = {
                        product: dom.keyRequestForm.querySelector('#key-product').value,
                        purpose: dom.keyRequestForm.querySelector('#key-purpose').value,
                        details: dom.keyRequestForm.querySelector('#key-details').value
                    };
                    
                    const data = await requestLicenseKey(formData);
                    
                    notyf.success(data.message);
                    dom.keyRequestModal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                    dom.keyRequestForm.reset();
                    
                    await updateKeyList();
                    
                } catch (error) {
                    notyf.error(error.message);
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        }

        // Product select change
        const productSelect = document.getElementById('key-product');
        if (productSelect) {
            productSelect.addEventListener('change', updateKeyLimits);
        }

        // Load initial keys
        updateKeyList();
    }

    // =====================
    // ACTIVITY FEED
    // =====================
    async function getRecentActivities() {
        try {
            const response = await fetch('/api/activity/recent.php');
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            if (!data.success) throw new Error(data.error || 'Failed to get activities');
            
            return data.activities || [];
        } catch (error) {
            console.error('Get activities error:', error);
            throw error;
        }
    }

    function renderActivities(activities) {
        if (!activities || activities.length === 0) {
            dom.activityList.innerHTML = `
                <div class="no-activity">
                    <i class="fas fa-info-circle"></i>
                    <p>No activities found</p>
                </div>
            `;
            return;
        }

        dom.activityList.innerHTML = activities.map(activity => `
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-${getActivityIcon(activity.type)}"></i>
                </div>
                <div class="activity-content">
                    <p>${escapeHtml(activity.description)}</p>
                    <span class="activity-time">${activity.time_ago}</span>
                </div>
            </div>
        `).join('');
    }

    async function updateActivityFeed() {
        try {
            const activities = await getRecentActivities();
            renderActivities(activities);
        } catch (error) {
            console.error('Update activity feed error:', error);
            dom.activityList.innerHTML = `
                <div class="error-activity">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Failed to load activities</p>
                </div>
            `;
        }
    }

    function initActivityFeed() {
        updateActivityFeed();
    }

    // =====================
    // UTILITY FUNCTIONS
    // =====================
    function initCopyButtons() {
        document.querySelectorAll('.copy-key-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (this.disabled) return;
                
                const key = this.getAttribute('data-key');
                if (!key) return;

                navigator.clipboard.writeText(key)
                    .then(() => {
                        const originalHTML = this.innerHTML;
                        this.innerHTML = '<i class="fas fa-check"></i> Copied!';
                        this.classList.add('copied');
                        
                        setTimeout(() => {
                            this.innerHTML = originalHTML;
                            this.classList.remove('copied');
                        }, 2000);
                        
                        notyf.success('Key copied to clipboard');
                    })
                    .catch(err => {
                        console.error('Failed to copy:', err);
                        notyf.error('Failed to copy key');
                    });
                
                e.stopPropagation();
            });
        });
    }

    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        });
    }

    function getPurposeIcon(purpose) {
        const icons = {
            'stream': 'broadcast-tower',
            'video': 'video',
            'event': 'calendar-star',
            'testing': 'flask',
            'other': 'question-circle'
        };
        return icons[purpose.toLowerCase()] || 'key';
    }

    function getActivityIcon(type) {
        const icons = {
            'stream': 'broadcast-tower',
            'key': 'key',
            'settings': 'cog',
            'default': 'bell'
        };
        return icons[type] || icons['default'];
    }
});