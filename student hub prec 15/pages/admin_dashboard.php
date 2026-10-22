<?php
// =============================================================================
// Practical 10: Role-Based Access - Administrator Control Panel
// Problem Definition:
// Implement role-based redirection and restricted dashboard access. Only users
// with the 'admin' role can access this administration portal.
// Interactive, streamlined administrative UI with single dedicated controls
// for each functionality (no duplicate buttons or redundant links).
// =============================================================================

require_once __DIR__ . "/auth.php";

// Restrict access: Only users with 'admin' role are permitted
require_role('admin');

$adminName    = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 'System Administrator';
$adminEnroll  = isset($_SESSION['enrollment_no']) ? $_SESSION['enrollment_no'] : 'ADMIN001';
$adminEmail   = isset($_SESSION['email']) ? $_SESSION['email'] : 'admin@charusat.edu.in';

$statusMsg  = "";
$statusType = "";
$activeTab  = isset($_POST["active_tab"]) ? $_POST["active_tab"] : (isset($_GET["tab"]) ? $_GET["tab"] : "tab-students");

// Handle Student Management Actions (Add, Edit, Delete) via MySQLi Prepared Statements
if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["student_action"])) {
    $action = $_POST["student_action"];
    
    // Add Student
    if ($action === "add") {
        $fullname   = isset($_POST["fullname"]) ? trim($_POST["fullname"]) : "";
        $enrollment = isset($_POST["enrollment_no"]) ? trim(strtoupper($_POST["enrollment_no"])) : "";
        $email      = isset($_POST["email"]) ? trim(strtolower($_POST["email"])) : "";
        $mobile     = isset($_POST["mobile"]) ? trim($_POST["mobile"]) : "";
        $department = isset($_POST["department"]) ? trim($_POST["department"]) : "Computer Engineering";
        $semester   = isset($_POST["semester"]) ? trim($_POST["semester"]) : "Semester 3";
        $password   = isset($_POST["password"]) ? $_POST["password"] : "";

        if (empty($fullname) || empty($enrollment) || empty($email) || empty($mobile) || empty($password)) {
            $statusMsg  = "Failed to add student: All fields are required.";
            $statusType = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $statusMsg  = "Failed to add student: Invalid email address.";
            $statusType = "error";
        } elseif (!preg_match("/^[0-9]{10}$/", $mobile)) {
            $statusMsg  = "Failed to add student: Mobile must be 10 digits.";
            $statusType = "error";
        } elseif (strlen($password) < 6) {
            $statusMsg  = "Failed to add student: Password must be at least 6 characters.";
            $statusType = "error";
        } else {
            $checkStmt = mysqli_prepare($conn, "SELECT student_id, enrollment_no, email FROM students WHERE enrollment_no = ? OR email = ? LIMIT 1");
            mysqli_stmt_bind_param($checkStmt, "ss", $enrollment, $email);
            mysqli_stmt_execute($checkStmt);
            $cRes = mysqli_stmt_get_result($checkStmt);
            $dup = mysqli_fetch_assoc($cRes);
            mysqli_stmt_close($checkStmt);

            if ($dup) {
                $statusMsg  = (strtoupper($dup['enrollment_no']) === $enrollment) 
                    ? "Failed to add student: Enrollment Number '$enrollment' already exists." 
                    : "Failed to add student: Email '$email' is already registered.";
                $statusType = "error";
            } else {
                $pHash = password_hash($password, PASSWORD_DEFAULT);
                $insStmt = mysqli_prepare($conn, "INSERT INTO students (fullname, enrollment_no, email, mobile, department, semester, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, 'student')");
                mysqli_stmt_bind_param($insStmt, "sssssss", $fullname, $enrollment, $email, $mobile, $department, $semester, $pHash);
                if (mysqli_stmt_execute($insStmt)) {
                    $newId = mysqli_insert_id($conn);
                    $statusMsg  = "Success! Student record #$newId ('$fullname' - $enrollment) was added successfully via MySQLi.";
                    $statusType = "success";
                } else {
                    $statusMsg  = "Database Error: " . mysqli_error($conn);
                    $statusType = "error";
                }
                mysqli_stmt_close($insStmt);
            }
        }
    }
    // Edit Student
    elseif ($action === "edit") {
        $studentId  = isset($_POST["student_id"]) ? intval($_POST["student_id"]) : 0;
        $fullname   = isset($_POST["fullname"]) ? trim($_POST["fullname"]) : "";
        $enrollment = isset($_POST["enrollment_no"]) ? trim(strtoupper($_POST["enrollment_no"])) : "";
        $email      = isset($_POST["email"]) ? trim(strtolower($_POST["email"])) : "";
        $mobile     = isset($_POST["mobile"]) ? trim($_POST["mobile"]) : "";
        $department = isset($_POST["department"]) ? trim($_POST["department"]) : "";
        $semester   = isset($_POST["semester"]) ? trim($_POST["semester"]) : "";
        $newPass    = isset($_POST["new_password"]) ? trim($_POST["new_password"]) : "";

        if ($studentId <= 0 || empty($fullname) || empty($enrollment) || empty($email) || empty($mobile) || empty($department) || empty($semester)) {
            $statusMsg  = "Failed to update: All fields are required.";
            $statusType = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $statusMsg  = "Failed to update: Invalid email format.";
            $statusType = "error";
        } elseif (!preg_match("/^[0-9]{10}$/", $mobile)) {
            $statusMsg  = "Failed to update: Mobile must be 10 digits.";
            $statusType = "error";
        } else {
            $dupStmt = mysqli_prepare($conn, "SELECT student_id, enrollment_no, email FROM students WHERE (enrollment_no = ? OR email = ?) AND student_id != ? LIMIT 1");
            mysqli_stmt_bind_param($dupStmt, "ssi", $enrollment, $email, $studentId);
            mysqli_stmt_execute($dupStmt);
            $dupRes = mysqli_stmt_get_result($dupStmt);
            $dup = mysqli_fetch_assoc($dupRes);
            mysqli_stmt_close($dupStmt);

            if ($dup) {
                $statusMsg  = (strtoupper($dup['enrollment_no']) === $enrollment)
                    ? "Failed to update: Enrollment Number '$enrollment' is already used by student #{$dup['student_id']}."
                    : "Failed to update: Email '$email' is already used by student #{$dup['student_id']}.";
                $statusType = "error";
            } else {
                if (!empty($newPass)) {
                    if (strlen($newPass) < 6) {
                        $statusMsg  = "Failed to update: New password must be at least 6 characters.";
                        $statusType = "error";
                    } else {
                        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                        $upStmt = mysqli_prepare($conn, "UPDATE students SET fullname = ?, enrollment_no = ?, email = ?, mobile = ?, department = ?, semester = ?, password = ? WHERE student_id = ? AND role != 'admin'");
                        mysqli_stmt_bind_param($upStmt, "sssssssi", $fullname, $enrollment, $email, $mobile, $department, $semester, $newHash, $studentId);
                    }
                } else {
                    $upStmt = mysqli_prepare($conn, "UPDATE students SET fullname = ?, enrollment_no = ?, email = ?, mobile = ?, department = ?, semester = ? WHERE student_id = ? AND role != 'admin'");
                    mysqli_stmt_bind_param($upStmt, "ssssssi", $fullname, $enrollment, $email, $mobile, $department, $semester, $studentId);
                }

                if (!empty($upStmt)) {
                    if (mysqli_stmt_execute($upStmt)) {
                        $statusMsg  = "Success! Student record #$studentId ('$fullname') updated successfully via MySQLi.";
                        $statusType = "success";
                    } else {
                        $statusMsg  = "Database Error: " . mysqli_error($conn);
                        $statusType = "error";
                    }
                    mysqli_stmt_close($upStmt);
                }
            }
        }
    }
    // Delete Student
    elseif ($action === "delete") {
        $studentId = isset($_POST["student_id"]) ? intval($_POST["student_id"]) : 0;
        if ($studentId > 0) {
            $fStmt = mysqli_prepare($conn, "SELECT student_id, fullname, enrollment_no, role FROM students WHERE student_id = ? LIMIT 1");
            mysqli_stmt_bind_param($fStmt, "i", $studentId);
            mysqli_stmt_execute($fStmt);
            $target = mysqli_fetch_assoc(mysqli_stmt_get_result($fStmt));
            mysqli_stmt_close($fStmt);

            if (!$target) {
                $statusMsg  = "Failed to delete: Record #$studentId not found.";
                $statusType = "error";
            } elseif ($target['role'] === 'admin') {
                $statusMsg  = "Action denied: Protected administrator account cannot be deleted.";
                $statusType = "error";
            } else {
                $dStmt = mysqli_prepare($conn, "DELETE FROM students WHERE student_id = ? AND role != 'admin'");
                mysqli_stmt_bind_param($dStmt, "i", $studentId);
                if (mysqli_stmt_execute($dStmt) && mysqli_stmt_affected_rows($dStmt) > 0) {
                    $statusMsg  = "Success! Student record #$studentId ('" . htmlspecialchars($target['fullname']) . "') deleted successfully via MySQLi.";
                    $statusType = "success";
                } else {
                    $statusMsg  = "Database Error: Could not delete student (" . mysqli_error($conn) . ").";
                    $statusType = "error";
                }
                mysqli_stmt_close($dStmt);
            }
        }
    }
}

// Handle Event Management Actions (Add, Edit, Delete) with Poster Upload & File Validation
if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["event_action"])) {
    $activeTab = "tab-events";
    $evAction  = $_POST["event_action"];

    // Poster upload handler with file size & type validation
    $validateAndUploadPoster = function($fileKey, &$uploadErr) {
        $uploadErr = "";
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
            return null; // No file uploaded (optional)
        }

        $file = $_FILES[$fileKey];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadErr = "Upload error occurred (PHP Code: " . $file['error'] . ").";
            return false;
        }

        // 1. File size validation: Max 2 MB (2,097,152 bytes)
        $maxSizeBytes = 2 * 1024 * 1024;
        if ($file['size'] > $maxSizeBytes) {
            $mbSize = round($file['size'] / (1024 * 1024), 2);
            $uploadErr = "File size (" . $mbSize . " MB) exceeds the maximum allowed limit of 2 MB.";
            return false;
        }

        // 2. File extension validation
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $origName = $file['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) {
            $uploadErr = "Invalid file type (." . htmlspecialchars($ext) . "). Only JPG, JPEG, PNG, WEBP, and GIF images are allowed.";
            return false;
        }

        // 3. MIME type validation
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $detectedMime = "";
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        } elseif (function_exists('mime_content_type')) {
            $detectedMime = mime_content_type($file['tmp_name']);
        }

        if (!empty($detectedMime) && !in_array($detectedMime, $allowedMimes)) {
            $uploadErr = "Invalid MIME type (" . htmlspecialchars($detectedMime) . "). File is not an allowed image.";
            return false;
        }

        // 4. Verify valid image content using getimagesize
        $imgInfo = @getimagesize($file['tmp_name']);
        if ($imgInfo === false) {
            $uploadErr = "The uploaded file is not a valid or readable image.";
            return false;
        }

        // 5. Ensure upload directory exists
        $uploadDir = __DIR__ . "/../uploads/events/";
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        // 6. Generate collision-free unique filename
        $newFilename = "event_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
        $destination = $uploadDir . $newFilename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $uploadErr = "Failed to move uploaded poster to server storage directory.";
            return false;
        }

        return "uploads/events/" . $newFilename;
    };

    // A. ADD EVENT
    if ($evAction === "add") {
        $tag         = isset($_POST["tag"]) ? trim($_POST["tag"]) : "Technical Fest";
        $title       = isset($_POST["title"]) ? trim($_POST["title"]) : "";
        $eventDate   = isset($_POST["event_date"]) ? trim($_POST["event_date"]) : "";
        $venue       = isset($_POST["venue"]) ? trim($_POST["venue"]) : "";
        $organizer   = isset($_POST["organizer"]) ? trim($_POST["organizer"]) : "";
        $description = isset($_POST["description"]) ? trim($_POST["description"]) : "";

        if (empty($title) || empty($tag) || empty($eventDate) || empty($venue) || empty($organizer) || empty($description)) {
            $statusMsg  = "Failed to add event: All fields (Title, Category, Date, Venue, Organizer, Description) are required.";
            $statusType = "error";
        } else {
            $posterErr = "";
            $posterPath = $validateAndUploadPoster("poster", $posterErr);

            if ($posterPath === false) {
                $statusMsg  = "Failed to add event: " . $posterErr;
                $statusType = "error";
            } else {
                $insEvStmt = mysqli_prepare($conn, "INSERT INTO events (tag, title, event_date, venue, organizer, description, poster) VALUES (?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($insEvStmt, "sssssss", $tag, $title, $eventDate, $venue, $organizer, $description, $posterPath);
                if (mysqli_stmt_execute($insEvStmt)) {
                    $newEvId = mysqli_insert_id($conn);
                    $statusMsg  = "Success! Event #$newEvId ('$title') was created successfully" . ($posterPath ? " with uploaded poster." : ".");
                    $statusType = "success";
                } else {
                    $statusMsg  = "Database Error: " . mysqli_error($conn);
                    $statusType = "error";
                }
                mysqli_stmt_close($insEvStmt);
            }
        }
    }
    // B. EDIT EVENT
    elseif ($evAction === "edit") {
        $eventId     = isset($_POST["event_id"]) ? intval($_POST["event_id"]) : 0;
        $tag         = isset($_POST["tag"]) ? trim($_POST["tag"]) : "";
        $title       = isset($_POST["title"]) ? trim($_POST["title"]) : "";
        $eventDate   = isset($_POST["event_date"]) ? trim($_POST["event_date"]) : "";
        $venue       = isset($_POST["venue"]) ? trim($_POST["venue"]) : "";
        $organizer   = isset($_POST["organizer"]) ? trim($_POST["organizer"]) : "";
        $description = isset($_POST["description"]) ? trim($_POST["description"]) : "";

        if ($eventId <= 0 || empty($title) || empty($tag) || empty($eventDate) || empty($venue) || empty($organizer) || empty($description)) {
            $statusMsg  = "Failed to update event: All fields are required.";
            $statusType = "error";
        } else {
            $checkEv = mysqli_prepare($conn, "SELECT event_id, poster FROM events WHERE event_id = ? LIMIT 1");
            mysqli_stmt_bind_param($checkEv, "i", $eventId);
            mysqli_stmt_execute($checkEv);
            $evRow = mysqli_fetch_assoc(mysqli_stmt_get_result($checkEv));
            mysqli_stmt_close($checkEv);

            if (!$evRow) {
                $statusMsg  = "Failed to update: Event #$eventId was not found.";
                $statusType = "error";
            } else {
                $posterErr = "";
                $newPosterPath = $validateAndUploadPoster("poster", $posterErr);

                if ($newPosterPath === false) {
                    $statusMsg  = "Failed to update event: " . $posterErr;
                    $statusType = "error";
                } else {
                    if ($newPosterPath !== null) {
                        if (!empty($evRow['poster']) && file_exists(__DIR__ . "/../" . $evRow['poster'])) {
                            @unlink(__DIR__ . "/../" . $evRow['poster']);
                        }
                        $upEv = mysqli_prepare($conn, "UPDATE events SET tag = ?, title = ?, event_date = ?, venue = ?, organizer = ?, description = ?, poster = ? WHERE event_id = ?");
                        mysqli_stmt_bind_param($upEv, "sssssssi", $tag, $title, $eventDate, $venue, $organizer, $description, $newPosterPath, $eventId);
                    } else {
                        $upEv = mysqli_prepare($conn, "UPDATE events SET tag = ?, title = ?, event_date = ?, venue = ?, organizer = ?, description = ? WHERE event_id = ?");
                        mysqli_stmt_bind_param($upEv, "ssssssi", $tag, $title, $eventDate, $venue, $organizer, $description, $eventId);
                    }

                    if (mysqli_stmt_execute($upEv)) {
                        $statusMsg  = "Success! Event #$eventId ('$title') updated successfully" . ($newPosterPath ? " with new poster." : ".");
                        $statusType = "success";
                    } else {
                        $statusMsg  = "Database Error: " . mysqli_error($conn);
                        $statusType = "error";
                    }
                    mysqli_stmt_close($upEv);
                }
            }
        }
    }
    // C. DELETE EVENT
    elseif ($evAction === "delete") {
        $eventId = isset($_POST["event_id"]) ? intval($_POST["event_id"]) : 0;
        if ($eventId > 0) {
            $fEv = mysqli_prepare($conn, "SELECT event_id, title, poster FROM events WHERE event_id = ? LIMIT 1");
            mysqli_stmt_bind_param($fEv, "i", $eventId);
            mysqli_stmt_execute($fEv);
            $targetEv = mysqli_fetch_assoc(mysqli_stmt_get_result($fEv));
            mysqli_stmt_close($fEv);

            if (!$targetEv) {
                $statusMsg  = "Failed to delete: Event #$eventId not found.";
                $statusType = "error";
            } else {
                if (!empty($targetEv['poster']) && file_exists(__DIR__ . "/../" . $targetEv['poster'])) {
                    @unlink(__DIR__ . "/../" . $targetEv['poster']);
                }
                $dEv = mysqli_prepare($conn, "DELETE FROM events WHERE event_id = ?");
                mysqli_stmt_bind_param($dEv, "i", $eventId);
                if (mysqli_stmt_execute($dEv)) {
                    $statusMsg  = "Success! Event #$eventId ('" . htmlspecialchars($targetEv['title']) . "') and associated registrations deleted successfully.";
                    $statusType = "success";
                } else {
                    $statusMsg  = "Database Error: Could not delete event (" . mysqli_error($conn) . ").";
                    $statusType = "error";
                }
                mysqli_stmt_close($dEv);
            }
        }
    }
}

// 1. Fetch Live Statistics from MySQL database
$totalUsers    = 0;
$totalStudents = 0;
$totalAdmins   = 0;
$totalEvents   = 0;
$totalRegs     = 0;
$allStudents   = [];
$deptCounts    = [];
$eventsWithCounts = [];
$latestRegistrations = [];

// Query total registered users count (all accounts: students + admins)
$rUsers = mysqli_query($conn, "SELECT COUNT(*) FROM students");
if ($rUsers) {
    $rowU = mysqli_fetch_row($rUsers);
    $totalUsers = intval($rowU[0]);
}

// Query total student count (role != 'admin')
$r1 = mysqli_query($conn, "SELECT COUNT(*) FROM students WHERE role != 'admin'");
if ($r1) {
    $row1 = mysqli_fetch_row($r1);
    $totalStudents = intval($row1[0]);
}

// Query total administrator count
$rAdm = mysqli_query($conn, "SELECT COUNT(*) FROM students WHERE role = 'admin'");
if ($rAdm) {
    $rowA = mysqli_fetch_row($rAdm);
    $totalAdmins = intval($rowA[0]);
}

// Query total campus events count
$r2 = mysqli_query($conn, "SELECT COUNT(*) FROM events");
if ($r2) {
    $row2 = mysqli_fetch_row($r2);
    $totalEvents = intval($row2[0]);
}

// Query total event registrations count
$r3 = mysqli_query($conn, "SELECT COUNT(*) FROM registrations");
if ($r3) {
    $row3 = mysqli_fetch_row($r3);
    $totalRegs = intval($row3[0]);
}

// Query latest registrations with student and event details (JOIN registrations, students, events)
$regSql = "SELECT r.registration_id, r.registration_date, r.status,
                  s.fullname, s.enrollment_no, s.email, s.department,
                  e.title AS event_title, e.tag AS event_tag
           FROM registrations r
           JOIN students s ON r.student_id = s.student_id
           JOIN events e ON r.event_id = e.event_id
           ORDER BY r.registration_id DESC
           LIMIT 5";
$regRes = mysqli_query($conn, $regSql);
if ($regRes) {
    while ($regRow = mysqli_fetch_assoc($regRes)) {
        $latestRegistrations[] = $regRow;
    }
}

// Query all registered students for directory
$studentSql = "SELECT student_id, fullname, enrollment_no, email, mobile, department, semester, role, created_at 
               FROM students 
               WHERE role != 'admin' 
               ORDER BY student_id DESC";
$studentRes = mysqli_query($conn, $studentSql);
if ($studentRes) {
    while ($s = mysqli_fetch_assoc($studentRes)) {
        $allStudents[] = $s;
        $dept = !empty($s['department']) ? $s['department'] : 'General';
        if (!isset($deptCounts[$dept])) {
            $deptCounts[$dept] = 0;
        }
        $deptCounts[$dept]++;
    }
}

// Query events with their registration counts, descriptions, and posters
$eventSql = "SELECT e.event_id, e.tag, e.title, e.event_date, e.venue, e.organizer, e.description, e.poster, e.created_at, COUNT(r.registration_id) as reg_count
             FROM events e
             LEFT JOIN registrations r ON e.event_id = r.event_id
             GROUP BY e.event_id
             ORDER BY e.event_id DESC";
$eventRes = mysqli_query($conn, $eventSql);
if ($eventRes) {
    while ($ev = mysqli_fetch_assoc($eventRes)) {
        $eventsWithCounts[] = $ev;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Control Panel - Student Hub</title>
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
        /* =====================================================================
           PROFESSIONAL ADMIN DASHBOARD - STREAMLINED & INTERACTIVE STYLES
           ===================================================================== */
        
        /* Top Status Bar */
        .admin-top-strip {
            background: linear-gradient(135deg, #1B1F44 0%, #2b326d 100%);
            color: white;
            padding: 14px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
            border-bottom: 3px solid orange;
        }
        
        .admin-profile-meta {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .admin-avatar {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: orange;
            color: #1B1F44;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
            border: 2px solid white;
        }

        .admin-meta-text h3 {
            margin: 0;
            font-size: 16px;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .admin-meta-text p {
            margin: 2px 0 0;
            font-size: 12.5px;
            color: #ffd700;
        }

        .admin-badge-pill {
            background-color: #dc3545;
            color: white;
            font-size: 11px;
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 12px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .admin-telemetry-cluster {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .live-status-pill {
            display: flex;
            align-items: center;
            gap: 7px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 215, 0, 0.4);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            color: #ffffff;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            background-color: #28a745;
            border-radius: 50%;
            box-shadow: 0 0 0 rgba(40, 167, 69, 0.6);
            animation: pulse-green 1.8s infinite;
        }

        @keyframes pulse-green {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
            70% { box-shadow: 0 0 0 7px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }

        .timer-badge {
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            color: #ffdd57;
            font-family: monospace;
            font-weight: bold;
        }

        /* Container Layout */
        .admin-main-container {
            width: 90%;
            max-width: 1200px;
            margin: 20px auto 60px;
        }

        /* 4 Dedicated Metric Cards for Summary Statistics */
        .admin-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }

        .admin-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
            border-top: 4px solid #1B1F44;
            transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
            cursor: pointer;
            user-select: none;
        }

        .admin-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 22px rgba(0, 0, 0, 0.12);
            border-top-color: orange;
        }

        .admin-card.active-tab-card {
            border-top: 4px solid orange;
            border-color: orange;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(255, 165, 0, 0.22);
            position: relative;
        }

        .admin-card.active-tab-card::after {
            content: "";
            position: absolute;
            bottom: -11px;
            left: 50%;
            transform: translateX(-50%);
            border-width: 10px 10px 0 10px;
            border-style: solid;
            border-color: orange transparent transparent transparent;
        }

        .card-icon-wrap {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
            transition: transform 0.3s ease;
        }

        .admin-card:hover .card-icon-wrap {
            transform: scale(1.08);
        }

        .icon-blue   { background-color: #e0f2fe; color: #0369a1; }
        .icon-orange { background-color: #ffedd5; color: #c2410c; }
        .icon-purple { background-color: #ede9fe; color: #6d28d9; }
        .icon-green  { background-color: #dcfce7; color: #15803d; }

        .card-data {
            flex: 1;
        }

        .card-meta-title {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .card-meta-num {
            font-size: 26px;
            font-weight: 800;
            color: #1B1F44;
            line-height: 1.1;
            margin-bottom: 4px;
        }

        .card-meta-sub {
            font-size: 12px;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .admin-card.active-tab-card .card-meta-title {
            color: #1B1F44;
        }

        .admin-card.active-tab-card .card-meta-sub {
            color: #c2410c;
            font-weight: bold;
        }

        /* Single Unified Toolbar for Student Directory */
        .table-toolbar {
            background: white;
            border-radius: 8px 8px 0 0;
            padding: 14px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            border: 1px solid #e2e8f0;
            border-bottom: none;
        }

        .search-box-wrap {
            position: relative;
            flex: 1;
            min-width: 250px;
            max-width: 420px;
        }

        .search-box-wrap input {
            width: 100%;
            padding: 9px 12px 9px 36px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            font-size: 13.5px;
            outline: none;
            transition: all 0.2s ease;
        }

        .search-box-wrap input:focus {
            border-color: #1B1F44;
            box-shadow: 0 0 0 3px rgba(27, 31, 68, 0.12);
        }

        .search-icon {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 15px;
            pointer-events: none;
        }

        .filter-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-select {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            background-color: white;
            color: #1e293b;
            outline: none;
            cursor: pointer;
        }

        .btn-export {
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            border: 1px solid #bbf7d0;
            background: #f8fafc;
            color: #15803d;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-export:hover {
            background-color: #15803d;
            color: white;
            border-color: #15803d;
        }

        /* Student Table */
        .admin-table-card {
            background: white;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            overflow-x: auto;
        }

        .styled-admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            text-align: left;
        }

        .styled-admin-table th {
            background-color: #1B1F44;
            color: white;
            padding: 12px 14px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        .styled-admin-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }

        .styled-admin-table tbody tr:hover {
            background-color: #f8fafc;
        }

        .student-user-cell {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .user-avatar-initials {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1B1F44 0%, #3a4282 100%);
            color: #ffd700;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            flex-shrink: 0;
            border: 1px solid orange;
        }

        .user-name-title {
            font-weight: bold;
            color: #1B1F44;
            display: block;
        }

        .user-sub-id {
            font-size: 11.5px;
            color: #64748b;
        }

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
        .pill-gen { background-color: #f1f5f9; color: #475569; }

        .pill-active {
            background-color: #dcfce7;
            color: #166534;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Single Action Button per student row */
        .btn-view-profile {
            background-color: #1B1F44;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-view-profile:hover {
            background-color: orange;
            color: #1B1F44;
            transform: scale(1.05);
        }

        .btn-edit-student {
            background-color: #0284c7;
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-edit-student:hover {
            background-color: #0369a1;
            transform: scale(1.05);
        }

        .btn-delete-student {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-delete-student:hover {
            background-color: #b02a37;
            transform: scale(1.05);
        }

        .btn-add-primary {
            background-color: orange;
            color: #1B1F44;
            border: none;
            padding: 7px 14px;
            border-radius: 5px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s ease;
        }

        .btn-add-primary:hover {
            background-color: #ff9800;
            color: white;
        }

        .alert-msg {
            padding: 12px 18px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 13.5px;
        }

        .alert-msg.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-left: 5px solid #28a745;
        }

        .alert-msg.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-left: 5px solid #dc3545;
        }

        /* Modal Details */
        .admin-modal {
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

        .admin-modal-card {
            background-color: white;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: modalFadeIn 0.25s ease-out;
            border-top: 5px solid orange;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-header-bar {
            background-color: #1B1F44;
            color: white;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header-bar h3 {
            margin: 0;
            font-size: 17px;
        }

        .modal-close-x {
            background: transparent;
            border: none;
            color: white;
            font-size: 22px;
            cursor: pointer;
            font-weight: bold;
            line-height: 1;
        }

        .modal-close-x:hover {
            color: orange;
        }

        .modal-body-content {
            padding: 20px 24px;
        }

        .modal-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 9px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px;
        }

        .modal-detail-row:last-child {
            border-bottom: none;
        }

        .modal-detail-label {
            color: #64748b;
            font-weight: 600;
        }

        .modal-detail-val {
            color: #1e293b;
            font-weight: bold;
            text-align: right;
        }

        .modal-footer-bar {
            background-color: #f8fafc;
            padding: 12px 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid #e2e8f0;
        }

        /* Tab Panes */
        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        /* Events Tab */
        .events-admin-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .event-admin-card {
            background: white;
            border-radius: 8px;
            padding: 18px;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #1B1F44;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            transition: all 0.25s ease;
        }

        .event-admin-card:hover {
            transform: translateY(-3px);
            border-left-color: orange;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.08);
        }

        .event-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .event-poster-container {
            width: 100%;
            height: 145px;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 12px;
            background-color: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            border: 1px solid #e2e8f0;
        }

        .event-poster-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.3s ease;
        }

        .event-admin-card:hover .event-poster-img {
            transform: scale(1.03);
        }

        .event-poster-placeholder {
            color: #64748b;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            width: 100%;
            height: 100%;
            justify-content: center;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            box-sizing: border-box;
        }

        .event-card-actions {
            display: flex;
            gap: 8px;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid #f1f5f9;
        }

        .event-card-actions button {
            flex: 1;
            text-align: center;
            justify-content: center;
        }

        /* Dark Mode */
        body.dark-mode .admin-top-strip {
            background: #111;
        }

        body.dark-mode .admin-card,
        body.dark-mode .table-toolbar,
        body.dark-mode .admin-table-card,
        body.dark-mode .event-admin-card,
        body.dark-mode .admin-modal-card {
            background-color: #1e1e1e !important;
            border-color: #333 !important;
            color: #e0e0e0 !important;
        }

        body.dark-mode .card-meta-num,
        body.dark-mode .user-name-title {
            color: #ffffff !important;
        }

        body.dark-mode .card-meta-title,
        body.dark-mode .card-meta-sub,
        body.dark-mode .modal-detail-label,
        body.dark-mode .user-sub-id {
            color: #aaa !important;
        }

        body.dark-mode .styled-admin-table td {
            color: #ddd !important;
            border-bottom-color: #2a2a2a !important;
        }

        body.dark-mode .styled-admin-table tbody tr:hover {
            background-color: #262626 !important;
        }

        body.dark-mode .search-box-wrap input,
        body.dark-mode .filter-select {
            background-color: #2a2a2a !important;
            color: white !important;
            border-color: #444 !important;
        }

        body.dark-mode .admin-card.active-tab-card::after {
            border-color: orange transparent transparent transparent;
        }

        body.dark-mode .modal-footer-bar {
            background-color: #252525 !important;
            border-top-color: #333 !important;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .admin-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 768px) {
            .admin-stats-grid {
                grid-template-columns: 1fr;
            }
            .events-admin-grid {
                grid-template-columns: 1fr;
            }
            .admin-top-strip {
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }
            .admin-profile-meta {
                flex-direction: column;
            }
        }
    </style>
</head>
<body class="dashboard-body">
    
    <!-- HEADER -->
    <iframe
        src="header.html"
        class="header-frame">
    </iframe>

    <h1 class="page-title">ADMINISTRATOR CONTROL PANEL</h1>

    <!-- DYNAMIC NAVIGATION BASED ON ROLE -->
    <nav>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="admin_dashboard.php" class="active" style="background-color: #28a745; color: white;">ADMIN DASHBOARD</a>
            <a href="manage_students.php">MANAGE STUDENTS</a>
            <a href="manage_events.php">MANAGE EVENTS</a>
            <a href="view_records.php">ALL RECORDS</a>
            <a href="dashboard.php">STUDENT VIEW</a>
        <?php else: ?>
            <a href="dashboard.php" class="active">DASHBOARD</a>
            <a href="profile.html">PROFILE</a>
            <a href="course.html">COURSES</a>
            <a href="event.html">EVENTS</a>
        <?php endif; ?>
        <a href="contact.html">CONTACT</a>
        <a href="logout.php" style="background-color: #dc3545; color: white;">LOGOUT 🚪</a>
    </nav>

    <!-- ADMIN TELEMETRY TOP BAR (HEARTBEAT & INACTIVITY COUNTDOWN) -->
    <div class="admin-top-strip">
        <div class="admin-profile-meta">
            <div class="admin-avatar">👨‍💼</div>
            <div class="admin-meta-text">
                <h3>
                    <?= htmlspecialchars($adminName) ?> 
                    <span class="admin-badge-pill">Role: Administrator</span>
                </h3>
                <p>ID: <code><?= htmlspecialchars($adminEnroll) ?></code> • <?= htmlspecialchars($adminEmail) ?></p>
            </div>
        </div>

        <div class="admin-telemetry-cluster">
            <div class="live-status-pill" title="Server Heartbeat Active">
                <span class="pulse-dot"></span>
                <span>System Online</span>
            </div>
            
            <div class="timer-badge" id="sessionTimerBadge" title="Session inactivity countdown">
                ⏱ Timeout in: <span id="countdownDisplay">15:00</span>
            </div>
        </div>
    </div>

    <!-- MAIN DASHBOARD CONTENT -->
    <div class="admin-main-container">

        <?php $isTabEvents = ($activeTab === 'tab-events'); ?>
        <!-- 4 SUMMARY STATISTIC CARDS (USER STATS, STUDENT COUNT, EVENT COUNT, REGISTRATIONS) -->
        <div class="admin-stats-grid">
            <!-- 1. USER STATISTICS -->
            <div class="admin-card" id="cardUsers" title="Total registered portal users">
                <div class="card-icon-wrap icon-purple">📊</div>
                <div class="card-data">
                    <div class="card-meta-title">User Statistics</div>
                    <div class="card-meta-num"><?= intval($totalUsers) ?></div>
                    <div class="card-meta-sub">
                        <span>👥 <?= intval($totalStudents) ?> Students &bull; 🛡️ <?= intval($totalAdmins) ?> Admin</span>
                    </div>
                </div>
            </div>

            <!-- 2. STUDENT COUNT -->
            <div class="admin-card <?= !$isTabEvents ? 'active-tab-card' : '' ?>" id="cardStudents" onclick="switchTab('tab-students')" title="Click to view student directory">
                <div class="card-icon-wrap icon-blue">👥</div>
                <div class="card-data">
                    <div class="card-meta-title">Student Count</div>
                    <div class="card-meta-num"><?= intval($totalStudents) ?></div>
                    <div class="card-meta-sub view-indicator">
                        <span id="indicatorStudents"><?= !$isTabEvents ? 'Viewing Student Directory ▼' : 'Click to View Students ➜' ?></span>
                    </div>
                </div>
            </div>

            <!-- 3. EVENT COUNT -->
            <div class="admin-card <?= $isTabEvents ? 'active-tab-card' : '' ?>" id="cardEvents" onclick="switchTab('tab-events')" title="Click to view campus events & registrations">
                <div class="card-icon-wrap icon-orange">🏆</div>
                <div class="card-data">
                    <div class="card-meta-title">Event Count</div>
                    <div class="card-meta-num"><?= intval($totalEvents) ?></div>
                    <div class="card-meta-sub view-indicator">
                        <span id="indicatorEvents"><?= $isTabEvents ? 'Viewing Campus Events ▼' : 'Click to View Events ➜' ?></span>
                    </div>
                </div>
            </div>

            <!-- 4. LATEST REGISTRATIONS COUNT -->
            <div class="admin-card" id="cardRegistrations" onclick="document.getElementById('latestRegistrationsSection').scrollIntoView({behavior: 'smooth'})" title="Click to view latest registrations">
                <div class="card-icon-wrap icon-green">📝</div>
                <div class="card-data">
                    <div class="card-meta-title">Event Registrations</div>
                    <div class="card-meta-num"><?= intval($totalRegs) ?></div>
                    <div class="card-meta-sub view-indicator">
                        <span>View Latest Below ▼</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- LATEST REGISTRATIONS TABLE SECTION -->
        <div class="admin-table-card" id="latestRegistrationsSection" style="margin-bottom: 25px; border-radius: 8px;">
            <div style="padding: 14px 20px; background: #fafafa; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; color: #1B1F44; font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                        <span>📝</span> Latest Registrations
                    </h3>
                    <p style="margin: 3px 0 0; font-size: 12px; color: #64748b;">Most recent campus event registrations submitted by students</p>
                </div>
                <span class="pill-badge pill-ce" style="font-size: 12px;">
                    Total: <?= intval($totalRegs) ?> Registrations
                </span>
            </div>

            <table class="styled-admin-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">#ID</th>
                        <th>Student Name</th>
                        <th>Enrollment No</th>
                        <th>Department</th>
                        <th>Event Registered</th>
                        <th>Registration Date</th>
                        <th style="text-align: center; width: 120px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($latestRegistrations)): ?>
                        <?php foreach ($latestRegistrations as $reg): ?>
                            <tr>
                                <td><b>#<?= htmlspecialchars($reg['registration_id']) ?></b></td>
                                <td>
                                    <span class="user-name-title"><?= htmlspecialchars($reg['fullname']) ?></span>
                                    <span style="font-size: 11.5px; color: #64748b;"><?= htmlspecialchars($reg['email']) ?></span>
                                </td>
                                <td><code><?= htmlspecialchars($reg['enrollment_no']) ?></code></td>
                                <td><span class="pill-badge pill-gen"><?= htmlspecialchars($reg['department']) ?></span></td>
                                <td>
                                    <span style="font-weight: 600; color: #1B1F44;"><?= htmlspecialchars($reg['event_title']) ?></span>
                                    <span class="pill-badge pill-ce" style="font-size: 10.5px; margin-left: 4px;"><?= htmlspecialchars($reg['event_tag']) ?></span>
                                </td>
                                <td style="font-size: 12.5px; color: #475569;">
                                    📅 <?= !empty($reg['registration_date']) ? date("M d, Y h:i A", strtotime($reg['registration_date'])) : 'Recently' ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="pill-badge pill-active">
                                        ✓ <?= htmlspecialchars($reg['status'] ?? 'Confirmed') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 25px; color: #64748b;">
                                ℹ️ No registrations found in the database.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ===============================================================
             TAB 1: STUDENT DIRECTORY (SINGLE UNIFIED VIEW FOR STUDENT DATA)
             =============================================================== -->
        <div class="tab-pane <?= !$isTabEvents ? 'active' : '' ?>" id="tab-students">
            
            <?php if (!empty($statusMsg)): ?>
                <div class="alert-msg <?= htmlspecialchars($statusType) ?>" id="adminStatusAlert" style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <?= ($statusType === 'success') ? '✓' : '⚠️' ?> <?= htmlspecialchars($statusMsg) ?>
                    </div>
                    <button type="button" onclick="document.getElementById('adminStatusAlert').style.display='none'" style="background: none; border: none; font-size: 18px; cursor: pointer; color: inherit;">&times;</button>
                </div>
            <?php endif; ?>

            <!-- TOOLBAR: SEARCH, FILTER, ADD & EXPORT -->
            <div class="table-toolbar">
                <div class="search-box-wrap">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="studentSearchInput" placeholder="Search by student name, enrollment ID, or email..." onkeyup="filterStudentTable()">
                </div>

                <div class="filter-controls">
                    <select class="filter-select" id="deptFilterSelect" onchange="filterStudentTable()">
                        <option value="ALL">All Departments (<?= intval($totalStudents) ?>)</option>
                        <?php foreach ($deptCounts as $deptName => $cnt): ?>
                            <option value="<?= htmlspecialchars($deptName) ?>"><?= htmlspecialchars($deptName) ?> (<?= $cnt ?>)</option>
                        <?php endforeach; ?>
                    </select>

                    <select class="filter-select" id="semFilterSelect" onchange="filterStudentTable()">
                        <option value="ALL">All Semesters</option>
                        <option value="Semester 1">Semester 1</option>
                        <option value="Semester 2">Semester 2</option>
                        <option value="Semester 3">Semester 3</option>
                        <option value="Semester 4">Semester 4</option>
                        <option value="Semester 5">Semester 5</option>
                        <option value="Semester 6">Semester 6</option>
                        <option value="Semester 7">Semester 7</option>
                        <option value="Semester 8">Semester 8</option>
                    </select>

                    <button type="button" class="btn-add-primary" onclick="openAdminAddModal()" title="Add new student record">
                        ➕ Add Student
                    </button>

                    <button type="button" class="btn-export" onclick="exportStudentsToCSV()" title="Export student directory to CSV">
                        📥 Export CSV
                    </button>
                </div>
            </div>

            <!-- STUDENT TABLE -->
            <div class="admin-table-card">
                <table class="styled-admin-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#ID</th>
                            <th>Student Name</th>
                            <th>Enrollment No</th>
                            <th>Email Address</th>
                            <th>Department</th>
                            <th>Semester</th>
                            <th>Status</th>
                            <th style="text-align: center; width: 170px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="studentTableBody">
                        <?php if (!empty($allStudents)): ?>
                            <?php foreach ($allStudents as $st): 
                                $parts = explode(' ', trim($st['fullname']));
                                $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                                
                                $dLower = strtolower($st['department']);
                                $deptClass = 'pill-gen';
                                if (strpos($dLower, 'computer') !== false) $deptClass = 'pill-ce';
                                elseif (strpos($dLower, 'information') !== false) $deptClass = 'pill-it';
                                elseif (strpos($dLower, 'science') !== false) $deptClass = 'pill-cs';
                            ?>
                                <tr class="student-row" 
                                    data-id="<?= htmlspecialchars($st['student_id']) ?>"
                                    data-name="<?= htmlspecialchars(strtolower($st['fullname'])) ?>"
                                    data-rawname="<?= htmlspecialchars($st['fullname']) ?>"
                                    data-enroll="<?= htmlspecialchars(strtolower($st['enrollment_no'])) ?>"
                                    data-rawenroll="<?= htmlspecialchars($st['enrollment_no']) ?>"
                                    data-email="<?= htmlspecialchars(strtolower($st['email'])) ?>"
                                    data-rawemail="<?= htmlspecialchars($st['email']) ?>"
                                    data-dept="<?= htmlspecialchars($st['department']) ?>"
                                    data-mobile="<?= htmlspecialchars($st['mobile']) ?>"
                                    data-semester="<?= htmlspecialchars($st['semester']) ?>"
                                    data-created="<?= htmlspecialchars($st['created_at']) ?>">
                                    <td><b>#<?= htmlspecialchars($st['student_id']) ?></b></td>
                                    <td>
                                        <div class="student-user-cell">
                                            <div class="user-avatar-initials"><?= htmlspecialchars($initials) ?></div>
                                            <div>
                                                <span class="user-name-title"><?= htmlspecialchars($st['fullname']) ?></span>
                                                <span class="user-sub-id">Registered <?= date('M d, Y', strtotime($st['created_at'])) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><code><?= htmlspecialchars($st['enrollment_no']) ?></code></td>
                                    <td><?= htmlspecialchars($st['email']) ?></td>
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
                                        <div style="display: flex; gap: 4px; justify-content: center;">
                                            <button type="button" class="btn-view-profile" onclick="openStudentModal(this)" title="View complete details">
                                                👁️ View
                                            </button>
                                            <button type="button" class="btn-edit-student" onclick="openAdminEditModal(this)" title="Edit student record">
                                                ✏️ Edit
                                            </button>
                                            <button type="button" class="btn-delete-student" onclick="openAdminDeleteModal(this)" title="Delete student record">
                                                🗑️ Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 30px; color: #888;">
                                    No student records found in database.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div id="noMatchMessage" style="display: none; text-align: center; padding: 35px; color: #64748b; font-size: 14px;">
                    🔍 No students found matching your search filter. <button type="button" onclick="resetFilters()" style="background: none; border: none; color: blue; text-decoration: underline; cursor: pointer;">Clear filter</button>
                </div>
            </div>
            <div style="margin-top: 10px; font-size: 12.5px; color: #64748b; display: flex; justify-content: space-between;">
                <span>Showing <b id="displayedCount"><?= count($allStudents) ?></b> of <?= count($allStudents) ?> registered students</span>
                <span>Live from MySQL <code>students</code></span>
            </div>
        </div>

        <!-- ===============================================================
             TAB 2: CAMPUS EVENTS & REGISTRATION BREAKDOWN
             =============================================================== -->
        <div class="tab-pane <?= $isTabEvents ? 'active' : '' ?>" id="tab-events">
            
            <?php if (!empty($statusMsg) && $isTabEvents): ?>
                <div class="alert-msg <?= htmlspecialchars($statusType) ?>" id="adminEventStatusAlert" style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <?= ($statusType === 'success') ? '✓' : '⚠️' ?> <?= htmlspecialchars($statusMsg) ?>
                    </div>
                    <button type="button" onclick="document.getElementById('adminEventStatusAlert').style.display='none'" style="background: none; border: none; font-size: 18px; cursor: pointer; color: inherit;">&times;</button>
                </div>
            <?php endif; ?>

            <!-- TOOLBAR: SEARCH, CATEGORY FILTER, ADD EVENT & STANDALONE PAGE -->
            <div class="table-toolbar">
                <div class="search-box-wrap">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="eventSearchInput" placeholder="Search events by title, venue, or organizer..." onkeyup="filterEventCards()">
                </div>

                <div class="filter-controls">
                    <select class="filter-select" id="eventTagFilter" onchange="filterEventCards()">
                        <option value="ALL">All Categories (<?= intval($totalEvents) ?>)</option>
                        <option value="Technical Fest">Technical Fest</option>
                        <option value="Cultural Fest">Cultural Fest</option>
                        <option value="Workshop">Workshop</option>
                        <option value="Sports Meet">Sports Meet</option>
                        <option value="Academic">Academic</option>
                    </select>

                    <button type="button" class="btn-add-primary" onclick="openAdminAddEventModal()" title="Add new campus event with poster">
                        ➕ Add Event
                    </button>

                    <a href="manage_events.php" class="btn-export" style="text-decoration: none; display: inline-flex; align-items: center; gap: 5px;" title="Open standalone Event Management Module">
                        📋 Manage Events Page ➜
                    </a>
                </div>
            </div>

            <div class="events-admin-grid" id="eventsGridContainer">
                <?php foreach ($eventsWithCounts as $ev): ?>
                    <div class="event-admin-card" 
                         data-title="<?= htmlspecialchars(strtolower($ev['title'])) ?>"
                         data-tag="<?= htmlspecialchars($ev['tag']) ?>"
                         data-venue="<?= htmlspecialchars(strtolower($ev['venue'])) ?>"
                         data-organizer="<?= htmlspecialchars(strtolower($ev['organizer'])) ?>">
                        
                        <div class="event-poster-container">
                            <?php if (!empty($ev['poster']) && file_exists(__DIR__ . "/../" . $ev['poster'])): ?>
                                <img src="../<?= htmlspecialchars($ev['poster']) ?>" alt="<?= htmlspecialchars($ev['title']) ?> Poster" class="event-poster-img">
                            <?php else: ?>
                                <div class="event-poster-placeholder">
                                    <span>🎨 No Poster Uploaded</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="event-header-row">
                            <span class="pill-badge pill-ce" style="font-size: 11px;"><?= htmlspecialchars($ev['tag']) ?></span>
                            <span class="pill-badge" style="background-color: #f1f5f9; color: #1B1F44; font-weight: bold;">
                                👥 <?= intval($ev['reg_count']) ?> Registered
                            </span>
                        </div>
                        <h4 style="color: #1B1F44; margin: 0 0 6px; font-size: 15px;"><?= htmlspecialchars($ev['title']) ?></h4>
                        <p style="margin: 0 0 6px; font-size: 12.5px; color: #64748b;">
                            📅 <b>Date:</b> <?= htmlspecialchars($ev['event_date']) ?> &bull; 📍 <b>Venue:</b> <?= htmlspecialchars($ev['venue']) ?>
                        </p>
                        <p style="margin: 0 0 8px; font-size: 12px; color: #475569;">
                            🏢 <b>Organizer:</b> <?= htmlspecialchars($ev['organizer']) ?>
                        </p>
                        <p style="margin: 0 0 10px; font-size: 12px; color: #64748b; line-height: 1.4;">
                            <?= htmlspecialchars(mb_strimwidth($ev['description'] ?? '', 0, 110, '...')) ?>
                        </p>
                        <div class="event-card-actions">
                            <button type="button" class="btn-view-profile" onclick='viewEventDetails(<?= json_encode($ev, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View full event details & poster">👁️ View</button>
                            <button type="button" class="btn-edit-student" onclick='openAdminEditEventModal(<?= json_encode($ev, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit event and update poster">✏️ Edit</button>
                            <button type="button" class="btn-delete-student" onclick="openAdminDeleteEventModal(<?= intval($ev['event_id']) ?>, '<?= htmlspecialchars(addslashes($ev['title'])) ?>')" title="Delete event">🗑️ Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="noEventsMessage" style="display: none; text-align: center; padding: 40px; background: white; border-radius: 8px; border: 1px dashed #cbd5e1; margin-top: 15px; color: #64748b;">
                🔍 No events matched your search or category filter. <button type="button" onclick="resetEventFilters()" style="background: none; border: none; color: blue; text-decoration: underline; cursor: pointer;">Clear filter</button>
            </div>

            <div style="margin-top: 10px; font-size: 12.5px; color: #64748b; display: flex; justify-content: space-between;">
                <span>Total <b id="displayedEventsCount"><?= count($eventsWithCounts) ?></b> campus events</span>
                <span>Live from MySQL <code>events</code></span>
            </div>
        </div>

    </div>

    <!-- STUDENT PROFILE DETAILS MODAL -->
    <div class="admin-modal" id="studentDetailModal" onclick="handleBackdropClick(event)">
        <div class="admin-modal-card">
            <div class="modal-header-bar">
                <h3>👤 Student Profile Dossier</h3>
                <button type="button" class="modal-close-x" onclick="closeStudentModal()">&times;</button>
            </div>
            <div class="modal-body-content">
                <div style="text-align: center; margin-bottom: 18px;">
                    <div class="user-avatar-initials" id="mAvatar" style="width: 56px; height: 56px; font-size: 20px; margin: 0 auto 10px;">--</div>
                    <h3 id="mFullName" style="margin: 0; color: #1B1F44; font-size: 19px;">Student Name</h3>
                    <span id="mEnrollBadge" class="pill-badge pill-ce" style="margin-top: 4px;">25DCE000</span>
                </div>

                <div class="modal-detail-row">
                    <span class="modal-detail-label">Student ID:</span>
                    <span class="modal-detail-val" id="mStudentId">#0</span>
                </div>
                <div class="modal-detail-row">
                    <span class="modal-detail-label">Email Address:</span>
                    <span class="modal-detail-val" id="mEmail">user@charusat.edu.in</span>
                </div>
                <div class="modal-detail-row">
                    <span class="modal-detail-label">Mobile Number:</span>
                    <span class="modal-detail-val" id="mMobile">+91 0000000000</span>
                </div>
                <div class="modal-detail-row">
                    <span class="modal-detail-label">Department:</span>
                    <span class="modal-detail-val" id="mDepartment">Computer Engineering</span>
                </div>
                <div class="modal-detail-row">
                    <span class="modal-detail-label">Semester:</span>
                    <span class="modal-detail-val" id="mSemester">Semester 3</span>
                </div>
                <div class="modal-detail-row">
                    <span class="modal-detail-label">Registration Date:</span>
                    <span class="modal-detail-val" id="mCreated">YYYY-MM-DD</span>
                </div>
                <div class="modal-detail-row">
                    <span class="modal-detail-label">Account Role:</span>
                    <span class="modal-detail-val" style="color: #28a745;">Student Verified</span>
                </div>
            </div>
            <div class="modal-footer-bar">
                <button type="button" class="btn-export" style="border-color: #cbd5e1; color: #1B1F44;" onclick="sendMailToStudent()">
                    ✉️ Send Email
                </button>
                <button type="button" class="btn-view-profile" onclick="closeStudentModal()">
                    Close Window
                </button>
            </div>
        </div>
    </div>

    <!-- 1. ADMIN ADD STUDENT MODAL -->
    <div class="admin-modal" id="adminAddStudentModal" onclick="handleAdminBackdrop(event, 'adminAddStudentModal')">
        <div class="admin-modal-card" style="max-width: 540px;">
            <div class="modal-header-bar" style="background-color: #1B1F44; border-bottom: 3px solid orange;">
                <h3>➕ Add New Student Record</h3>
                <button type="button" class="modal-close-x" onclick="closeAdminAddModal()">&times;</button>
            </div>
            <form method="POST" action="admin_dashboard.php">
                <input type="hidden" name="student_action" value="add">
                <div class="modal-body-content" style="padding: 20px;">
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Full Name <span style="color: red;">*</span></label>
                        <input type="text" name="fullname" id="admin_add_name" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Enrollment No <span style="color: red;">*</span></label>
                            <input type="text" name="enrollment_no" id="admin_add_enroll" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Mobile Number <span style="color: red;">*</span></label>
                            <input type="text" name="mobile" id="admin_add_mobile" pattern="[0-9]{10}" maxlength="10" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        </div>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Email Address <span style="color: red;">*</span></label>
                        <input type="email" name="email" id="admin_add_email" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Department <span style="color: red;">*</span></label>
                            <select name="department" id="admin_add_dept" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                                <option value="Computer Engineering">Computer Engineering</option>
                                <option value="Information Technology">Information Technology</option>
                                <option value="Computer Science & Engineering">Computer Science & Engineering</option>
                                <option value="Electronics & Communication">Electronics & Communication</option>
                                <option value="Mechanical Engineering">Mechanical Engineering</option>
                                <option value="Civil Engineering">Civil Engineering</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Semester <span style="color: red;">*</span></label>
                            <select name="semester" id="admin_add_sem" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                                <option value="Semester 1">Semester 1</option>
                                <option value="Semester 2">Semester 2</option>
                                <option value="Semester 3" selected>Semester 3</option>
                                <option value="Semester 4">Semester 4</option>
                                <option value="Semester 5">Semester 5</option>
                                <option value="Semester 6">Semester 6</option>
                                <option value="Semester 7">Semester 7</option>
                                <option value="Semester 8">Semester 8</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-bottom: 8px;">
                        <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Password <span style="color: red;">*</span></label>
                        <input type="password" name="password" id="admin_add_pass" minlength="6" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        <small style="color: #64748b; font-size: 11.5px;">Password will be securely hashed with bcrypt using PHP <code>password_hash()</code>.</small>
                    </div>
                </div>
                <div class="modal-footer-bar">
                    <button type="button" class="btn-export" onclick="closeAdminAddModal()">Cancel</button>
                    <button type="submit" class="btn-view-profile" style="background-color: #1B1F44;">💾 Save Student</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. ADMIN EDIT STUDENT MODAL -->
    <div class="admin-modal" id="adminEditStudentModal" onclick="handleAdminBackdrop(event, 'adminEditStudentModal')">
        <div class="admin-modal-card" style="max-width: 540px;">
            <div class="modal-header-bar" style="background-color: #0284c7; border-bottom: 3px solid #0369a1;">
                <h3>✏️ Edit Student Record</h3>
                <button type="button" class="modal-close-x" onclick="closeAdminEditModal()">&times;</button>
            </div>
            <form method="POST" action="admin_dashboard.php">
                <input type="hidden" name="student_action" value="edit">
                <input type="hidden" name="student_id" id="admin_edit_id" value="">
                <div class="modal-body-content" style="padding: 20px;">
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Full Name <span style="color: red;">*</span></label>
                        <input type="text" name="fullname" id="admin_edit_name" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Enrollment No <span style="color: red;">*</span></label>
                            <input type="text" name="enrollment_no" id="admin_edit_enroll" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Mobile Number <span style="color: red;">*</span></label>
                            <input type="text" name="mobile" id="admin_edit_mobile" pattern="[0-9]{10}" maxlength="10" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        </div>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Email Address <span style="color: red;">*</span></label>
                        <input type="email" name="email" id="admin_edit_email" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Department <span style="color: red;">*</span></label>
                            <select name="department" id="admin_edit_dept" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                                <option value="Computer Engineering">Computer Engineering</option>
                                <option value="Information Technology">Information Technology</option>
                                <option value="Computer Science & Engineering">Computer Science & Engineering</option>
                                <option value="Electronics & Communication">Electronics & Communication</option>
                                <option value="Mechanical Engineering">Mechanical Engineering</option>
                                <option value="Civil Engineering">Civil Engineering</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Semester <span style="color: red;">*</span></label>
                            <select name="semester" id="admin_edit_sem" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                                <option value="Semester 1">Semester 1</option>
                                <option value="Semester 2">Semester 2</option>
                                <option value="Semester 3">Semester 3</option>
                                <option value="Semester 4">Semester 4</option>
                                <option value="Semester 5">Semester 5</option>
                                <option value="Semester 6">Semester 6</option>
                                <option value="Semester 7">Semester 7</option>
                                <option value="Semester 8">Semester 8</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-bottom: 8px;">
                        <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">New Password (Optional)</label>
                        <input type="password" name="new_password" id="admin_edit_pass" minlength="6" placeholder="Leave blank to retain current password" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                    </div>
                </div>
                <div class="modal-footer-bar">
                    <button type="button" class="btn-export" onclick="closeAdminEditModal()">Cancel</button>
                    <button type="submit" class="btn-view-profile" style="background-color: #0284c7;">💾 Update Student</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. ADMIN DELETE STUDENT MODAL -->
    <div class="admin-modal" id="adminDeleteStudentModal" onclick="handleAdminBackdrop(event, 'adminDeleteStudentModal')">
        <div class="admin-modal-card" style="max-width: 440px;">
            <div class="modal-header-bar" style="background-color: #dc3545; border-bottom: 3px solid #b02a37;">
                <h3>🗑️ Confirm Student Deletion</h3>
                <button type="button" class="modal-close-x" onclick="closeAdminDeleteModal()">&times;</button>
            </div>
            <form method="POST" action="admin_dashboard.php">
                <input type="hidden" name="student_action" value="delete">
                <input type="hidden" name="student_id" id="admin_delete_id" value="">
                <div class="modal-body-content" style="padding: 22px; text-align: center;">
                    <div style="font-size: 38px; margin-bottom: 10px;">⚠️</div>
                    <h4 style="margin: 0 0 8px; color: #1B1F44; font-size: 17px;">Permanently delete this student?</h4>
                    <p style="color: #475569; font-size: 13.5px; margin: 0 0 14px;">
                        <strong id="admin_delete_name">--</strong> (<code id="admin_delete_enroll">--</code>)
                    </p>
                    <div style="background-color: #fff3cd; color: #856404; font-size: 12px; padding: 10px; border-radius: 6px; border: 1px solid #ffeeba; text-align: left;">
                        Note: Deleting will remove the student record and related event registrations from MySQL.
                    </div>
                </div>
                <div class="modal-footer-bar" style="justify-content: center; gap: 12px;">
                    <button type="button" class="btn-export" onclick="closeAdminDeleteModal()">No, Cancel</button>
                    <button type="submit" class="btn-delete-student" style="padding: 8px 16px; font-size: 13px;">🗑️ Yes, Delete Record</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. ADMIN ADD EVENT MODAL (WITH POSTER UPLOAD & VALIDATION) -->
    <div class="admin-modal" id="adminAddEventModal" onclick="handleAdminBackdrop(event, 'adminAddEventModal')">
        <div class="admin-modal-card" style="max-width: 560px;">
            <div class="modal-header-bar" style="background-color: #1B1F44; border-bottom: 3px solid orange;">
                <h3>➕ Add New Campus Event</h3>
                <button type="button" class="modal-close-x" onclick="closeAdminAddEventModal()">&times;</button>
            </div>
            <form method="POST" action="admin_dashboard.php" enctype="multipart/form-data">
                <input type="hidden" name="event_action" value="add">
                <input type="hidden" name="active_tab" value="tab-events">
                <div class="modal-body-content" style="max-height: 75vh; overflow-y: auto;">
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Event Title *</label>
                        <input type="text" name="title" id="add_ev_title" required placeholder="e.g., TechX 2026 - National Hackathon" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Category / Tag *</label>
                            <select name="tag" id="add_ev_tag" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                                <option value="Technical Fest">Technical Fest</option>
                                <option value="Cultural Fest">Cultural Fest</option>
                                <option value="Workshop">Workshop</option>
                                <option value="Sports Meet">Sports Meet</option>
                                <option value="Academic">Academic</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Event Date *</label>
                            <input type="text" name="event_date" id="add_ev_date" required placeholder="e.g., Nov 15 - 16, 2026" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Venue / Location *</label>
                            <input type="text" name="venue" id="add_ev_venue" required placeholder="e.g., Central Auditorium" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Organizer *</label>
                            <input type="text" name="organizer" id="add_ev_organizer" required placeholder="e.g., Computer Engineering Dept." style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        </div>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Event Description *</label>
                        <textarea name="description" id="add_ev_description" rows="3" required placeholder="Detailed description of the campus event..." style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box; resize: vertical;"></textarea>
                    </div>
                    <div style="background: #f8fafc; border: 1px dashed #94a3b8; border-radius: 6px; padding: 12px; margin-bottom: 8px;">
                        <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">🖼️ Event Poster Upload</label>
                        <input type="file" name="poster" id="add_ev_poster" accept=".jpg,.jpeg,.png,.webp,.gif" onchange="validateAndPreviewPoster(this, 'add_ev_poster_preview', 'add_ev_poster_err')" style="width: 100%; font-size: 12.5px;">
                        <div style="font-size: 11.5px; color: #64748b; margin-top: 5px;">
                            ✓ <b>Allowed Types:</b> JPG, JPEG, PNG, WEBP, GIF &bull; <b>Max Size:</b> 2 MB
                        </div>
                        <div id="add_ev_poster_err" style="color: #dc3545; font-size: 12px; font-weight: bold; margin-top: 6px; display: none;"></div>
                        <div id="add_ev_poster_preview" style="margin-top: 10px; display: none;">
                            <img src="" alt="Poster Preview" style="max-height: 140px; border-radius: 6px; border: 1px solid #cbd5e1; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
                        </div>
                    </div>
                </div>
                <div class="modal-footer-bar">
                    <button type="button" class="btn-export" onclick="closeAdminAddEventModal()">Cancel</button>
                    <button type="submit" class="btn-add-primary" style="background-color: orange; color: #1B1F44;">➕ Add Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 5. ADMIN EDIT EVENT MODAL (WITH POSTER REPLACE & VALIDATION) -->
    <div class="admin-modal" id="adminEditEventModal" onclick="handleAdminBackdrop(event, 'adminEditEventModal')">
        <div class="admin-modal-card" style="max-width: 560px;">
            <div class="modal-header-bar" style="background-color: #0284c7; border-bottom: 3px solid #0369a1;">
                <h3>✏️ Edit Campus Event</h3>
                <button type="button" class="modal-close-x" onclick="closeAdminEditEventModal()">&times;</button>
            </div>
            <form method="POST" action="admin_dashboard.php" enctype="multipart/form-data">
                <input type="hidden" name="event_action" value="edit">
                <input type="hidden" name="active_tab" value="tab-events">
                <input type="hidden" name="event_id" id="edit_ev_id" value="">
                <div class="modal-body-content" style="max-height: 75vh; overflow-y: auto;">
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Event Title *</label>
                        <input type="text" name="title" id="edit_ev_title" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Category / Tag *</label>
                            <select name="tag" id="edit_ev_tag" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                                <option value="Technical Fest">Technical Fest</option>
                                <option value="Cultural Fest">Cultural Fest</option>
                                <option value="Workshop">Workshop</option>
                                <option value="Sports Meet">Sports Meet</option>
                                <option value="Academic">Academic</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Event Date *</label>
                            <input type="text" name="event_date" id="edit_ev_date" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Venue / Location *</label>
                            <input type="text" name="venue" id="edit_ev_venue" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Organizer *</label>
                            <input type="text" name="organizer" id="edit_ev_organizer" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        </div>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Event Description *</label>
                        <textarea name="description" id="edit_ev_description" rows="3" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box; resize: vertical;"></textarea>
                    </div>
                    
                    <!-- Current Poster Info -->
                    <div id="edit_current_poster_box" style="margin-bottom: 10px; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 12.5px;">
                        <div style="font-weight: bold; margin-bottom: 6px; color: #1B1F44;">Current Poster:</div>
                        <div id="edit_current_poster_content">
                            <!-- Populated via JS -->
                        </div>
                    </div>

                    <div style="background: #f8fafc; border: 1px dashed #94a3b8; border-radius: 6px; padding: 12px; margin-bottom: 8px;">
                        <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">🖼️ Replace / Upload New Poster (Optional)</label>
                        <input type="file" name="poster" id="edit_ev_poster" accept=".jpg,.jpeg,.png,.webp,.gif" onchange="validateAndPreviewPoster(this, 'edit_ev_poster_preview', 'edit_ev_poster_err')" style="width: 100%; font-size: 12.5px;">
                        <div style="font-size: 11.5px; color: #64748b; margin-top: 5px;">
                            Leave blank to retain current poster &bull; Allowed: JPG, PNG, WEBP, GIF (Max: 2 MB)
                        </div>
                        <div id="edit_ev_poster_err" style="color: #dc3545; font-size: 12px; font-weight: bold; margin-top: 6px; display: none;"></div>
                        <div id="edit_ev_poster_preview" style="margin-top: 10px; display: none;">
                            <img src="" alt="New Poster Preview" style="max-height: 140px; border-radius: 6px; border: 1px solid #cbd5e1; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
                        </div>
                    </div>
                </div>
                <div class="modal-footer-bar">
                    <button type="button" class="btn-export" onclick="closeAdminEditEventModal()">Cancel</button>
                    <button type="submit" class="btn-view-profile" style="background-color: #0284c7;">💾 Update Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 6. ADMIN DELETE EVENT MODAL -->
    <div class="admin-modal" id="adminDeleteEventModal" onclick="handleAdminBackdrop(event, 'adminDeleteEventModal')">
        <div class="admin-modal-card" style="max-width: 440px;">
            <div class="modal-header-bar" style="background-color: #dc3545; border-bottom: 3px solid #b02a37;">
                <h3>🗑️ Confirm Event Deletion</h3>
                <button type="button" class="modal-close-x" onclick="closeAdminDeleteEventModal()">&times;</button>
            </div>
            <form method="POST" action="admin_dashboard.php">
                <input type="hidden" name="event_action" value="delete">
                <input type="hidden" name="active_tab" value="tab-events">
                <input type="hidden" name="event_id" id="admin_delete_event_id" value="">
                <div class="modal-body-content" style="padding: 22px; text-align: center;">
                    <div style="font-size: 38px; margin-bottom: 10px;">⚠️</div>
                    <h4 style="margin: 0 0 8px; color: #1B1F44; font-size: 17px;">Permanently delete this event?</h4>
                    <p style="color: #475569; font-size: 13.5px; margin: 0 0 14px;">
                        <strong id="admin_delete_event_title_display">--</strong>
                    </p>
                    <div style="background-color: #fff3cd; color: #856404; font-size: 12px; padding: 10px; border-radius: 6px; border: 1px solid #ffeeba; text-align: left;">
                        Note: Deleting will remove this event, its uploaded poster file, and all student registrations for this event from MySQL.
                    </div>
                </div>
                <div class="modal-footer-bar" style="justify-content: center; gap: 12px;">
                    <button type="button" class="btn-export" onclick="closeAdminDeleteEventModal()">No, Cancel</button>
                    <button type="submit" class="btn-delete-student" style="padding: 8px 16px; font-size: 13px;">🗑️ Yes, Delete Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 7. ADMIN VIEW EVENT MODAL (DOSSIER & FULL POSTER) -->
    <div class="admin-modal" id="adminViewEventModal" onclick="handleAdminBackdrop(event, 'adminViewEventModal')">
        <div class="admin-modal-card" style="max-width: 580px;">
            <div class="modal-header-bar" style="background-color: #1B1F44; border-bottom: 3px solid orange;">
                <h3>👁️ Campus Event Details &amp; Poster</h3>
                <button type="button" class="modal-close-x" onclick="closeAdminViewEventModal()">&times;</button>
            </div>
            <div class="modal-body-content" style="max-height: 80vh; overflow-y: auto;">
                <div id="view_event_poster_box" style="text-align: center; margin-bottom: 15px; border-radius: 8px; overflow: hidden; background: #0f172a; max-height: 280px; display: flex; align-items: center; justify-content: center;">
                    <img id="view_event_poster_img" src="" alt="Event Poster" style="max-width: 100%; max-height: 280px; object-fit: contain;">
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <span class="pill-badge pill-ce" id="view_event_tag" style="font-size: 12px;">Category</span>
                    <span class="pill-badge" id="view_event_regs" style="background-color: #f1f5f9; color: #1B1F44; font-weight: bold;">👥 0 Registered</span>
                </div>
                <h3 id="view_event_title" style="margin: 0 0 10px; color: #1B1F44; font-size: 18px;">Event Title</h3>
                
                <div class="modal-detail-row">
                    <span class="modal-detail-label">📅 Date &amp; Time</span>
                    <span class="modal-detail-val" id="view_event_date">--</span>
                </div>
                <div class="modal-detail-row">
                    <span class="modal-detail-label">📍 Venue</span>
                    <span class="modal-detail-val" id="view_event_venue">--</span>
                </div>
                <div class="modal-detail-row">
                    <span class="modal-detail-label">🏢 Organizer</span>
                    <span class="modal-detail-val" id="view_event_organizer">--</span>
                </div>
                <div style="margin-top: 14px;">
                    <div style="font-size: 13px; font-weight: bold; color: #1B1F44; margin-bottom: 6px;">Description:</div>
                    <div id="view_event_desc" style="font-size: 13px; color: #475569; line-height: 1.5; background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0; white-space: pre-wrap;">--</div>
                </div>
            </div>
            <div class="modal-footer-bar">
                <button type="button" class="btn-export" onclick="closeAdminViewEventModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- HOME BUTTON AT BOTTOM RIGHT -->
    <a href="../index.php" class="home-btn">🏠 Home</a>

    <!-- FOOTER -->
    <footer>
        &copy; 2026 STUDENT HUB • CHARUSAT University • All Rights Reserved.
    </footer>

    <!-- INTERACTIVE JAVASCRIPT LOGIC -->
    <script>
        // 1. Session Inactivity Countdown Timer (15 minutes)
        var remainingSeconds = 900;
        var countdownElement = document.getElementById("countdownDisplay");
        var timerBadge = document.getElementById("sessionTimerBadge");

        var timerInterval = setInterval(function() {
            remainingSeconds--;
            if (remainingSeconds <= 0) {
                clearInterval(timerInterval);
                countdownElement.textContent = "00:00";
                window.location.href = "login.php?msg=timeout";
                return;
            }

            var mins = Math.floor(remainingSeconds / 60);
            var secs = remainingSeconds % 60;
            var formatted = (mins < 10 ? "0" + mins : mins) + ":" + (secs < 10 ? "0" + secs : secs);
            countdownElement.textContent = formatted;

            if (remainingSeconds <= 120) {
                timerBadge.style.backgroundColor = "#dc3545";
                timerBadge.style.color = "#ffffff";
            }
        }, 1000);

        // 2. View Switching via the 2 Metric Cards (Only One Control Per Detail)
        function switchTab(tabId) {
            var tabs = document.querySelectorAll('.tab-pane');
            tabs.forEach(function(t) { t.classList.remove('active'); });

            var cards = document.querySelectorAll('.admin-card');
            cards.forEach(function(c) { c.classList.remove('active-tab-card'); });

            var activePane = document.getElementById(tabId);
            if (activePane) activePane.classList.add('active');

            // Reset all card indicators to clickable state
            var indS = document.getElementById('indicatorStudents');
            var indE = document.getElementById('indicatorEvents');
            if (indS) indS.textContent = 'Click to View Students ➜';
            if (indE) indE.textContent = 'Click to View Events ➜';

            if (tabId === 'tab-students') {
                var cS = document.getElementById('cardStudents');
                if (cS) cS.classList.add('active-tab-card');
                if (indS) indS.textContent = 'Viewing Student Directory ▼';
            } else if (tabId === 'tab-events') {
                var cE = document.getElementById('cardEvents');
                if (cE) cE.classList.add('active-tab-card');
                if (indE) indE.textContent = 'Viewing Campus Events ▼';
            }
        }

        // 3. Instant Search & Department / Semester Filter
        function filterStudentTable() {
            var query = document.getElementById("studentSearchInput").value.toLowerCase().trim();
            var deptFilter = document.getElementById("deptFilterSelect").value;
            var semFilter  = document.getElementById("semFilterSelect") ? document.getElementById("semFilterSelect").value : "ALL";
            var rows = document.querySelectorAll(".student-row");
            var visibleCount = 0;

            rows.forEach(function(row) {
                var name   = row.getAttribute("data-name");
                var enroll = row.getAttribute("data-enroll");
                var email  = row.getAttribute("data-email");
                var dept   = row.getAttribute("data-dept");
                var sem    = row.getAttribute("data-semester");

                var matchesQuery = (name.indexOf(query) !== -1 || enroll.indexOf(query) !== -1 || email.indexOf(query) !== -1);
                var matchesDept  = (deptFilter === "ALL" || dept === deptFilter);
                var matchesSem   = (semFilter === "ALL" || sem === semFilter);

                if (matchesQuery && matchesDept && matchesSem) {
                    row.style.display = "";
                    visibleCount++;
                } else {
                    row.style.display = "none";
                }
            });

            document.getElementById("displayedCount").textContent = visibleCount;
            var noMatch = document.getElementById("noMatchMessage");
            if (visibleCount === 0) {
                noMatch.style.display = "block";
            } else {
                noMatch.style.display = "none";
            }
        }

        function resetFilters() {
            document.getElementById("studentSearchInput").value = "";
            document.getElementById("deptFilterSelect").value = "ALL";
            if (document.getElementById("semFilterSelect")) {
                document.getElementById("semFilterSelect").value = "ALL";
            }
            filterStudentTable();
        }

        // Student Management Modals
        function handleAdminBackdrop(e, id) {
            if (e.target.id === id) {
                document.getElementById(id).style.display = "none";
            }
        }

        function openAdminAddModal() {
            document.getElementById("admin_add_name").value = "";
            document.getElementById("admin_add_enroll").value = "";
            document.getElementById("admin_add_email").value = "";
            document.getElementById("admin_add_mobile").value = "";
            document.getElementById("admin_add_pass").value = "";
            document.getElementById("adminAddStudentModal").style.display = "flex";
        }

        function closeAdminAddModal() {
            document.getElementById("adminAddStudentModal").style.display = "none";
        }

        function openAdminEditModal(btn) {
            var row = btn.closest(".student-row");
            if (!row) return;

            var id     = row.getAttribute("data-id");
            var name   = row.getAttribute("data-rawname");
            var enroll = row.getAttribute("data-rawenroll");
            var email  = row.getAttribute("data-rawemail");
            var mobile = row.getAttribute("data-mobile");
            var dept   = row.getAttribute("data-dept");
            var sem    = row.getAttribute("data-semester");

            document.getElementById("admin_edit_id").value     = id;
            document.getElementById("admin_edit_name").value   = name;
            document.getElementById("admin_edit_enroll").value = enroll;
            document.getElementById("admin_edit_email").value  = email;
            document.getElementById("admin_edit_mobile").value = mobile;
            document.getElementById("admin_edit_dept").value   = dept;
            document.getElementById("admin_edit_sem").value    = sem;
            document.getElementById("admin_edit_pass").value   = "";

            document.getElementById("adminEditStudentModal").style.display = "flex";
        }

        function closeAdminEditModal() {
            document.getElementById("adminEditStudentModal").style.display = "none";
        }

        function openAdminDeleteModal(btn) {
            var row = btn.closest(".student-row");
            if (!row) return;

            var id     = row.getAttribute("data-id");
            var name   = row.getAttribute("data-rawname");
            var enroll = row.getAttribute("data-rawenroll");

            document.getElementById("admin_delete_id").value = id;
            document.getElementById("admin_delete_name").textContent = name;
            document.getElementById("admin_delete_enroll").textContent = enroll;

            document.getElementById("adminDeleteStudentModal").style.display = "flex";
        }

        function closeAdminDeleteModal() {
            document.getElementById("adminDeleteStudentModal").style.display = "none";
        }

        // 4. Student Dossier Modal
        var currentModalEmail = "";

        function openStudentModal(btn) {
            var row = btn.closest(".student-row");
            if (!row) return;

            var id       = row.getAttribute("data-id");
            var name     = row.querySelector(".user-name-title").textContent;
            var enroll   = row.getAttribute("data-enroll").toUpperCase();
            var email    = row.getAttribute("data-email");
            var mobile   = row.getAttribute("data-mobile") || "Not provided";
            var dept     = row.getAttribute("data-dept");
            var semester = row.getAttribute("data-semester");
            var created  = row.getAttribute("data-created");

            currentModalEmail = email;

            var parts = name.trim().split(" ");
            var inits = parts[0].charAt(0) + (parts.length > 1 ? parts[1].charAt(0) : "");

            document.getElementById("mAvatar").textContent = inits.toUpperCase();
            document.getElementById("mFullName").textContent = name;
            document.getElementById("mEnrollBadge").textContent = enroll;
            document.getElementById("mStudentId").textContent = "#" + id;
            document.getElementById("mEmail").textContent = email;
            document.getElementById("mMobile").textContent = mobile;
            document.getElementById("mDepartment").textContent = dept;
            document.getElementById("mSemester").textContent = semester;
            document.getElementById("mCreated").textContent = created;

            document.getElementById("studentDetailModal").style.display = "flex";
        }

        function closeStudentModal() {
            document.getElementById("studentDetailModal").style.display = "none";
        }

        function handleBackdropClick(e) {
            if (e.target.id === "studentDetailModal") {
                closeStudentModal();
            }
        }

        function sendMailToStudent() {
            if (currentModalEmail) {
                window.location.href = "mailto:" + currentModalEmail;
            }
        }

        // =====================================================================
        // EVENT MANAGEMENT MODALS & POSTER FILE VALIDATION
        // =====================================================================
        function validateAndPreviewPoster(input, previewId, errorId) {
            var file = input.files[0];
            var previewBox = document.getElementById(previewId);
            var errBox = document.getElementById(errorId);
            
            if (errBox) {
                errBox.textContent = '';
                errBox.style.display = 'none';
            }
            if (previewBox) {
                previewBox.style.display = 'none';
            }
            
            if (!file) return;

            // 1. File extension validation
            var allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            var fileName = file.name.toLowerCase();
            var ext = fileName.substring(fileName.lastIndexOf('.') + 1);

            if (allowedExts.indexOf(ext) === -1) {
                if (errBox) {
                    errBox.textContent = '⚠️ Invalid file type (.' + ext + '). Only JPG, JPEG, PNG, WEBP, and GIF images are allowed.';
                    errBox.style.display = 'block';
                }
                input.value = '';
                return false;
            }

            // 2. File size validation (Max 2 MB = 2 * 1024 * 1024 bytes)
            var maxSize = 2 * 1024 * 1024;
            if (file.size > maxSize) {
                var sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                if (errBox) {
                    errBox.textContent = '⚠️ File size (' + sizeMb + ' MB) exceeds the maximum allowed limit of 2 MB.';
                    errBox.style.display = 'block';
                }
                input.value = '';
                return false;
            }

            // 3. Client-side preview via FileReader
            if (previewBox) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var img = previewBox.querySelector('img');
                    if (img) img.src = e.target.result;
                    previewBox.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
            return true;
        }

        function openAdminAddEventModal() {
            var addPreview = document.getElementById('add_ev_poster_preview');
            if (addPreview) addPreview.style.display = 'none';
            var addErr = document.getElementById('add_ev_poster_err');
            if (addErr) addErr.style.display = 'none';
            var fileInp = document.getElementById('add_ev_poster');
            if (fileInp) fileInp.value = '';
            document.getElementById('adminAddEventModal').style.display = 'flex';
        }

        function closeAdminAddEventModal() {
            document.getElementById('adminAddEventModal').style.display = 'none';
        }

        function openAdminEditEventModal(ev) {
            document.getElementById('edit_ev_id').value = ev.event_id;
            document.getElementById('edit_ev_title').value = ev.title;
            document.getElementById('edit_ev_tag').value = ev.tag;
            document.getElementById('edit_ev_date').value = ev.event_date;
            document.getElementById('edit_ev_venue').value = ev.venue;
            document.getElementById('edit_ev_organizer').value = ev.organizer;
            document.getElementById('edit_ev_description').value = ev.description;
            
            var currentPosterContent = document.getElementById('edit_current_poster_content');
            if (ev.poster) {
                currentPosterContent.innerHTML = '<div style="display: flex; align-items: center; gap: 10px;">' +
                    '<img src="../' + ev.poster + '" style="height: 50px; width: 70px; object-fit: cover; border-radius: 4px; border: 1px solid #cbd5e1;">' +
                    '<div><code style="font-size: 11px;">' + ev.poster + '</code><br><span style="color: #16a34a; font-size: 11px; font-weight: bold;">✓ Current Poster Active</span></div>' +
                    '</div>';
            } else {
                currentPosterContent.innerHTML = '<span style="color: #64748b;">No poster currently attached to this event.</span>';
            }
            
            var editPreview = document.getElementById('edit_ev_poster_preview');
            if (editPreview) editPreview.style.display = 'none';
            var editErr = document.getElementById('edit_ev_poster_err');
            if (editErr) editErr.style.display = 'none';
            document.getElementById('edit_ev_poster').value = '';

            document.getElementById('adminEditEventModal').style.display = 'flex';
        }

        function closeAdminEditEventModal() {
            document.getElementById('adminEditEventModal').style.display = 'none';
        }

        function openAdminDeleteEventModal(id, title) {
            document.getElementById('admin_delete_event_id').value = id;
            document.getElementById('admin_delete_event_title_display').textContent = title;
            document.getElementById('adminDeleteEventModal').style.display = 'flex';
        }

        function closeAdminDeleteEventModal() {
            document.getElementById('adminDeleteEventModal').style.display = 'none';
        }

        function viewEventDetails(ev) {
            document.getElementById('view_event_title').textContent = ev.title;
            document.getElementById('view_event_tag').textContent = ev.tag;
            document.getElementById('view_event_regs').textContent = '👥 ' + (ev.reg_count || 0) + ' Registered';
            document.getElementById('view_event_date').textContent = ev.event_date;
            document.getElementById('view_event_venue').textContent = ev.venue;
            document.getElementById('view_event_organizer').textContent = ev.organizer;
            document.getElementById('view_event_desc').textContent = ev.description;

            var posterBox = document.getElementById('view_event_poster_box');
            var posterImg = document.getElementById('view_event_poster_img');
            if (ev.poster) {
                posterImg.src = '../' + ev.poster;
                posterBox.style.display = 'flex';
            } else {
                posterBox.style.display = 'none';
            }

            document.getElementById('adminViewEventModal').style.display = 'flex';
        }

        function closeAdminViewEventModal() {
            document.getElementById('adminViewEventModal').style.display = 'none';
        }

        function filterEventCards() {
            var query = document.getElementById('eventSearchInput').value.toLowerCase().trim();
            var tagFilter = document.getElementById('eventTagFilter').value;
            var cards = document.querySelectorAll('.event-admin-card');
            var visibleCount = 0;

            cards.forEach(function(card) {
                var title = card.getAttribute('data-title') || '';
                var venue = card.getAttribute('data-venue') || '';
                var organizer = card.getAttribute('data-organizer') || '';
                var tag = card.getAttribute('data-tag') || '';

                var matchesQuery = (title.indexOf(query) !== -1 || venue.indexOf(query) !== -1 || organizer.indexOf(query) !== -1);
                var matchesTag = (tagFilter === 'ALL' || tag === tagFilter);

                if (matchesQuery && matchesTag) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            var noMsg = document.getElementById('noEventsMessage');
            if (noMsg) {
                noMsg.style.display = (visibleCount === 0) ? 'block' : 'none';
            }
            var countEl = document.getElementById('displayedEventsCount');
            if (countEl) countEl.textContent = visibleCount;
        }

        function resetEventFilters() {
            var searchInp = document.getElementById('eventSearchInput');
            if (searchInp) searchInp.value = '';
            var tagFilter = document.getElementById('eventTagFilter');
            if (tagFilter) tagFilter.value = 'ALL';
            filterEventCards();
        }

        window.addEventListener("keydown", function(e) {
            if (e.key === "Escape") {
                closeStudentModal();
                closeAdminAddModal();
                closeAdminEditModal();
                closeAdminDeleteModal();
                closeAdminAddEventModal();
                closeAdminEditEventModal();
                closeAdminDeleteEventModal();
                closeAdminViewEventModal();
            }
        });

        // 5. CSV Export
        function exportStudentsToCSV() {
            var rows = document.querySelectorAll(".student-row");
            if (rows.length === 0) {
                alert("No student records available to export.");
                return;
            }

            var csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Student ID,Full Name,Enrollment No,Email,Mobile,Department,Semester,Registered Date\n";

            rows.forEach(function(row) {
                if (row.style.display !== "none") {
                    var id      = row.getAttribute("data-id");
                    var name    = '"' + row.querySelector(".user-name-title").textContent.replace(/"/g, '""') + '"';
                    var enroll  = '"' + row.getAttribute("data-enroll").toUpperCase() + '"';
                    var email   = '"' + row.getAttribute("data-email") + '"';
                    var mobile  = '"' + row.getAttribute("data-mobile") + '"';
                    var dept    = '"' + row.getAttribute("data-dept").replace(/"/g, '""') + '"';
                    var sem     = '"' + row.getAttribute("data-semester") + '"';
                    var created = '"' + row.getAttribute("data-created") + '"';

                    csvContent += [id, name, enroll, email, mobile, dept, sem, created].join(",") + "\n";
                }
            });

            var encodedUri = encodeURI(csvContent);
            var link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "studenthub_roster_" + new Date().toISOString().slice(0, 10) + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        try {
            localStorage.setItem('sh_logged_in', '1');
            localStorage.setItem('sh_role', 'admin');
        } catch(e) {}
    </script>
    <script src="../js/script.js"></script>
</body>
</html>
