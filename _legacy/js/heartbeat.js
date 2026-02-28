/**
 * Session Heartbeat Manager
 * Maintains active session by sending periodic requests to the server
 * and handles session timeout warnings.
 */
class SessionHeartbeat {
    constructor() {
        this.timeoutMinutes = 55; // Match PHP session timeout (60 minutes) minus buffer
        this.warningMinutes = 5; // Show warning 5 minutes before timeout
        this.checkInterval = 60000; // Check every minute (60000ms)
        this.pingInterval = 300000; // Ping every 5 minutes (300000ms)
        this.pingEndpoint = '/api/session_ping.php';
        this.statusEndpoint = '/api/session_status.php';
        
        this.lastActivity = Date.now();
        this.timeoutWarningShown = false;
        this.countdownInterval = null;
        this.pingIntervalId = null;
        
        this.init();
    }
    
    init() {
        // Set up activity listeners
        this.setupActivityListeners();
        
        // Start periodic checks
        setInterval(() => this.checkSession(), this.checkInterval);
        
        // Start periodic pings
        this.pingIntervalId = setInterval(() => this.sendPing(), this.pingInterval);
        
        // Initial check
        this.checkSession();
        this.sendPing();
    }
    
    setupActivityListeners() {
        // Track user activity
        const activities = ['mousemove', 'keydown', 'scroll', 'click', 'touchstart'];
        activities.forEach(event => {
            document.addEventListener(event, () => {
                this.lastActivity = Date.now();
                this.resetTimeoutWarning();
                
                // Send immediate ping if user is active after being idle
                if (Date.now() - this.lastPingTime > 60000) {
                    this.sendPing();
                }
            }, { passive: true });
        });
    }
    
    async sendPing() {
        try {
            await fetch(this.pingEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ ping: true })
            });
            this.lastPingTime = Date.now();
        } catch (error) {
            console.error('Session ping failed:', error);
        }
    }
    
    async checkSession() {
        try {
            // First check session status
            const statusResponse = await fetch(this.statusEndpoint, {
                cache: 'no-store'
            });
            const status = await statusResponse.json();
            
            if (!status.logged_in) {
                this.handleSessionEnd();
                return;
            }
            
            // Calculate remaining time
            const lastActive = status.last_activity * 1000; // Convert to ms
            const elapsed = Date.now() - lastActive;
            const remaining = (this.timeoutMinutes * 60000) - elapsed;
            
            // Show warning if approaching timeout
            if (remaining < (this.warningMinutes * 60000) && !this.timeoutWarningShown) {
                this.showTimeoutWarning(Math.floor(remaining / 60000));
            }
        } catch (error) {
            console.error('Session check failed:', error);
        }
    }
    
    showTimeoutWarning(minutesRemaining) {
        this.timeoutWarningShown = true;
        
        // Create warning modal
        const warning = document.createElement('div');
        warning.id = 'session-timeout-warning';
        warning.innerHTML = `
            <div class="session-warning-content">
                <h3>Session About to Expire</h3>
                <p>Your session will expire in <span id="session-countdown">${minutesRemaining}</span> minutes due to inactivity.</p>
                <p>Move your mouse or click anywhere to continue.</p>
                <button id="extend-session-btn">Stay Logged In</button>
            </div>
        `;
        
        document.body.appendChild(warning);
        
        // Start countdown
        let seconds = minutesRemaining * 60;
        this.countdownInterval = setInterval(() => {
            seconds--;
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            document.getElementById('session-countdown').textContent = 
                `${mins}:${secs < 10 ? '0' : ''}${secs}`;
            
            if (seconds <= 0) {
                this.handleSessionEnd();
            }
        }, 1000);
        
        // Add event listener to extend button
        document.getElementById('extend-session-btn').addEventListener('click', () => {
            this.resetTimeoutWarning();
            this.sendPing().then(() => this.checkSession());
        });
    }
    
    resetTimeoutWarning() {
        if (this.timeoutWarningShown) {
            clearInterval(this.countdownInterval);
            const warning = document.getElementById('session-timeout-warning');
            if (warning) warning.remove();
            this.timeoutWarningShown = false;
        }
    }
    
    handleSessionEnd() {
        // Clear all intervals
        clearInterval(this.pingIntervalId);
        clearInterval(this.countdownInterval);
        
        // Redirect to login page
        window.location.href = '/login.php?reason=session_expired';
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.sessionHeartbeat === 'undefined') {
        window.sessionHeartbeat = new SessionHeartbeat();
    }
});