<?php
// =============================================================================
// Practical 11: Student Management Module
// Problem Definition:
// Create a student management module to add, view, edit, delete, search and
// filter student records. All database operations must use MYSQLi statements
// and clear success/failure messages.
// =============================================================================

require_once __DIR__ . "/auth.php";

// Initialize session if not active
if (session_status() === PHP_SESSION_NONE) {
    init_session();
}

$isLoggedIn = is_logged_in();
$userRole   = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
$userName   = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 'Administrator / Evaluator';

$message     = "";
$messageType = ""; // 'success' or 'error'

// =============================================================================
// 1. HANDLE POST DATABASE OPERATIONS (MYSQLi PREPARED STATEMENTS)
// =============================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {

    $action = $_POST["action"];

    // -------------------------------------------------------------------------
    // A. ADD NEW STUDENT RECORD
    // -------------------------------------------------------------------------
    if ($action === "add") {
        $fullname   = isset($_POST["fullname"]) ? trim($_POST["fullname"]) : "";
        $enrollment = isset($_POST["enrollment_no"]) ? trim(strtoupper($_POST["enrollment_no"])) : "";
        $email      = isset($_POST["email"]) ? trim(strtolower($_POST["email"])) : "";
        $mobile     = isset($_POST["mobile"]) ? trim($_POST["mobile"]) : "";
        $department = isset($_POST["department"]) ? trim($_POST["department"]) : "Computer Engineering";
        $semester   = isset($_POST["semester"]) ? trim($_POST["semester"]) : "Semester 3";
        $password   = isset($_POST["password"]) ? $_POST["password"] : "";

        // Backend Validations
        if (empty($fullname) || empty($enrollment) || empty($email) || empty($mobile) || empty($password)) {
            $message     = "Failed to add student: All fields (Name, Enrollment, Email, Mobile, Password) are required.";
            $messageType = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message     = "Failed to add student: Please provide a valid email address.";
            $messageType = "error";
        } elseif (!preg_match("/^[0-9]{10}$/", $mobile)) {
            $message     = "Failed to add student: Mobile number must be exactly 10 digits.";
            $messageType = "error";
        } elseif (strlen($password) < 6) {
            $message     = "Failed to add student: Password must be at least 6 characters long.";
            $messageType = "error";
        } else {
            // Check for duplicate Enrollment Number or Email using MySQLi Prepared Statement
            $checkSql = "SELECT student_id, enrollment_no, email FROM students WHERE enrollment_no = ? OR email = ? LIMIT 1";
            $checkStmt = mysqli_prepare($conn, $checkSql);
            if ($checkStmt) {
                mysqli_stmt_bind_param($checkStmt, "ss", $enrollment, $email);
                mysqli_stmt_execute($checkStmt);
                $checkRes = mysqli_stmt_get_result($checkStmt);
                $existing = mysqli_fetch_assoc($checkRes);
                mysqli_stmt_close($checkStmt);

                if ($existing) {
                    if (strtoupper($existing['enrollment_no']) === $enrollment) {
                        $message     = "Failed to add student: Enrollment Number '$enrollment' already exists in the database.";
                    } else {
                        $message     = "Failed to add student: Email address '$email' is already registered with another student.";
                    }
                    $messageType = "error";
                } else {
                    // Insert new student using MySQLi Prepared Statement
                    $passHash = password_hash($password, PASSWORD_DEFAULT);
                    $insertSql = "INSERT INTO students (fullname, enrollment_no, email, mobile, department, semester, password, role) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, 'student')";
                    $insertStmt = mysqli_prepare($conn, $insertSql);
                    if ($insertStmt) {
                        mysqli_stmt_bind_param($insertStmt, "sssssss", $fullname, $enrollment, $email, $mobile, $department, $semester, $passHash);
                        if (mysqli_stmt_execute($insertStmt)) {
                            $newId       = mysqli_insert_id($conn);
                            $message     = "Success! Student record #$newId ('$fullname' - $enrollment) was successfully created in MySQL.";
                            $messageType = "success";
                        } else {
                            $message     = "Database Failure: Unable to insert student (" . mysqli_error($conn) . ").";
                            $messageType = "error";
                        }
                        mysqli_stmt_close($insertStmt);
                    } else {
                        $message     = "Database Preparation Error: " . mysqli_error($conn);
                        $messageType = "error";
                    }
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // B. EDIT EXISTING STUDENT RECORD
    // -------------------------------------------------------------------------
    elseif ($action === "edit") {
        $studentId  = isset($_POST["student_id"]) ? intval($_POST["student_id"]) : 0;
        $fullname   = isset($_POST["fullname"]) ? trim($_POST["fullname"]) : "";
        $enrollment = isset($_POST["enrollment_no"]) ? trim(strtoupper($_POST["enrollment_no"])) : "";
        $email      = isset($_POST["email"]) ? trim(strtolower($_POST["email"])) : "";
        $mobile     = isset($_POST["mobile"]) ? trim($_POST["mobile"]) : "";
        $department = isset($_POST["department"]) ? trim($_POST["department"]) : "";
        $semester   = isset($_POST["semester"]) ? trim($_POST["semester"]) : "";
        $newPassword= isset($_POST["new_password"]) ? trim($_POST["new_password"]) : "";

        if ($studentId <= 0) {
            $message     = "Failed to edit: Invalid student ID.";
            $messageType = "error";
        } elseif (empty($fullname) || empty($enrollment) || empty($email) || empty($mobile) || empty($department) || empty($semester)) {
            $message     = "Failed to edit: All profile details must be filled.";
            $messageType = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message     = "Failed to edit: Invalid email address format.";
            $messageType = "error";
        } elseif (!preg_match("/^[0-9]{10}$/", $mobile)) {
            $message     = "Failed to edit: Mobile number must be 10 digits.";
            $messageType = "error";
        } else {
            // Verify student exists and is not admin
            $verifySql = "SELECT student_id, role, fullname FROM students WHERE student_id = ? LIMIT 1";
            $verifyStmt = mysqli_prepare($conn, $verifySql);
            mysqli_stmt_bind_param($verifyStmt, "i", $studentId);
            mysqli_stmt_execute($verifyStmt);
            $vRes = mysqli_stmt_get_result($verifyStmt);
            $studentRow = mysqli_fetch_assoc($vRes);
            mysqli_stmt_close($verifyStmt);

            if (!$studentRow) {
                $message     = "Failed to edit: Student record #$studentId does not exist.";
                $messageType = "error";
            } elseif ($studentRow['role'] === 'admin') {
                $message     = "Action denied: System Administrator credentials cannot be edited from this interface.";
                $messageType = "error";
            } else {
                // Check if another student has the same Enrollment No or Email
                $dupSql = "SELECT student_id, enrollment_no, email FROM students WHERE (enrollment_no = ? OR email = ?) AND student_id != ? LIMIT 1";
                $dupStmt = mysqli_prepare($conn, $dupSql);
                mysqli_stmt_bind_param($dupStmt, "ssi", $enrollment, $email, $studentId);
                mysqli_stmt_execute($dupStmt);
                $dupRes = mysqli_stmt_get_result($dupStmt);
                $dupMatch = mysqli_fetch_assoc($dupRes);
                mysqli_stmt_close($dupStmt);

                if ($dupMatch) {
                    if (strtoupper($dupMatch['enrollment_no']) === $enrollment) {
                        $message     = "Failed to edit: Enrollment Number '$enrollment' is already used by student #{$dupMatch['student_id']}.";
                    } else {
                        $message     = "Failed to edit: Email address '$email' is already used by student #{$dupMatch['student_id']}.";
                    }
                    $messageType = "error";
                } else {
                    // Update record with or without password change
                    if (!empty($newPassword)) {
                        if (strlen($newPassword) < 6) {
                            $message     = "Failed to edit: New password must be at least 6 characters.";
                            $messageType = "error";
                        } else {
                            $newPassHash = password_hash($newPassword, PASSWORD_DEFAULT);
                            $updateSql = "UPDATE students SET fullname = ?, enrollment_no = ?, email = ?, mobile = ?, department = ?, semester = ?, password = ? WHERE student_id = ? AND role != 'admin'";
                            $updateStmt = mysqli_prepare($conn, $updateSql);
                            mysqli_stmt_bind_param($updateStmt, "sssssssi", $fullname, $enrollment, $email, $mobile, $department, $semester, $newPassHash, $studentId);
                        }
                    } else {
                        $updateSql = "UPDATE students SET fullname = ?, enrollment_no = ?, email = ?, mobile = ?, department = ?, semester = ? WHERE student_id = ? AND role != 'admin'";
                        $updateStmt = mysqli_prepare($conn, $updateSql);
                        mysqli_stmt_bind_param($updateStmt, "ssssssi", $fullname, $enrollment, $email, $mobile, $department, $semester, $studentId);
                    }

                    if (!empty($updateStmt)) {
                        if (mysqli_stmt_execute($updateStmt)) {
                            $message     = "Success! Student record #$studentId ('$fullname') was successfully updated in MySQL.";
                            $messageType = "success";
                        } else {
                            $message     = "Database Failure: Unable to update student (" . mysqli_error($conn) . ").";
                            $messageType = "error";
                        }
                        mysqli_stmt_close($updateStmt);
                    }
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // C. DELETE STUDENT RECORD
    // -------------------------------------------------------------------------
    elseif ($action === "delete") {
        $studentId = isset($_POST["student_id"]) ? intval($_POST["student_id"]) : 0;

        if ($studentId <= 0) {
            $message     = "Failed to delete: Invalid student ID.";
            $messageType = "error";
        } else {
            // Check student existence & protect admin
            $findSql = "SELECT student_id, fullname, enrollment_no, role FROM students WHERE student_id = ? LIMIT 1";
            $findStmt = mysqli_prepare($conn, $findSql);
            mysqli_stmt_bind_param($findStmt, "i", $studentId);
            mysqli_stmt_execute($findStmt);
            $fRes = mysqli_stmt_get_result($findStmt);
            $target = mysqli_fetch_assoc($fRes);
            mysqli_stmt_close($findStmt);

            if (!$target) {
                $message     = "Failed to delete: Student record #$studentId was not found.";
                $messageType = "error";
            } elseif ($target['role'] === 'admin') {
                $message     = "Action denied: The System Administrator account cannot be deleted.";
                $messageType = "error";
            } else {
                // Execute DELETE query via MySQLi Prepared Statement
                $delSql = "DELETE FROM students WHERE student_id = ? AND role != 'admin'";
                $delStmt = mysqli_prepare($conn, $delSql);
                if ($delStmt) {
                    mysqli_stmt_bind_param($delStmt, "i", $studentId);
                    if (mysqli_stmt_execute($delStmt) && mysqli_stmt_affected_rows($delStmt) > 0) {
                        $deletedName   = htmlspecialchars($target['fullname']);
                        $deletedEnroll = htmlspecialchars($target['enrollment_no']);
                        $message       = "Success! Student record #$studentId ('$deletedName' - $deletedEnroll) has been permanently removed from the database.";
                        $messageType   = "success";
                    } else {
                        $message     = "Database Failure: Unable to delete student record (" . mysqli_error($conn) . ").";
                        $messageType = "error";
                    }
                    mysqli_stmt_close($delStmt);
                }
            }
        }
    }
}

// =============================================================================
// 2. SEARCH & FILTER RETRIEVAL USING MYSQLi PREPARED STATEMENTS
// =============================================================================
$searchTerm = isset($_GET["search"]) ? trim($_GET["search"]) : "";
$filterDept = isset($_GET["department"]) ? trim($_GET["department"]) : "ALL";
$filterSem  = isset($_GET["semester"]) ? trim($_GET["semester"]) : "ALL";

// Base query for students (exclude administrative accounts from student list)
$whereClauses = ["role != 'admin'"];
$params       = [];
$types        = "";

if (!empty($searchTerm)) {
    $whereClauses[] = "(fullname LIKE ? OR enrollment_no LIKE ? OR email LIKE ? OR mobile LIKE ?)";
    $like = "%" . $searchTerm . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "ssss";
}

if (!empty($filterDept) && $filterDept !== "ALL") {
    $whereClauses[] = "department = ?";
    $params[] = $filterDept;
    $types .= "s";
}

if (!empty($filterSem) && $filterSem !== "ALL") {
    $whereClauses[] = "semester = ?";
    $params[] = $filterSem;
    $types .= "s";
}

$querySql = "SELECT student_id, fullname, enrollment_no, email, mobile, department, semester, role, created_at 
             FROM students 
             WHERE " . implode(" AND ", $whereClauses) . " 
             ORDER BY student_id DESC";

$queryStmt = mysqli_prepare($conn, $querySql);
if (!empty($params)) {
    mysqli_stmt_bind_param($queryStmt, $types, ...$params);
}
mysqli_stmt_execute($queryStmt);
$queryRes = mysqli_stmt_get_result($queryStmt);

$students = [];
while ($row = mysqli_fetch_assoc($queryRes)) {
    $students[] = $row;
}
mysqli_stmt_close($queryStmt);

// Overall Counts for metrics and dropdown options
$allDepts = [
    "Computer Engineering",
    "Information Technology",
    "Computer Science & Engineering",
    "Electronics & Communication",
    "Mechanical Engineering",
    "Civil Engineering"
];

$allSemesters = [
    "Semester 1",
    "Semester 2",
    "Semester 3",
    "Semester 4",
    "Semester 5",
    "Semester 6",
    "Semester 7",
    "Semester 8"
];

$totalStudentCount = 0;
$cRes = mysqli_query($conn, "SELECT COUNT(*) FROM students WHERE role != 'admin'");
if ($cRes) {
    $cRow = mysqli_fetch_row($cRes);
    $totalStudentCount = intval($cRow[0]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management Module - Student Hub</title>
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
        /* =====================================================================
           STUDENT MANAGEMENT MODULE - PROFESSIONAL THEME STYLES
           Preserves exact StudentHub appearance & colors (#1B1F44, orange)
           ===================================================================== */
        .management-container {
            width: 90%;
            max-width: 1200px;
            margin: 20px auto 60px;
        }

        /* Top Action / Status Strip */
        .top-meta-banner {
            background: linear-gradient(135deg, #1B1F44 0%, #2b326d 100%);
            color: white;
            padding: 16px 25px;
            border-radius: 8px 8px 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid orange;
            flex-wrap: wrap;
            gap: 15px;
        }

        .meta-title-box h2 {
            margin: 0;
            font-size: 19px;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .meta-title-box p {
            margin: 4px 0 0;
            font-size: 13px;
            color: #ffd700;
        }

        .top-actions-cluster {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Primary Add Button */
        .btn-add-primary {
            background-color: orange;
            color: #1B1F44;
            border: 2px solid #ffffff;
            padding: 9px 18px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 13.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }

        .btn-add-primary:hover {
            background-color: #ff9800;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }

        /* Notification / Alert Banners */
        .alert-box {
            padding: 14px 20px;
            border-radius: 6px;
            margin: 15px 0 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            animation: fadeIn 0.3s ease;
        }

        .alert-box.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-left: 5px solid #28a745;
        }

        .alert-box.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-left: 5px solid #dc3545;
        }

        .alert-close-btn {
            background: none;
            border: none;
            font-size: 20px;
            font-weight: bold;
            color: inherit;
            cursor: pointer;
            margin-left: 15px;
            line-height: 1;
        }

        /* Search & Filter Toolbar */
        .management-toolbar {
            background: white;
            padding: 16px 20px;
            border: 1px solid #e2e8f0;
            border-top: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
        }

        .search-input-wrapper {
            position: relative;
            flex: 1;
            min-width: 260px;
            max-width: 440px;
        }

        .search-input-wrapper input {
            width: 100%;
            padding: 10px 14px 10px 38px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13.5px;
            outline: none;
            transition: 0.2s;
            box-sizing: border-box;
        }

        .search-input-wrapper input:focus {
            border-color: #1B1F44;
            box-shadow: 0 0 0 3px rgba(27, 31, 68, 0.12);
        }

        .search-icon-symbol {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 15px;
            pointer-events: none;
        }

        .filter-group-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-select-input {
            padding: 9px 12px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            background-color: white;
            color: #1e293b;
            outline: none;
            cursor: pointer;
        }

        .filter-select-input:focus {
            border-color: #1B1F44;
        }

        .btn-reset-filters {
            padding: 9px 14px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #475569;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-reset-filters:hover {
            background-color: #e2e8f0;
            color: #1B1F44;
        }

        .btn-csv-export {
            padding: 9px 14px;
            border-radius: 6px;
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            color: #166534;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-csv-export:hover {
            background-color: #166534;
            color: white;
        }

        /* Table Card Container */
        .records-table-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-top: none;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }

        .students-styled-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            text-align: left;
        }

        .students-styled-table th {
            background-color: #1B1F44;
            color: white;
            padding: 12px 14px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            border: none;
        }

        .students-styled-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
            white-space: nowrap;
        }

        .students-styled-table tbody tr:hover {
            background-color: #f8fafc;
        }

        .student-cell-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar-initial-badge {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1B1F44 0%, #3a4282 100%);
            color: #ffd700;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: bold;
            flex-shrink: 0;
            border: 1px solid orange;
        }

        .student-title-name {
            font-weight: bold;
            color: #1B1F44;
            display: block;
        }

        .student-sub-date {
            font-size: 11.5px;
            color: #64748b;
        }

        /* Badges */
        .pill-badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 12px;
            font-size: 11.5px;
            font-weight: bold;
            white-space: nowrap;
        }

        .pill-ce  { background-color: #e0f2fe; color: #0284c7; }
        .pill-it  { background-color: #fef3c7; color: #b45309; }
        .pill-cs  { background-color: #ede9fe; color: #7c3aed; }
        .pill-ec  { background-color: #ffe4e6; color: #e11d48; }
        .pill-me  { background-color: #f3e8ff; color: #9333ea; }
        .pill-cl  { background-color: #ffedd5; color: #c2410c; }
        .pill-gen { background-color: #f1f5f9; color: #475569; }

        .pill-active {
            background-color: #dcfce7;
            color: #166534;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Action Buttons */
        .action-buttons-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
            justify-content: center;
        }

        .btn-action-view {
            background-color: #1B1F44;
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-action-view:hover {
            background-color: orange;
            color: #1B1F44;
        }

        .btn-action-edit {
            background-color: #0284c7;
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-action-edit:hover {
            background-color: #0369a1;
        }

        .btn-action-delete {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-action-delete:hover {
            background-color: #b02a37;
        }

        /* Table Footer / Summary */
        .table-meta-footer {
            padding: 12px 18px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12.5px;
            color: #64748b;
        }

        /* =====================================================================
           MODAL WINDOWS (ADD, EDIT, DELETE, VIEW DOSSIER)
           ===================================================================== */
        .modal-overlay {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(3px);
            align-items: center;
            justify-content: center;
        }

        .modal-card-box {
            background-color: white;
            border-radius: 10px;
            width: 90%;
            max-width: 550px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: modalFadeIn 0.25s ease-out;
        }

        .modal-header-section {
            background-color: #1B1F44;
            color: white;
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid orange;
        }

        .modal-header-section h3 {
            margin: 0;
            font-size: 16px;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-close-cross {
            background: none;
            border: none;
            color: #ffffff;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
            padding: 0;
            transition: 0.2s;
        }

        .modal-close-cross:hover {
            color: orange;
        }

        .modal-form-body {
            padding: 22px 24px;
            max-height: 75vh;
            overflow-y: auto;
        }

        .modal-grid-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 14px;
        }

        .modal-form-group {
            margin-bottom: 14px;
        }

        .modal-form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            font-weight: bold;
            color: #1B1F44;
        }

        .modal-form-group input,
        .modal-form-group select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13.5px;
            outline: none;
            box-sizing: border-box;
            transition: 0.2s;
        }

        .modal-form-group input:focus,
        .modal-form-group select:focus {
            border-color: #1B1F44;
            box-shadow: 0 0 0 3px rgba(27, 31, 68, 0.1);
        }

        .modal-form-group small {
            display: block;
            margin-top: 4px;
            font-size: 11.5px;
            color: #64748b;
        }

        .modal-footer-section {
            padding: 14px 24px;
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-modal-cancel {
            padding: 9px 16px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: white;
            color: #475569;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-modal-cancel:hover {
            background-color: #f1f5f9;
        }

        .btn-modal-submit-add {
            padding: 9px 18px;
            border-radius: 6px;
            border: none;
            background-color: #1B1F44;
            color: white;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-modal-submit-add:hover {
            background-color: orange;
            color: #1B1F44;
        }

        .btn-modal-submit-edit {
            padding: 9px 18px;
            border-radius: 6px;
            border: none;
            background-color: #0284c7;
            color: white;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-modal-submit-edit:hover {
            background-color: #0369a1;
        }

        .btn-modal-submit-delete {
            padding: 9px 18px;
            border-radius: 6px;
            border: none;
            background-color: #dc3545;
            color: white;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-modal-submit-delete:hover {
            background-color: #b02a37;
        }

        /* Dossier Detail Rows */
        .dossier-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 13.5px;
        }

        .dossier-label {
            color: #64748b;
            font-weight: 500;
        }

        .dossier-val {
            color: #1B1F44;
            font-weight: bold;
            text-align: right;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-15px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
    </style>
</head>
<body>

    <!-- HEADER IFRAME -->
    <iframe
        src="header.html"
        class="header-frame">
    </iframe>

    <h1 class="page-title">STUDENT MANAGEMENT MODULE</h1>

    <!-- NAVIGATION (ROLE & SESSION ADAPTIVE) -->
    <nav>
        <?php if ($isLoggedIn): ?>
            <?php if ($userRole === 'admin'): ?>
                <a href="admin_dashboard.php">ADMIN PANEL</a>
            <?php endif; ?>
            <a href="manage_students.php" class="active" style="background-color: orange; color: #1B1F44; font-weight: bold;">MANAGE STUDENTS</a>
            <a href="dashboard.php">DASHBOARD</a>
            <a href="view_records.php">ALL RECORDS</a>
            <a href="event.html">EVENTS</a>
            <a href="course.html">COURSES</a>
            <a href="contact.html">CONTACT</a>
            <a href="logout.php" style="background-color: #dc3545; color: white;" title="End session and log out">LOGOUT 🚪</a>
        <?php else: ?>
            <a href="dashboard.php">DASHBOARD</a>
            <a href="manage_students.php" class="active" style="background-color: orange; color: #1B1F44; font-weight: bold;">MANAGE STUDENTS</a>
            <a href="event.html">EVENTS</a>
            <a href="course.html">COURSES</a>
            <a href="register.php">REGISTER</a>
            <a href="login.php">LOGIN</a>
            <a href="contact.html">CONTACT</a>
        <?php endif; ?>
    </nav>

    <!-- MAIN CONTAINER -->
    <div class="management-container">

        <!-- SUCCESS / FAILURE MESSAGES (MYSQLi FEEDBACK) -->
        <?php if (!empty($message)): ?>
            <div class="alert-box <?= htmlspecialchars($messageType) ?>" id="feedbackAlert">
                <div>
                    <?php if ($messageType === 'success'): ?>
                        <strong>✓ Success:</strong> <?= htmlspecialchars($message) ?>
                    <?php else: ?>
                        <strong>⚠️ Notice:</strong> <?= htmlspecialchars($message) ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="alert-close-btn" onclick="document.getElementById('feedbackAlert').style.display='none'">&times;</button>
            </div>
        <?php endif; ?>

        <!-- TOP STATUS / META STRIP -->
        <div class="top-meta-banner">
            <div class="meta-title-box">
                <h2>
                    👥 Student Records Directory
                    <span class="pill-badge pill-active" style="font-size: 12px; font-weight: normal; margin-left: 8px;">
                        ● MySQLi Operations Active
                    </span>
                </h2>
                <p>
                    Manage, search, filter, edit, and maintain student database profiles securely with MySQLi Prepared Statements.
                </p>
            </div>
            <div class="top-actions-cluster">
                <button type="button" class="btn-add-primary" onclick="openAddModal()">
                    ➕ Add New Student
                </button>
            </div>
        </div>

        <!-- SEARCH, FILTER & EXPORT TOOLBAR -->
        <div class="management-toolbar">
            <!-- Client-side + Server-side Search -->
            <div class="search-input-wrapper">
                <span class="search-icon-symbol">🔍</span>
                <input type="text" id="liveSearchInput" placeholder="Search by name, enrollment ID, email, or mobile..." 
                       value="<?= htmlspecialchars($searchTerm) ?>" onkeyup="filterTableRealtime()">
            </div>

            <!-- Filters -->
            <div class="filter-group-wrapper">
                <!-- Department Filter -->
                <select class="filter-select-input" id="deptFilterSelect" onchange="filterTableRealtime()">
                    <option value="ALL">All Departments (<?= intval($totalStudentCount) ?>)</option>
                    <?php foreach ($allDepts as $dept): ?>
                        <option value="<?= htmlspecialchars($dept) ?>" <?= ($filterDept === $dept) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Semester Filter -->
                <select class="filter-select-input" id="semFilterSelect" onchange="filterTableRealtime()">
                    <option value="ALL">All Semesters</option>
                    <?php foreach ($allSemesters as $sem): ?>
                        <option value="<?= htmlspecialchars($sem) ?>" <?= ($filterSem === $sem) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sem) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Reset Filters Button -->
                <button type="button" class="btn-reset-filters" onclick="resetAllFilters()" title="Reset search & filters">
                    🔄 Reset
                </button>

                <!-- Export CSV Button -->
                <button type="button" class="btn-csv-export" onclick="exportRecordsToCSV()" title="Export current student list to CSV">
                    📥 Export CSV
                </button>
            </div>
        </div>

        <!-- RECORDS TABLE CARD -->
        <div class="records-table-card">
            <table class="students-styled-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#ID</th>
                        <th>Student Name</th>
                        <th>Enrollment No</th>
                        <th>Email Address</th>
                        <th>Mobile</th>
                        <th>Department</th>
                        <th>Semester</th>
                        <th>Status</th>
                        <th style="text-align: center; width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="studentsTableBody">
                    <?php if (!empty($students)): ?>
                        <?php foreach ($students as $st): 
                            $parts = explode(' ', trim($st['fullname']));
                            $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                            
                            $dLower = strtolower($st['department']);
                            $deptClass = 'pill-gen';
                            if (strpos($dLower, 'computer engine') !== false) $deptClass = 'pill-ce';
                            elseif (strpos($dLower, 'information') !== false) $deptClass = 'pill-it';
                            elseif (strpos($dLower, 'science') !== false) $deptClass = 'pill-cs';
                            elseif (strpos($dLower, 'electron') !== false) $deptClass = 'pill-ec';
                            elseif (strpos($dLower, 'mech') !== false) $deptClass = 'pill-me';
                            elseif (strpos($dLower, 'civil') !== false) $deptClass = 'pill-cl';
                        ?>
                            <tr class="student-record-row"
                                data-id="<?= htmlspecialchars($st['student_id']) ?>"
                                data-name="<?= htmlspecialchars(strtolower($st['fullname'])) ?>"
                                data-rawname="<?= htmlspecialchars($st['fullname']) ?>"
                                data-enroll="<?= htmlspecialchars(strtolower($st['enrollment_no'])) ?>"
                                data-rawenroll="<?= htmlspecialchars($st['enrollment_no']) ?>"
                                data-email="<?= htmlspecialchars(strtolower($st['email'])) ?>"
                                data-rawemail="<?= htmlspecialchars($st['email']) ?>"
                                data-mobile="<?= htmlspecialchars($st['mobile']) ?>"
                                data-dept="<?= htmlspecialchars($st['department']) ?>"
                                data-semester="<?= htmlspecialchars($st['semester']) ?>"
                                data-created="<?= htmlspecialchars($st['created_at']) ?>">
                                <td><b>#<?= htmlspecialchars($st['student_id']) ?></b></td>
                                <td>
                                    <div class="student-cell-profile">
                                        <div class="avatar-initial-badge"><?= htmlspecialchars($initials) ?></div>
                                        <div>
                                            <span class="student-title-name"><?= htmlspecialchars($st['fullname']) ?></span>
                                            <span class="student-sub-date">Registered <?= date('M d, Y', strtotime($st['created_at'])) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><code><?= htmlspecialchars($st['enrollment_no']) ?></code></td>
                                <td><?= htmlspecialchars($st['email']) ?></td>
                                <td><?= htmlspecialchars($st['mobile']) ?></td>
                                <td>
                                    <span class="pill-badge <?= $deptClass ?>">
                                        <?= htmlspecialchars($st['department']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($st['semester']) ?></td>
                                <td>
                                    <span class="pill-badge pill-active">
                                        <span style="font-size: 8px;">●</span> Active
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <div class="action-buttons-wrap">
                                        <button type="button" class="btn-action-view" onclick="openViewModal(this)" title="View Student Dossier">
                                            👁️ View
                                        </button>
                                        <button type="button" class="btn-action-edit" onclick="openEditModal(this)" title="Edit Student Record">
                                            ✏️ Edit
                                        </button>
                                        <button type="button" class="btn-action-delete" onclick="openDeleteModal(this)" title="Delete Student Record">
                                            🗑️ Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #64748b; font-size: 14px;">
                                No student records found matching the specified search or filter criteria.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Realtime No-Match Fallback -->
            <div id="noLiveMatches" style="display: none; text-align: center; padding: 35px; color: #64748b; font-size: 14px;">
                🔍 No student records match your live search / filter criteria. 
                <button type="button" onclick="resetAllFilters()" style="background: none; border: none; color: #0284c7; text-decoration: underline; cursor: pointer; font-weight: bold;">Clear filters</button>
            </div>

            <!-- Table Footer Status -->
            <div class="table-meta-footer">
                <div>
                    Showing <b id="visibleCountDisplay"><?= count($students) ?></b> of <b id="totalCountDisplay"><?= count($students) ?></b> student records
                </div>
                <div>
                    Managed via <b>MySQLi Prepared Statements</b> &bull; Database: <code>studenthub</code>
                </div>
            </div>
        </div>

    </div>

    <!-- =========================================================================
         1. ADD STUDENT MODAL
         ========================================================================= -->
    <div class="modal-overlay" id="addStudentModal" onclick="handleModalBackdrop(event, 'addStudentModal')">
        <div class="modal-card-box">
            <div class="modal-header-section">
                <h3>➕ Add New Student Record</h3>
                <button type="button" class="modal-close-cross" onclick="closeModal('addStudentModal')">&times;</button>
            </div>
            <form method="POST" action="manage_students.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-form-body">
                    <div class="modal-form-group">
                        <label for="add_fullname">Full Name <span style="color: red;">*</span></label>
                        <input type="text" id="add_fullname" name="fullname" placeholder="e.g. Yashvi Vekariya" required>
                    </div>

                    <div class="modal-grid-row">
                        <div class="modal-form-group">
                            <label for="add_enrollment">Enrollment No <span style="color: red;">*</span></label>
                            <input type="text" id="add_enrollment" name="enrollment_no" placeholder="e.g. 25DCE122" required>
                        </div>
                        <div class="modal-form-group">
                            <label for="add_mobile">Mobile Number <span style="color: red;">*</span></label>
                            <input type="text" id="add_mobile" name="mobile" placeholder="10-digit number" pattern="[0-9]{10}" maxlength="10" required>
                        </div>
                    </div>

                    <div class="modal-form-group">
                        <label for="add_email">Email Address <span style="color: red;">*</span></label>
                        <input type="email" id="add_email" name="email" placeholder="student@charusat.edu.in" required>
                    </div>

                    <div class="modal-grid-row">
                        <div class="modal-form-group">
                            <label for="add_department">Department <span style="color: red;">*</span></label>
                            <select id="add_department" name="department" required>
                                <?php foreach ($allDepts as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label for="add_semester">Semester <span style="color: red;">*</span></label>
                            <select id="add_semester" name="semester" required>
                                <?php foreach ($allSemesters as $sem): ?>
                                    <option value="<?= htmlspecialchars($sem) ?>" <?= ($sem === 'Semester 3') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sem) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="modal-form-group">
                        <label for="add_password">Account Password <span style="color: red;">*</span></label>
                        <input type="password" id="add_password" name="password" placeholder="At least 6 characters" minlength="6" required>
                        <small>Password will be securely hashed with bcrypt (<code>password_hash</code>) before MySQL storage.</small>
                    </div>
                </div>
                <div class="modal-footer-section">
                    <button type="button" class="btn-modal-cancel" onclick="closeModal('addStudentModal')">Cancel</button>
                    <button type="submit" class="btn-modal-submit-add">💾 Save Student Record</button>
                </div>
            </form>
        </div>
    </div>

    <!-- =========================================================================
         2. EDIT STUDENT MODAL
         ========================================================================= -->
    <div class="modal-overlay" id="editStudentModal" onclick="handleModalBackdrop(event, 'editStudentModal')">
        <div class="modal-card-box">
            <div class="modal-header-section" style="background-color: #0284c7;">
                <h3>✏️ Edit Student Record</h3>
                <button type="button" class="modal-close-cross" onclick="closeModal('editStudentModal')">&times;</button>
            </div>
            <form method="POST" action="manage_students.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_student_id" name="student_id" value="">
                
                <div class="modal-form-body">
                    <div class="modal-form-group">
                        <label for="edit_fullname">Full Name <span style="color: red;">*</span></label>
                        <input type="text" id="edit_fullname" name="fullname" required>
                    </div>

                    <div class="modal-grid-row">
                        <div class="modal-form-group">
                            <label for="edit_enrollment">Enrollment No <span style="color: red;">*</span></label>
                            <input type="text" id="edit_enrollment" name="enrollment_no" required>
                        </div>
                        <div class="modal-form-group">
                            <label for="edit_mobile">Mobile Number <span style="color: red;">*</span></label>
                            <input type="text" id="edit_mobile" name="mobile" pattern="[0-9]{10}" maxlength="10" required>
                        </div>
                    </div>

                    <div class="modal-form-group">
                        <label for="edit_email">Email Address <span style="color: red;">*</span></label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>

                    <div class="modal-grid-row">
                        <div class="modal-form-group">
                            <label for="edit_department">Department <span style="color: red;">*</span></label>
                            <select id="edit_department" name="department" required>
                                <?php foreach ($allDepts as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label for="edit_semester">Semester <span style="color: red;">*</span></label>
                            <select id="edit_semester" name="semester" required>
                                <?php foreach ($allSemesters as $sem): ?>
                                    <option value="<?= htmlspecialchars($sem) ?>"><?= htmlspecialchars($sem) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="modal-form-group">
                        <label for="edit_new_password">New Password (Optional)</label>
                        <input type="password" id="edit_new_password" name="new_password" placeholder="Leave blank to keep existing password" minlength="6">
                        <small>Leave this field empty if you do not wish to change the student's password.</small>
                    </div>
                </div>
                <div class="modal-footer-section">
                    <button type="button" class="btn-modal-cancel" onclick="closeModal('editStudentModal')">Cancel</button>
                    <button type="submit" class="btn-modal-submit-edit">💾 Update Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- =========================================================================
         3. DELETE CONFIRMATION MODAL
         ========================================================================= -->
    <div class="modal-overlay" id="deleteStudentModal" onclick="handleModalBackdrop(event, 'deleteStudentModal')">
        <div class="modal-card-box" style="max-width: 460px;">
            <div class="modal-header-section" style="background-color: #dc3545;">
                <h3>🗑️ Confirm Student Record Deletion</h3>
                <button type="button" class="modal-close-cross" onclick="closeModal('deleteStudentModal')">&times;</button>
            </div>
            <form method="POST" action="manage_students.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" id="delete_student_id" name="student_id" value="">

                <div class="modal-form-body" style="text-align: center; padding: 25px 20px;">
                    <div style="font-size: 42px; margin-bottom: 12px;">⚠️</div>
                    <h3 style="color: #1B1F44; margin: 0 0 8px; font-size: 18px;">Are you sure you want to delete this record?</h3>
                    <p style="color: #475569; font-size: 14px; margin: 0 0 15px;">
                        Student: <strong id="delete_display_name">--</strong><br>
                        Enrollment: <code id="delete_display_enroll">--</code>
                    </p>
                    <div style="background-color: #fff3cd; color: #856404; padding: 10px 14px; border-radius: 6px; font-size: 12.5px; border: 1px solid #ffeeba; text-align: left;">
                        Notice: Deleting this student will also remove any associated event registrations via foreign key CASCADE. This action cannot be reversed.
                    </div>
                </div>
                <div class="modal-footer-section" style="justify-content: center; gap: 15px;">
                    <button type="button" class="btn-modal-cancel" onclick="closeModal('deleteStudentModal')">No, Cancel</button>
                    <button type="submit" class="btn-modal-submit-delete">🗑️ Yes, Delete Record</button>
                </div>
            </form>
        </div>
    </div>

    <!-- =========================================================================
         4. VIEW STUDENT DOSSIER MODAL
         ========================================================================= -->
    <div class="modal-overlay" id="viewStudentModal" onclick="handleModalBackdrop(event, 'viewStudentModal')">
        <div class="modal-card-box" style="max-width: 500px;">
            <div class="modal-header-section">
                <h3>👤 Student Profile Dossier</h3>
                <button type="button" class="modal-close-cross" onclick="closeModal('viewStudentModal')">&times;</button>
            </div>
            <div class="modal-form-body">
                <div style="text-align: center; margin-bottom: 20px;">
                    <div class="avatar-initial-badge" id="view_avatar" style="width: 56px; height: 56px; font-size: 20px; margin: 0 auto 10px;">--</div>
                    <h3 id="view_fullname" style="margin: 0; color: #1B1F44; font-size: 19px;">Student Name</h3>
                    <span id="view_enroll_badge" class="pill-badge pill-ce" style="margin-top: 5px;">25DCE000</span>
                </div>

                <div class="dossier-row">
                    <span class="dossier-label">Student ID:</span>
                    <span class="dossier-val" id="view_student_id">#0</span>
                </div>
                <div class="dossier-row">
                    <span class="dossier-label">Email Address:</span>
                    <span class="dossier-val" id="view_email">email@charusat.edu.in</span>
                </div>
                <div class="dossier-row">
                    <span class="dossier-label">Mobile Number:</span>
                    <span class="dossier-val" id="view_mobile">0000000000</span>
                </div>
                <div class="dossier-row">
                    <span class="dossier-label">Department:</span>
                    <span class="dossier-val" id="view_department">Computer Engineering</span>
                </div>
                <div class="dossier-row">
                    <span class="dossier-label">Semester:</span>
                    <span class="dossier-val" id="view_semester">Semester 3</span>
                </div>
                <div class="dossier-row">
                    <span class="dossier-label">Registration Date:</span>
                    <span class="dossier-val" id="view_created">YYYY-MM-DD</span>
                </div>
                <div class="dossier-row" style="border-bottom: none;">
                    <span class="dossier-label">Account Status:</span>
                    <span class="dossier-val" style="color: #166534;">● Active Student</span>
                </div>
            </div>
            <div class="modal-footer-section">
                <button type="button" class="btn-csv-export" onclick="emailStudent()">
                    ✉️ Send Email
                </button>
                <button type="button" class="btn-modal-cancel" onclick="closeModal('viewStudentModal')">
                    Close Window
                </button>
            </div>
        </div>
    </div>

    <!-- HOME BUTTON AT BOTTOM RIGHT -->
    <a href="../index.php" class="home-btn">🏠 Home</a>

    <!-- FOOTER -->
    <footer>
        &copy; 2026 STUDENT HUB • CHARUSAT University • All Rights Reserved.
    </footer>

    <!-- INTERACTIVE SCRIPT (MODAL CONTROLS, SEARCH, FILTER, CSV) -->
    <script>
        var currentViewEmail = "";

        // Modal Helpers
        function openModal(id) {
            document.getElementById(id).style.display = "flex";
        }

        function closeModal(id) {
            document.getElementById(id).style.display = "none";
        }

        function handleModalBackdrop(event, id) {
            if (event.target.id === id) {
                closeModal(id);
            }
        }

        window.addEventListener("keydown", function(e) {
            if (e.key === "Escape") {
                closeModal("addStudentModal");
                closeModal("editStudentModal");
                closeModal("deleteStudentModal");
                closeModal("viewStudentModal");
            }
        });

        // 1. Add Student Modal
        function openAddModal() {
            document.getElementById("add_fullname").value = "";
            document.getElementById("add_enrollment").value = "";
            document.getElementById("add_email").value = "";
            document.getElementById("add_mobile").value = "";
            document.getElementById("add_password").value = "";
            openModal("addStudentModal");
        }

        // 2. View Student Modal
        function openViewModal(btn) {
            var row = btn.closest(".student-record-row");
            if (!row) return;

            var id       = row.getAttribute("data-id");
            var name     = row.getAttribute("data-rawname");
            var enroll   = row.getAttribute("data-rawenroll");
            var email    = row.getAttribute("data-rawemail");
            var mobile   = row.getAttribute("data-mobile");
            var dept     = row.getAttribute("data-dept");
            var semester = row.getAttribute("data-semester");
            var created  = row.getAttribute("data-created");

            currentViewEmail = email;

            var parts = name.trim().split(" ");
            var inits = parts[0].charAt(0) + (parts.length > 1 ? parts[1].charAt(0) : "");

            document.getElementById("view_avatar").textContent = inits.toUpperCase();
            document.getElementById("view_fullname").textContent = name;
            document.getElementById("view_enroll_badge").textContent = enroll;
            document.getElementById("view_student_id").textContent = "#" + id;
            document.getElementById("view_email").textContent = email;
            document.getElementById("view_mobile").textContent = mobile;
            document.getElementById("view_department").textContent = dept;
            document.getElementById("view_semester").textContent = semester;
            document.getElementById("view_created").textContent = created;

            openModal("viewStudentModal");
        }

        function emailStudent() {
            if (currentViewEmail) {
                window.location.href = "mailto:" + currentViewEmail;
            }
        }

        // 3. Edit Student Modal
        function openEditModal(btn) {
            var row = btn.closest(".student-record-row");
            if (!row) return;

            var id       = row.getAttribute("data-id");
            var name     = row.getAttribute("data-rawname");
            var enroll   = row.getAttribute("data-rawenroll");
            var email    = row.getAttribute("data-rawemail");
            var mobile   = row.getAttribute("data-mobile");
            var dept     = row.getAttribute("data-dept");
            var semester = row.getAttribute("data-semester");

            document.getElementById("edit_student_id").value = id;
            document.getElementById("edit_fullname").value   = name;
            document.getElementById("edit_enrollment").value = enroll;
            document.getElementById("edit_email").value      = email;
            document.getElementById("edit_mobile").value     = mobile;
            document.getElementById("edit_department").value = dept;
            document.getElementById("edit_semester").value   = semester;
            document.getElementById("edit_new_password").value = "";

            openModal("editStudentModal");
        }

        // 4. Delete Student Modal
        function openDeleteModal(btn) {
            var row = btn.closest(".student-record-row");
            if (!row) return;

            var id     = row.getAttribute("data-id");
            var name   = row.getAttribute("data-rawname");
            var enroll = row.getAttribute("data-rawenroll");

            document.getElementById("delete_student_id").value = id;
            document.getElementById("delete_display_name").textContent = name;
            document.getElementById("delete_display_enroll").textContent = enroll;

            openModal("deleteStudentModal");
        }

        // 5. Instant Realtime Search & Filter
        function filterTableRealtime() {
            var query = document.getElementById("liveSearchInput").value.toLowerCase().trim();
            var dept  = document.getElementById("deptFilterSelect").value;
            var sem   = document.getElementById("semFilterSelect").value;
            var rows  = document.querySelectorAll(".student-record-row");
            var visibleCount = 0;

            rows.forEach(function(row) {
                var rowName   = row.getAttribute("data-name");
                var rowEnroll = row.getAttribute("data-enroll");
                var rowEmail  = row.getAttribute("data-email");
                var rowMobile = row.getAttribute("data-mobile");
                var rowDept   = row.getAttribute("data-dept");
                var rowSem    = row.getAttribute("data-semester");

                var matchesQuery = (
                    query === "" || 
                    rowName.indexOf(query) !== -1 || 
                    rowEnroll.indexOf(query) !== -1 || 
                    rowEmail.indexOf(query) !== -1 || 
                    rowMobile.indexOf(query) !== -1
                );

                var matchesDept = (dept === "ALL" || rowDept === dept);
                var matchesSem  = (sem === "ALL" || rowSem === sem);

                if (matchesQuery && matchesDept && matchesSem) {
                    row.style.display = "";
                    visibleCount++;
                } else {
                    row.style.display = "none";
                }
            });

            document.getElementById("visibleCountDisplay").textContent = visibleCount;
            var fallback = document.getElementById("noLiveMatches");
            if (visibleCount === 0) {
                fallback.style.display = "block";
            } else {
                fallback.style.display = "none";
            }
        }

        function resetAllFilters() {
            document.getElementById("liveSearchInput").value = "";
            document.getElementById("deptFilterSelect").value = "ALL";
            document.getElementById("semFilterSelect").value  = "ALL";
            filterTableRealtime();
        }

        // 6. CSV Export Functionality
        function exportRecordsToCSV() {
            var rows = document.querySelectorAll(".student-record-row");
            if (rows.length === 0) {
                alert("No student records available to export.");
                return;
            }

            var csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Student ID,Full Name,Enrollment No,Email,Mobile,Department,Semester,Registered Date\n";

            var exportedCount = 0;
            rows.forEach(function(row) {
                if (row.style.display !== "none") {
                    var id      = row.getAttribute("data-id");
                    var name    = '"' + row.getAttribute("data-rawname").replace(/"/g, '""') + '"';
                    var enroll  = '"' + row.getAttribute("data-rawenroll") + '"';
                    var email   = '"' + row.getAttribute("data-rawemail") + '"';
                    var mobile  = '"' + row.getAttribute("data-mobile") + '"';
                    var dept    = '"' + row.getAttribute("data-dept").replace(/"/g, '""') + '"';
                    var sem     = '"' + row.getAttribute("data-semester") + '"';
                    var created = '"' + row.getAttribute("data-created") + '"';

                    csvContent += [id, name, enroll, email, mobile, dept, sem, created].join(",") + "\n";
                    exportedCount++;
                }
            });

            if (exportedCount === 0) {
                alert("No records match your active search filters to export.");
                return;
            }

            var encodedUri = encodeURI(csvContent);
            var link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "studenthub_roster_" + new Date().toISOString().slice(0, 10) + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
    <script src="../js/script.js"></script>
</body>
</html>
