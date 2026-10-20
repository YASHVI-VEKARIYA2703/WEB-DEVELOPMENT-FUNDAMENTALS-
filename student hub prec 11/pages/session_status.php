<?php
// =============================================================================
// Practical 10: Session Status Endpoint for Dynamic Navigation Updates
// Returns current authentication status and user role as JSON
// =============================================================================

require_once __DIR__ . "/auth.php";

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$response = [
    'logged_in'   => is_logged_in(),
    'role'        => isset($_SESSION['role']) ? $_SESSION['role'] : null,
    'fullname'    => isset($_SESSION['fullname']) ? $_SESSION['fullname'] : null,
    'enrollment'  => isset($_SESSION['enrollment_no']) ? $_SESSION['enrollment_no'] : null
];

echo json_encode($response);
exit;
