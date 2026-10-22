<?php
// =============================================================================
// Practical 10: Secure Authentication, Session Management & Role-Based Access
// Problem Definition:
// Develop secure login and logout functionality using PHP sessions. Verify
// password using password_verify(), regenerate session ID after login,
// restrict dashboard access, implement role-based redirection, session
// timeout, and optional secure remember-me token.
// =============================================================================

require_once __DIR__ . "/db.php";

/**
 * Configure secure session parameters and start session
 */
function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Secure cookie settings (HttpOnly and SameSite=Lax for CSRF mitigation)
        $cookieParams = [
            'lifetime' => 0,       // Session cookie expires when browser closes
            'path'     => '/',
            'httponly' => true,   // Prevent JavaScript access (XSS mitigation)
            'samesite' => 'Lax'    // Cross-site request forgery protection
        ];

        // Enable secure flag if HTTPS is active
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $cookieParams['secure'] = true;
        }

        session_set_cookie_params($cookieParams);
        session_start();
    }

    // 1. Session Inactivity Timeout Check (15 minutes = 900 seconds)
    $timeout_duration = 900;
    if (isset($_SESSION['student_id']) && isset($_SESSION['last_activity'])) {
        if ((time() - $_SESSION['last_activity']) > $timeout_duration) {
            // Session has expired due to inactivity
            logout_user(false); // Clear session without revoking remember-me token
            header("Location: login.php?msg=timeout");
            exit;
        }
    }

    // Update last activity timestamp for active logged-in user
    if (isset($_SESSION['student_id'])) {
        $_SESSION['last_activity'] = time();
        if (empty($_COOKIE['sh_auth'])) {
            setcookie('sh_auth', '1', 0, '/');
            setcookie('sh_role', isset($_SESSION['role']) ? $_SESSION['role'] : 'student', 0, '/');
        }
    } else {
        // 2. Check for optional secure Remember-Me token if session is not active
        check_remember_me_token();
    }
}

/**
 * Check if the user is currently authenticated
 */
function is_logged_in() {
    return isset($_SESSION['student_id']) && !empty($_SESSION['student_id']);
}

/**
 * Restrict page access: Redirect unauthenticated users to login page
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php?msg=auth_required");
        exit;
    }
}

/**
 * Restrict page access by user role ('student', 'admin')
 */
function require_role($requiredRole) {
    require_login();
    $currentRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'student';

    if ($currentRole !== $requiredRole) {
        if ($currentRole === 'student') {
            header("Location: dashboard.php?msg=access_denied");
        } else {
            header("Location: admin_dashboard.php?msg=access_denied");
        }
        exit;
    }
}

/**
 * Securely log in user with session ID regeneration and optional Remember-Me token
 */
function login_user($user, $rememberMe = false) {
    // Regenerate session ID immediately upon authentication to prevent Session Fixation
    session_regenerate_id(true);

    $_SESSION['student_id']    = $user['student_id'];
    $_SESSION['fullname']      = $user['fullname'];
    $_SESSION['enrollment_no'] = $user['enrollment_no'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['role']          = !empty($user['role']) ? $user['role'] : 'student';
    $_SESSION['department']    = !empty($user['department']) ? $user['department'] : 'Computer Engineering';
    $_SESSION['semester']      = !empty($user['semester']) ? $user['semester'] : 'Semester 3';
    $_SESSION['last_activity'] = time();

    // Client-side indicators for instant seamless navbar updates across all pages
    setcookie('sh_auth', '1', 0, '/');
    setcookie('sh_role', $_SESSION['role'], 0, '/');

    // Issue Remember-Me token if requested
    if ($rememberMe) {
        issue_remember_me_token($user['student_id']);
    }
}

/**
 * Generate and store cryptographically secure split Remember-Me token (selector:validator)
 */
function issue_remember_me_token($studentId) {
    global $conn;

    // Cryptographically secure pseudorandom token parts
    $selector  = bin2hex(random_bytes(16));  // 32 characters public selector
    $validator = bin2hex(random_bytes(32));  // 64 characters secret validator
    $tokenHash = hash('sha256', $validator); // SHA-256 hash stored in DB
    $expiresAt = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30-day token lifetime

    $stmt = mysqli_prepare($conn, "
        INSERT INTO auth_tokens (student_id, selector, token_hash, expires_at)
        VALUES (?, ?, ?, ?)
    ");

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "isss", $studentId, $selector, $tokenHash, $expiresAt);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Store cookie on client side with HttpOnly and SameSite flags
        $cookieValue = $selector . ':' . $validator;
        $cookieExpiry = time() + (86400 * 30);
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        setcookie('remember_token', $cookieValue, [
            'expires'  => $cookieExpiry,
            'path'     => '/',
            'httponly' => true,
            'secure'   => $secure,
            'samesite' => 'Lax'
        ]);
    }
}

/**
 * Validate client-side Remember-Me token and automatically restore session
 */
function check_remember_me_token() {
    global $conn;

    if (empty($_COOKIE['remember_token'])) {
        return false;
    }

    $parts = explode(':', $_COOKIE['remember_token']);
    if (count($parts) !== 2) {
        return false;
    }

    $selector  = $parts[0];
    $validator = $parts[1];

    $stmt = mysqli_prepare($conn, "
        SELECT t.token_id, t.student_id, t.token_hash, t.expires_at,
               s.fullname, s.enrollment_no, s.email, s.department, s.semester, s.role
        FROM auth_tokens t
        JOIN students s ON t.student_id = s.student_id
        WHERE t.selector = ? AND t.expires_at > NOW()
        LIMIT 1
    ");

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $selector);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            // Constant-time hash comparison prevents timing attacks
            if (hash_equals($row['token_hash'], hash('sha256', $validator))) {
                session_regenerate_id(true);

                $_SESSION['student_id']    = $row['student_id'];
                $_SESSION['fullname']      = $row['fullname'];
                $_SESSION['enrollment_no'] = $row['enrollment_no'];
                $_SESSION['email']         = $row['email'];
                $_SESSION['role']          = !empty($row['role']) ? $row['role'] : 'student';
                $_SESSION['department']    = $row['department'];
                $_SESSION['semester']      = $row['semester'];
                $_SESSION['last_activity'] = time();

                setcookie('sh_auth', '1', 0, '/');
                setcookie('sh_role', $_SESSION['role'], 0, '/');

                mysqli_stmt_close($stmt);
                return true;
            }
        }
        mysqli_stmt_close($stmt);
    }

    return false;
}

/**
 * Securely terminate session, clear session cookie, and remove Remember-Me token
 */
function logout_user($clearRememberToken = true) {
    global $conn;

    // Clear client-side navigation indicator cookies
    setcookie('sh_auth', '', time() - 3600, '/');
    setcookie('sh_role', '', time() - 3600, '/');

    // Remove Remember-Me token from database and clear cookie
    if ($clearRememberToken && !empty($_COOKIE['remember_token'])) {
        $parts = explode(':', $_COOKIE['remember_token']);
        if (count($parts) === 2 && !empty($conn)) {
            $selector = $parts[0];
            $stmt = mysqli_prepare($conn, "DELETE FROM auth_tokens WHERE selector = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $selector);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        setcookie('remember_token', '', time() - 3600, '/');
    }

    // Unset all session variables
    $_SESSION = [];

    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Destroy session storage
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

// Automatically initialize and check session on include
init_session();
