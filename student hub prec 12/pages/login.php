<?php
// =============================================================================
// Practical 10: Secure Login with Sessions, Role-Based Access, Timeout & Token
// Problem Definition:
// Develop secure login and logout functionality using PHP sessions. Verify
// password using password_verify(), regenerate session ID after login,
// restrict dashboard access, implement role-based redirection, session
// timeout, and optional secure remember-me token.
// =============================================================================

require_once __DIR__ . "/auth.php";

// If already logged in, redirect based on role
if (is_logged_in()) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$errorMessage   = "";
$noticeMessage  = "";
$noticeType     = "info"; // info, success, warning
$username       = "";

// Handle status messages passed via GET parameter
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'timeout':
            $noticeMessage = "Your session has expired due to inactivity (15-minute timeout). Please log in again.";
            $noticeType    = "warning";
            break;
        case 'logged_out':
            $noticeMessage = "You have been logged out securely. Session destroyed.";
            $noticeType    = "success";
            break;
        case 'auth_required':
            $noticeMessage = "Access restricted: Please log in first to view the dashboard.";
            $noticeType    = "warning";
            break;
        case 'access_denied':
            $noticeMessage = "Access denied: Administrator privileges required for that section.";
            $noticeType    = "warning";
            break;
    }
}

// Process Login Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username   = isset($_POST["username"]) ? trim($_POST["username"]) : "";
    $password   = isset($_POST["password"]) ? $_POST["password"] : "";
    $rememberMe = !empty($_POST["remember_me"]);

    if (empty($username) || empty($password)) {
        $errorMessage = "Please enter both your Enrollment Number / Email and Password.";
    } else {
        // Query user from students table by enrollment_no OR email using MySQLi Prepared Statement
        $sql = "SELECT student_id, fullname, enrollment_no, email, mobile, department, semester, password, role 
                FROM students 
                WHERE enrollment_no = ? OR email = ? 
                LIMIT 1";

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $username, $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user   = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            // Verify password using password_verify()
            if ($user && password_verify($password, $user['password'])) {
                // Successful verification: Regenerate session ID and store session state
                login_user($user, $rememberMe);

                // Role-Based Redirection
                if (isset($user['role']) && $user['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit;
            } else {
                $errorMessage = "Invalid Enrollment ID / Email or Password. Please try again.";
            }
        } else {
            $errorMessage = "Database query error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Student Hub</title>
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
        .login-alert {
            padding: 10px 14px;
            border-radius: 5px;
            font-size: 13.5px;
            margin-bottom: 16px;
            line-height: 1.4;
            font-weight: 500;
            text-align: left;
        }
        .login-alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .login-alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .login-alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .login-alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 12px;
            margin-bottom: 8px;
        }
        .remember-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            cursor: pointer;
            margin: 0;
            font-weight: normal;
            color: inherit;
        }
        .remember-label input[type="checkbox"] {
            width: auto;
            margin: 0;
            cursor: pointer;
        }
        .demo-box {
            margin-top: 18px;
            padding: 10px 12px;
            background-color: #f0f4f8;
            border-radius: 6px;
            font-size: 12px;
            color: #1B1F44;
            border: 1px dashed #b0c4de;
            line-height: 1.5;
        }
        body.dark-mode .demo-box {
            background-color: #2a2a2a;
            color: #ffd700;
            border-color: #555;
        }
    </style>
</head>

<body class="login-body">
    
    <!-- HEADER -->
    <iframe
        src="header.html"
        class="header-frame">
    </iframe>

    <h1 class="page-title">STUDENT LOGIN</h1>

    <!-- NAVIGATION -->
    <nav>
        <a href="dashboard.php">DASHBOARD</a>
        <a href="event.html">EVENTS</a>
        <a href="course.html">COURSES</a>
        <a href="register.php">REGISTER</a>
        <a href="login.php" class="active">LOGIN</a>
        <a href="contact.html">CONTACT</a>
    </nav>

    <!-- LOGIN FORM BOX -->
    <div class="form-box">
        <h2>Student &amp; Portal Login</h2>

        <!-- Notice Banner -->
        <?php if (!empty($noticeMessage)): ?>
            <div class="login-alert login-alert-<?= htmlspecialchars($noticeType) ?>">
                <?= htmlspecialchars($noticeMessage) ?>
            </div>
        <?php endif; ?>

        <!-- Error Banner -->
        <?php if (!empty($errorMessage)): ?>
            <div class="login-alert login-alert-error">
                ⚠️ <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <form id="loginForm" action="login.php" method="POST">
            <label for="username">Enrollment No / ID or Email:</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" placeholder="Enter Enrollment ID or Email" required>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" placeholder="Enter Password" required>

            <!-- REMEMBER ME TOKEN CHECKBOX -->
            <div class="remember-row">
                <label class="remember-label">
                    <input type="checkbox" name="remember_me" value="1">
                    Remember Me
                </label>
                <span style="font-size: 12px; color: #666;">(30-day secure token)</span>
            </div>

            <input type="submit" value="Login">

            <p style="text-align: center; margin-top: 15px; font-size: 14px;">
                Don't have an account? <a href="register.php" style="color: blue; font-weight: bold;">Register Here</a>
            </p>
        </form>
    </div>

    <!-- HOME BUTTON AT BOTTOM RIGHT -->
    <a href="../index.html" class="home-btn">🏠 Home</a>

    <!-- FOOTER -->
    <footer>
        &copy; 2026 STUDENT HUB • CHARUSAT University • All Rights Reserved.
    </footer>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out'): ?>
        <script>
            try {
                localStorage.removeItem('sh_logged_in');
                localStorage.removeItem('sh_role');
            } catch(e) {}
        </script>
    <?php endif; ?>
    <script src="../js/script.js"></script>

</body>
</html>
