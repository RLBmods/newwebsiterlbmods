<?php
/**
 * Secure Session Management System
 * 
 * Features:
 * - Database-backed session storage
 * - CSRF protection
 * - Session fixation prevention
 * - Role-based access control
 * - Maintenance mode handling
 * - Session expiration tracking
 */

// Database connection
require_once __DIR__ . '/../db/connection.php';

// Only configure and start session if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    // ======================
    // SESSION CONFIGURATION
    // ======================
    ini_set('session.gc_maxlifetime', 3600); // 1 hour session lifetime
    ini_set('session.cookie_lifetime', 3600); // 1 hour cookie lifetime
    ini_set('session.cookie_secure', 1); // Only send cookies over HTTPS
    ini_set('session.cookie_httponly', 1); // Prevent JavaScript access
    ini_set('session.cookie_samesite', 'None'); // Strict same-site policy
    ini_set('session.use_strict_mode', 1); // Prevent session fixation
    ini_set('session.sid_length', 128); // Strong session IDs
    ini_set('session.sid_bits_per_character', 6); // More entropy
    
    // ======================
    // DATABASE SESSION HANDLER
    // ======================
// In session.php, replace the DatabaseSessionHandler class with:

class DatabaseSessionHandler implements SessionHandlerInterface {
    private $pdo;
    
    public function __construct($pdoConnection) {
        $this->pdo = $pdoConnection;
    }
    
    public function open(string $savePath, string $sessionName): bool {
        return true;
    }
    
    public function close(): bool {
        return true;
    }
    
    public function read(string $id): string {
        try {
            $stmt = $this->pdo->prepare("SELECT data FROM sessions WHERE id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['data'] : '';
        } catch (Exception $e) {
            error_log("Session read error: " . $e->getMessage());
            return '';
        }
    }
    
    public function write(string $id, string $data): bool {
        try {
            $user_id = $_SESSION['user_id'] ?? 0;
            $stmt = $this->pdo->prepare("
                REPLACE INTO sessions (id, user_id, data, last_activity) 
                VALUES (?, ?, ?, NOW())
            ");
            return $stmt->execute([$id, $user_id, $data]);
        } catch (Exception $e) {
            error_log("Session write error: " . $e->getMessage());
            return false;
        }
    }
    
    public function destroy(string $id): bool {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Session destroy error: " . $e->getMessage());
            return false;
        }
    }
    
    public function gc(int $maxlifetime): int {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM sessions 
                WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$maxlifetime]);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Session GC error: " . $e->getMessage());
            return 0;
        }
    }
}

// Then initialize with PDO
$handler = new DatabaseSessionHandler($pdo); // Use PDO connection

// Then initialize with PDO instead of MySQLi
$handler = new DatabaseSessionHandler($pdo); // Use PDO instead of $con

    // Initialize custom session handler
    $handler = new DatabaseSessionHandler($con);
    session_set_save_handler($handler, true);
    
    // Set secure session name and start session
    session_name('SECURE_SESSION');
    session_start();
    
    // ======================
    // SESSION SECURITY
    // ======================
    // Regenerate ID periodically to prevent fixation
    if (empty($_SESSION['created'])) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    } elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// ======================
// SESSION INITIALIZATION
// ======================
function initializeSessionVariables(): void {
    $defaults = [
        'user_id' => null,
        'user_name' => null,
        'user_email' => null,
        'user_role' => 'guest',
        'last_activity' => time(),
        'discord_id' => null,
        'discord_token' => null,
        'csrf_token' => bin2hex(random_bytes(32)),
        'redirect_url' => null,
        'created' => time(),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ];

    foreach ($defaults as $key => $value) {
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = $value;
        }
    }
}

initializeSessionVariables();

// ======================
// SESSION VALIDATION
// ======================
function validateSession(): bool {
    // Check if session IP matches current IP
    if ($_SESSION['ip_address'] !== ($_SERVER['REMOTE_ADDR'] ?? null)) {
        return false;
    }
    
    // Check if user agent matches
    if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? null)) {
        return false;
    }
    
    // Check if session is expired
    if (isSessionExpired()) {
        return false;
    }
    
    // NEW: Check if user is banned
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
        if (isUserBanned($_SESSION['user_id'])) {
            handleBannedUser();
            return false; // This line won't be reached due to exit() in handleBannedUser
        }
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    return true;
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

function isSessionExpired(int $timeout = 3600): bool {
    return isset($_SESSION['last_activity']) && 
           (time() - $_SESSION['last_activity'] > $timeout);
}

// ======================
// MAINTENANCE MODE
// ======================
function checkMaintenanceMode(): bool {
    global $con;
    
    // Exempt staff/admin from maintenance
    if (isset($_SESSION['user_role'])) {
        $exemptRoles = ['support', 'developer', 'manager', 'founder'];
        if (in_array($_SESSION['user_role'], $exemptRoles)) {
            return false;
        }
    }
    
    // Check maintenance mode from database
    $result = $con->query("SELECT maintenance FROM site_settings LIMIT 1");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['maintenance'] == '1';
    }
    
    return false;
}

// ======================
// AUTHENTICATION CHECKS
// ======================
function requireAuth(): void {
    // First check maintenance mode
    if (checkMaintenanceMode()) {
        header("Location: /maintenance.php");
        exit();
    }
    
    // Then validate session
    if (!validateSession()) {
        handleInvalidSession();
    }
    
    // Finally check if logged in
    if (!isLoggedIn()) {
        handleUnauthenticated();
    }
    
    // NEW: Additional ban check (redundant but safe)
    if (isUserBanned($_SESSION['user_id'])) {
        handleBannedUser();
    }
}

function handleInvalidSession(): void {
    // Store current URL for redirect back
    if (!isset($_GET['reason'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    }
    
    // Clear session and redirect
    session_unset();
    session_destroy();
    header("Location: /login.php?reason=invalid_session");
    exit();
}

function handleUnauthenticated(): void {
    // Store current URL for redirect back
    if (!isset($_GET['reason'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    }
    
    header("Location: /login.php?reason=not_logged_in");
    exit();
}

// ======================
// USER MANAGEMENT
// ======================
function getUserInfo($user_id) {
    global $con;
    
    $stmt = $con->prepare("SELECT id, name as username, email, role, balance, status, current_ip, last_ip, discordid 
                          FROM usertable 
                          WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows === 1 ? $result->fetch_assoc() : false;
}

function getAuthenticatedUser() {
    if (!validateSession() || !isLoggedIn()) {
        return false;
    }
    
    // NEW: Check if user is banned
    if (isUserBanned($_SESSION['user_id'])) {
        handleBannedUser();
        return false; // This line won't be reached due to exit() in handleBannedUser
    }

    $_SESSION['last_activity'] = time();
    return getUserInfo($_SESSION['user_id']);
}

// ======================
// ROLE-BASED ACCESS CONTROL
// ======================
function requireRole(array $allowed_roles): void {
    requireAuth();
    
    if (!in_array($_SESSION['user_role'], $allowed_roles)) {
        header("Location: /index.php?reason=unauthorized");
        exit();
    }
}

function requireAdmin(): void { 
    requireRole(['manager', 'founder']); 
}

function requireStaff(): void { 
    requireRole(['support', 'developer', 'manager', 'founder']); 
}

function requireReseller(): void { 
    requireRole(['reseller', 'support', 'manager', 'developer', 'founder']); 
}

function requireMedia(): void { 
    requireRole(['media', 'support', 'developer', 'manager', 'founder']); 
}

function requireCustomer(): void { 
    requireRole(['customer', 'media', 'reseller', 'support', 'developer', 'manager', 'founder']); 
}

function requireMember(): void { 
    requireRole(['member', 'customer', 'media', 'reseller', 'support', 'developer', 'manager', 'founder']); 
}


// ======================
// BAN MANAGEMENT
// ======================
function isUserBanned($user_id): bool {
    global $con;
    
    $stmt = $con->prepare("
        SELECT id 
        FROM bans 
        WHERE user_id = ? 
        AND is_active = 1 
        AND (expires_at > NOW() OR is_permanent = 1)
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_banned = $result->num_rows > 0;
    $stmt->close();
    
    return $is_banned;
}

function getActiveBan($user_id) {
    global $con;
    
    $stmt = $con->prepare("
        SELECT b.*, u.name as banned_by_name 
        FROM bans b 
        LEFT JOIN usertable u ON b.banned_by = u.id 
        WHERE b.user_id = ? 
        AND b.is_active = 1 
        AND (b.expires_at > NOW() OR b.is_permanent = 1)
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ban = $result->fetch_assoc();
    $stmt->close();
    
    return $ban;
}

function handleBannedUser(): void {
    // Log the banned session access
    if (isset($_SESSION['user_id'])) {
        logSecurityEvent(
            "Banned user session detected",
            "Banned user attempted to access protected content",
            [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['user_name'] ?? 'Unknown',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ],
            "warning"
        );
    }
    
    // Clear session and redirect
    session_unset();
    session_destroy();
    header("Location: /login.php?reason=banned");
    exit();
}

// ======================
// MUTE MANAGEMENT (Updated for mutes table)
// ======================
function isUserMuted($user_id): bool {
    global $con;
    
    $stmt = $con->prepare("
        SELECT id 
        FROM mutes 
        WHERE user_id = ? 
        AND status = 'active'
        AND (length IS NULL OR length > NOW())
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_muted = $result->num_rows > 0;
    $stmt->close();
    
    return $is_muted;
}

function getMuteDetails($user_id) {
    global $con;
    
    $stmt = $con->prepare("
        SELECT m.*, 
               u.name as muted_by_name,
               mu.name as muted_username
        FROM mutes m
        LEFT JOIN usertable u ON m.muted_by = u.id
        LEFT JOIN usertable mu ON m.user_id = mu.id
        WHERE m.user_id = ? 
        AND m.status = 'active'
        AND (m.length IS NULL OR m.length > NOW())
        ORDER BY m.created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $mute_details = $result->fetch_assoc();
    $stmt->close();
    
    return $mute_details;
}

function getMuteTimeRemaining($expires_at): string {
    if ($expires_at === null) {
        return "Permanent";
    }
    
    $now = new DateTime();
    $expiry = new DateTime($expires_at);
    
    if ($now >= $expiry) {
        return "Expired";
    }
    
    $interval = $now->diff($expiry);
    
    $time_parts = [];
    if ($interval->d > 0) $time_parts[] = $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
    if ($interval->h > 0) $time_parts[] = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '');
    if ($interval->i > 0) $time_parts[] = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
    if ($interval->s > 0 && count($time_parts) < 2) $time_parts[] = $interval->s . ' second' . ($interval->s > 1 ? 's' : '');
    
    return implode(', ', $time_parts);
}

// ======================
// LOGOUT & SESSION DESTRUCTION
// ======================
function secureLogout($user_id = null, $con = null) {
    // Log logout event
    if ($user_id) {
        logUserAction(
            $user_id,
            "Session destroyed",
            "User session was destroyed",
            [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'reason' => 'session_destruction'
            ],
            'info'
        );
    }
    
    // Clear session data
    $_SESSION = [];
    
    // Delete session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    // Destroy database session if connection provided
    if ($con && isset($_COOKIE[session_name()])) {
        try {
            $session_id = $_COOKIE[session_name()];
            $delete_stmt = $con->prepare("DELETE FROM sessions WHERE id = ?");
            $delete_stmt->bind_param("s", $session_id);
            $delete_stmt->execute();
            $delete_stmt->close();
        } catch (Exception $e) {
            error_log("Error deleting session from database: " . $e->getMessage());
        }
    }
    
    // Destroy the session
    session_destroy();
    
    // Clear authentication cookies
    setcookie('remember_me', '', time() - 3600, '/', '', true, true);
    setcookie('auth_token', '', time() - 3600, '/', '', true, true);
}

function forceLogoutAllSessions($user_id, $con) {
    try {
        // Delete all sessions for this user
        $stmt = $con->prepare("DELETE FROM sessions WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        
        logSecurityEvent(
            "All sessions force-logged out",
            "All active sessions for user were terminated",
            ['user_id' => $user_id],
            "info"
        );
        
        return true;
    } catch (Exception $e) {
        error_log("Error force-logging out sessions: " . $e->getMessage());
        return false;
    }
}

// ======================
// SESSION UTILITIES
// ======================
function checkSessionStatus(): array {
    return [
        'valid' => validateSession(),
        'logged_in' => isLoggedIn(),
        'expired' => isSessionExpired(),
        'last_activity' => $_SESSION['last_activity'] ?? null,
        'user_id' => $_SESSION['user_id'] ?? null,
        'user_role' => $_SESSION['user_role'] ?? null
    ];
}

function destroySession(): void {
    // Clear session data
    $_SESSION = [];
    
    // Delete session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    // Destroy session
    session_destroy();
}

function regenerateSession(): void {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? null;
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
}