<?php
// =============================================================================
// Practical 10: Secure Logout Functionality
// Problem Definition:
// Develop secure login and logout functionality using PHP sessions.
// =============================================================================

require_once __DIR__ . "/auth.php";

// Terminate session, delete session cookies, and clear Remember-Me token from database
logout_user(true);

// Redirect to login page with logged_out status notification
header("Location: login.php?msg=logged_out");
exit;
