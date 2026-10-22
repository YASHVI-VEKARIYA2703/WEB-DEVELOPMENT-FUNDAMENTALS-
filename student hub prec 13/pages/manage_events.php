<?php
// =============================================================================
// Practical 12: Event Management CRUD with Poster Upload and File Validation
// Problem Definition:
// Develop event management functionality for admin users. Admin can add,
// view, update, delete, and upload event posters with file type and file size
// validation. All database operations must use MySQLi prepared statements
// with clear success/failure feedback messages.
// =============================================================================

require_once __DIR__ . "/auth.php";

// Initialize session if not active
if (session_status() === PHP_SESSION_NONE) {
    init_session();
}

$isLoggedIn = is_logged_in();
$userRole   = isset($_SESSION['role']) ? $_SESSION['role'] : 'admin';
$userName   = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 'System Administrator';

$message     = "";
$messageType = ""; // 'success' or 'error'

// Helper function to validate and upload event poster
function process_poster_upload($fileField, &$error) {
    $error = "";
    if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // File upload is optional
    }

    $file = $_FILES[$fileField];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload error occurred (PHP Code: " . $file['error'] . ").";
        return false;
    }

    // 1. File Size Validation: Maximum 2 MB (2,097,152 bytes)
    $maxSize = 2 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        $mb = round($file['size'] / (1024 * 1024), 2);
        $error = "File size (" . $mb . " MB) exceeds the maximum allowed limit of 2 MB.";
        return false;
    }

    // 2. File Extension Validation (whitelist)
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $filename    = $file['name'];
    $ext         = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) {
        $error = "Invalid file type (." . htmlspecialchars($ext) . "). Only JPG, JPEG, PNG, WEBP, and GIF images are permitted.";
        return false;
    }

    // 3. MIME Type Validation
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
        $error = "Invalid image MIME type (" . htmlspecialchars($detectedMime) . "). File is not an allowed image.";
        return false;
    }

    // 4. Verify Image Content Integrity via getimagesize
    $imgDetails = @getimagesize($file['tmp_name']);
    if ($imgDetails === false) {
        $error = "Uploaded file content is corrupted or not a valid image.";
        return false;
    }

    // 5. Ensure upload directory exists
    $uploadDir = __DIR__ . "/../uploads/events/";
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    // 6. Generate secure collision-free unique filename
    $uniqueName = "event_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
    $targetPath = $uploadDir . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $error = "Failed to save uploaded poster to server storage directory.";
        return false;
    }

    return "uploads/events/" . $uniqueName;
}

// =============================================================================
// 1. HANDLE POST DATABASE OPERATIONS (MYSQLi PREPARED STATEMENTS)
// =============================================================================
if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    $action = $_POST["action"];

    // -------------------------------------------------------------------------
    // A. ADD NEW EVENT
    // -------------------------------------------------------------------------
    if ($action === "add") {
        $tag         = isset($_POST["tag"]) ? trim($_POST["tag"]) : "Technical Fest";
        $title       = isset($_POST["title"]) ? trim($_POST["title"]) : "";
        $eventDate   = isset($_POST["event_date"]) ? trim($_POST["event_date"]) : "";
        $venue       = isset($_POST["venue"]) ? trim($_POST["venue"]) : "";
        $organizer   = isset($_POST["organizer"]) ? trim($_POST["organizer"]) : "";
        $description = isset($_POST["description"]) ? trim($_POST["description"]) : "";

        // Backend Validations
        if (empty($title) || empty($tag) || empty($eventDate) || empty($venue) || empty($organizer) || empty($description)) {
            $message     = "Failed to add event: All fields (Title, Category, Date, Venue, Organizer, Description) are required.";
            $messageType = "error";
        } else {
            $uploadError = "";
            $posterPath  = process_poster_upload("poster", $uploadError);

            if ($posterPath === false) {
                $message     = "Failed to add event: " . $uploadError;
                $messageType = "error";
            } else {
                $insertSql = "INSERT INTO events (tag, title, event_date, venue, organizer, description, poster) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $insertSql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "sssssss", $tag, $title, $eventDate, $venue, $organizer, $description, $posterPath);
                    if (mysqli_stmt_execute($stmt)) {
                        $newId       = mysqli_insert_id($conn);
                        $message     = "Success! Event #$newId ('$title') was successfully added to MySQL" . ($posterPath ? " with uploaded poster." : ".");
                        $messageType = "success";
                    } else {
                        $message     = "Database Failure: Unable to insert event (" . mysqli_error($conn) . ").";
                        $messageType = "error";
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $message     = "Database Preparation Error: " . mysqli_error($conn);
                    $messageType = "error";
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // B. EDIT EXISTING EVENT
    // -------------------------------------------------------------------------
    elseif ($action === "edit") {
        $eventId     = isset($_POST["event_id"]) ? intval($_POST["event_id"]) : 0;
        $tag         = isset($_POST["tag"]) ? trim($_POST["tag"]) : "";
        $title       = isset($_POST["title"]) ? trim($_POST["title"]) : "";
        $eventDate   = isset($_POST["event_date"]) ? trim($_POST["event_date"]) : "";
        $venue       = isset($_POST["venue"]) ? trim($_POST["venue"]) : "";
        $organizer   = isset($_POST["organizer"]) ? trim($_POST["organizer"]) : "";
        $description = isset($_POST["description"]) ? trim($_POST["description"]) : "";

        if ($eventId <= 0) {
            $message     = "Failed to edit: Invalid event ID.";
            $messageType = "error";
        } elseif (empty($title) || empty($tag) || empty($eventDate) || empty($venue) || empty($organizer) || empty($description)) {
            $message     = "Failed to edit: All fields must be completed.";
            $messageType = "error";
        } else {
            // Verify event exists and fetch existing poster
            $checkSql = "SELECT event_id, poster FROM events WHERE event_id = ? LIMIT 1";
            $checkStmt = mysqli_prepare($conn, $checkSql);
            mysqli_stmt_bind_param($checkStmt, "i", $eventId);
            mysqli_stmt_execute($checkStmt);
            $existingEv = mysqli_fetch_assoc(mysqli_stmt_get_result($checkStmt));
            mysqli_stmt_close($checkStmt);

            if (!$existingEv) {
                $message     = "Failed to edit: Event #$eventId not found in MySQL.";
                $messageType = "error";
            } else {
                $uploadError = "";
                $newPosterPath = process_poster_upload("poster", $uploadError);

                if ($newPosterPath === false) {
                    $message     = "Failed to update event: " . $uploadError;
                    $messageType = "error";
                } else {
                    if ($newPosterPath !== null) {
                        // Delete old poster file if on disk
                        if (!empty($existingEv['poster']) && file_exists(__DIR__ . "/../" . $existingEv['poster'])) {
                            @unlink(__DIR__ . "/../" . $existingEv['poster']);
                        }
                        $updateSql = "UPDATE events SET tag = ?, title = ?, event_date = ?, venue = ?, organizer = ?, description = ?, poster = ? WHERE event_id = ?";
                        $uStmt = mysqli_prepare($conn, $updateSql);
                        mysqli_stmt_bind_param($uStmt, "sssssssi", $tag, $title, $eventDate, $venue, $organizer, $description, $newPosterPath, $eventId);
                    } else {
                        $updateSql = "UPDATE events SET tag = ?, title = ?, event_date = ?, venue = ?, organizer = ?, description = ? WHERE event_id = ?";
                        $uStmt = mysqli_prepare($conn, $updateSql);
                        mysqli_stmt_bind_param($uStmt, "ssssssi", $tag, $title, $eventDate, $venue, $organizer, $description, $eventId);
                    }

                    if ($uStmt && mysqli_stmt_execute($uStmt)) {
                        $message     = "Success! Event #$eventId ('$title') was successfully updated" . ($newPosterPath ? " with new poster." : ".");
                        $messageType = "success";
                    } else {
                        $message     = "Database Update Error: " . mysqli_error($conn);
                        $messageType = "error";
                    }
                    if ($uStmt) mysqli_stmt_close($uStmt);
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // C. DELETE EVENT
    // -------------------------------------------------------------------------
    elseif ($action === "delete") {
        $eventId = isset($_POST["event_id"]) ? intval($_POST["event_id"]) : 0;
        if ($eventId <= 0) {
            $message     = "Failed to delete: Invalid event ID.";
            $messageType = "error";
        } else {
            $fetchSql = "SELECT event_id, title, poster FROM events WHERE event_id = ? LIMIT 1";
            $fStmt = mysqli_prepare($conn, $fetchSql);
            mysqli_stmt_bind_param($fStmt, "i", $eventId);
            mysqli_stmt_execute($fStmt);
            $target = mysqli_fetch_assoc(mysqli_stmt_get_result($fStmt));
            mysqli_stmt_close($fStmt);

            if (!$target) {
                $message     = "Failed to delete: Event #$eventId does not exist.";
                $messageType = "error";
            } else {
                if (!empty($target['poster']) && file_exists(__DIR__ . "/../" . $target['poster'])) {
                    @unlink(__DIR__ . "/../" . $target['poster']);
                }
                $delSql = "DELETE FROM events WHERE event_id = ?";
                $dStmt = mysqli_prepare($conn, $delSql);
                mysqli_stmt_bind_param($dStmt, "i", $eventId);
                if (mysqli_stmt_execute($dStmt)) {
                    $message     = "Success! Event #$eventId ('" . htmlspecialchars($target['title']) . "') and associated registrations were removed.";
                    $messageType = "success";
                } else {
                    $message     = "Database Deletion Error: " . mysqli_error($conn);
                    $messageType = "error";
                }
                mysqli_stmt_close($dStmt);
            }
        }
    }
}

// =============================================================================
// 2. FETCH EVENTS AND STATS FROM MYSQL DATABASE
// =============================================================================
$searchTerm     = isset($_GET['search']) ? trim($_GET['search']) : "";
$filterCategory = isset($_GET['category']) ? trim($_GET['category']) : "ALL";

// Base query for all events with registrations
$allEvents = [];
$totalEventsCount = 0;
$totalRegistrations = 0;
$eventsWithPosterCount = 0;
$allCategories = [];

$query = "SELECT e.*, COUNT(r.registration_id) as reg_count 
          FROM events e 
          LEFT JOIN registrations r ON e.event_id = r.event_id 
          GROUP BY e.event_id 
          ORDER BY e.event_id DESC";
$res = mysqli_query($conn, $query);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $allEvents[] = $row;
        $totalEventsCount++;
        $totalRegistrations += intval($row['reg_count']);
        if (!empty($row['poster']) && file_exists(__DIR__ . "/../" . $row['poster'])) {
            $eventsWithPosterCount++;
        }
        if (!in_array($row['tag'], $allCategories)) {
            $allCategories[] = $row['tag'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management - Student Hub</title>
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
        /* =====================================================================
           EVENT MANAGEMENT MODULE - DEDICATED STYLES
           ===================================================================== */
        body {
            background-color: #f1f5f9;
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
        }

        .management-container {
            width: 92%;
            max-width: 1250px;
            margin: 25px auto 50px;
        }

        /* Top Meta Banner */
        .top-meta-banner {
            background: linear-gradient(135deg, #1B1F44 0%, #29306b 100%);
            color: white;
            padding: 22px 28px;
            border-radius: 8px 8px 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
            border-bottom: 3px solid orange;
        }

        .meta-title-box h2 {
            margin: 0 0 6px 0;
            font-size: 21px;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .meta-title-box p {
            margin: 0;
            color: #cbd5e1;
            font-size: 13.5px;
        }

        .top-actions-cluster {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-add-primary {
            background-color: orange;
            color: #1B1F44;
            border: none;
            padding: 10px 18px;
            font-size: 13.5px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-add-primary:hover {
            background-color: #e68a00;
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(0, 0, 0, 0.25);
        }

        /* Metric Summary Grid */
        .metrics-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin: 18px 0;
        }

        .metric-mini-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
            border-left: 4px solid #1B1F44;
            transition: 0.2s;
        }

        .metric-mini-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        }

        .metric-mini-card.accent-orange { border-left-color: orange; }
        .metric-mini-card.accent-green  { border-left-color: #16a34a; }
        .metric-mini-card.accent-blue   { border-left-color: #0284c7; }

        .metric-icon-wrap {
            font-size: 26px;
            line-height: 1;
        }

        .metric-title-sub {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.4px;
        }

        .metric-num-big {
            font-size: 22px;
            font-weight: bold;
            color: #1B1F44;
            margin-top: 2px;
        }

        /* Toolbar */
        .management-toolbar {
            background: white;
            padding: 14px 20px;
            border-left: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-input-wrapper {
            position: relative;
            flex: 1;
            min-width: 260px;
        }

        .search-input-wrapper input {
            width: 100%;
            padding: 9px 12px 9px 36px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
            outline: none;
            box-sizing: border-box;
            transition: 0.2s;
        }

        .search-input-wrapper input:focus {
            border-color: orange;
            box-shadow: 0 0 0 3px rgba(255, 165, 0, 0.15);
        }

        .search-icon-symbol {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
        }

        .filter-group-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-select-input {
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
            background-color: white;
            color: #1e293b;
            outline: none;
            cursor: pointer;
        }

        .filter-select-input:focus {
            border-color: orange;
        }

        .btn-reset-filters, .btn-csv-export, .btn-view-toggle {
            padding: 9px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-reset-filters {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #475569;
        }

        .btn-reset-filters:hover {
            background-color: #e2e8f0;
            color: #1B1F44;
        }

        .btn-csv-export {
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            color: #166534;
        }

        .btn-csv-export:hover {
            background-color: #166534;
            color: white;
        }

        .btn-view-toggle {
            border: 1px solid #cbd5e1;
            background: white;
            color: #1B1F44;
        }

        .btn-view-toggle.active {
            background: #1B1F44;
            color: white;
            border-color: #1B1F44;
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

        .events-styled-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            text-align: left;
        }

        .events-styled-table th {
            background-color: #1B1F44;
            color: white;
            padding: 12px 14px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            border: none;
        }

        .events-styled-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }

        .events-styled-table tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Table poster thumbnail */
        .table-poster-thumb {
            width: 64px;
            height: 48px;
            border-radius: 4px;
            object-fit: cover;
            border: 1px solid #cbd5e1;
            background: #f1f5f9;
            display: block;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .table-poster-thumb:hover {
            transform: scale(1.1);
        }

        .table-poster-placeholder {
            width: 64px;
            height: 48px;
            border-radius: 4px;
            background: #f1f5f9;
            border: 1px dashed #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #94a3b8;
        }

        /* Badges */
        .pill-badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 12px;
            font-size: 11.5px;
            font-weight: bold;
        }

        .pill-tech   { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .pill-cult   { background-color: #fce7f3; color: #be185d; border: 1px solid #fbcfe8; }
        .pill-work   { background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .pill-sport  { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .pill-acad   { background-color: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe; }

        /* Action Buttons */
        .action-btns-group {
            display: flex;
            align-items: center;
            gap: 6px;
            justify-content: center;
        }

        .btn-act {
            border: none;
            padding: 6px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-act-view { background-color: #1B1F44; color: white; }
        .btn-act-view:hover { background-color: orange; color: #1B1F44; transform: scale(1.05); }

        .btn-act-edit { background-color: #0284c7; color: white; }
        .btn-act-edit:hover { background-color: #0369a1; transform: scale(1.05); }

        .btn-act-del { background-color: #dc3545; color: white; }
        .btn-act-del:hover { background-color: #b02a37; transform: scale(1.05); }

        /* Grid Cards View */
        .events-grid-view {
            display: none;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 18px;
            padding: 20px;
            background: white;
            border: 1px solid #e2e8f0;
            border-top: none;
            border-radius: 0 0 8px 8px;
        }

        .event-card-item {
            background: white;
            border-radius: 8px;
            padding: 16px;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #1B1F44;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            transition: all 0.25s ease;
        }

        .event-card-item:hover {
            transform: translateY(-3px);
            border-left-color: orange;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.08);
        }

        .card-poster-wrap {
            width: 100%;
            height: 140px;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 12px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-poster-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Alerts */
        .alert-box {
            padding: 13px 18px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 13.5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .alert-box.success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
            border-left: 5px solid #22c55e;
        }

        .alert-box.error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-left: 5px solid #ef4444;
        }

        .alert-close-btn {
            background: none;
            border: none;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            color: inherit;
        }

        /* Modals */
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

        .modal-container {
            background-color: white;
            border-radius: 10px;
            width: 90%;
            max-width: 560px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: modalPop 0.25s ease-out;
            border-top: 5px solid orange;
        }

        @keyframes modalPop {
            from { opacity: 0; transform: translateY(-20px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-head {
            background-color: #1B1F44;
            color: white;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-head h3 { margin: 0; font-size: 17px; }

        .modal-close-cross {
            background: transparent;
            border: none;
            color: white;
            font-size: 22px;
            cursor: pointer;
            font-weight: bold;
            line-height: 1;
        }

        .modal-close-cross:hover { color: orange; }

        .modal-content-body {
            padding: 20px 24px;
            max-height: 75vh;
            overflow-y: auto;
        }

        .modal-foot {
            background-color: #f8fafc;
            padding: 12px 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid #e2e8f0;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .metrics-summary-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 600px) {
            .metrics-summary-grid { grid-template-columns: 1fr; }
            .top-meta-banner { flex-direction: column; gap: 12px; text-align: center; }
        }
    </style>
</head>
<body>

    <!-- HEADER IFRAME -->
    <iframe
        src="header.html"
        class="header-frame">
    </iframe>

    <h1 class="page-title">CAMPUS EVENT MANAGEMENT MODULE</h1>

    <!-- NAVIGATION -->
    <nav>
        <?php if ($isLoggedIn): ?>
            <?php if ($userRole === 'admin'): ?>
                <a href="admin_dashboard.php">ADMIN PANEL</a>
            <?php endif; ?>
            <a href="manage_students.php">MANAGE STUDENTS</a>
            <a href="manage_events.php" class="active" style="background-color: orange; color: #1B1F44; font-weight: bold;">MANAGE EVENTS</a>
            <a href="dashboard.php">DASHBOARD</a>
            <a href="view_records.php">ALL RECORDS</a>
            <a href="event.html">EVENTS</a>
            <a href="course.html">COURSES</a>
            <a href="contact.html">CONTACT</a>
            <a href="logout.php" style="background-color: #dc3545; color: white;" title="End session and log out">LOGOUT 🚪</a>
        <?php else: ?>
            <a href="dashboard.php">DASHBOARD</a>
            <a href="manage_students.php">MANAGE STUDENTS</a>
            <a href="manage_events.php" class="active" style="background-color: orange; color: #1B1F44; font-weight: bold;">MANAGE EVENTS</a>
            <a href="event.html">EVENTS</a>
            <a href="course.html">COURSES</a>
            <a href="register.php">REGISTER</a>
            <a href="login.php">LOGIN</a>
            <a href="contact.html">CONTACT</a>
        <?php endif; ?>
    </nav>

    <!-- MAIN CONTAINER -->
    <div class="management-container">

        <!-- SUCCESS / FAILURE MESSAGES (MYSQLi / FETCH API FEEDBACK) -->
        <div class="alert-box <?= !empty($message) ? htmlspecialchars($messageType) : '' ?>" id="feedbackAlert" style="<?= empty($message) ? 'display: none;' : '' ?>">
            <div id="feedbackAlertText">
                <?php if (!empty($message)): ?>
                    <?php if ($messageType === 'success'): ?>
                        <strong>✓ Success:</strong> <?= htmlspecialchars($message) ?>
                    <?php else: ?>
                        <strong>⚠️ Notice:</strong> <?= htmlspecialchars($message) ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <button type="button" class="alert-close-btn" onclick="document.getElementById('feedbackAlert').style.display='none'">&times;</button>
        </div>

        <!-- TOP STATUS / META STRIP -->
        <div class="top-meta-banner">
            <div class="meta-title-box">
                <h2>
                    🏆 Campus Events Directory
                    <span class="pill-badge" style="background-color: #10b981; color: white; font-size: 11px; margin-left: 8px;">
                        ● MySQLi Operations Active
                    </span>
                </h2>
                <p>
                    Manage, view, update, delete campus events, and upload verified posters with file type and file size validation.
                </p>
            </div>
            <div class="top-actions-cluster">
                <button type="button" class="btn-add-primary" onclick="openAddEventModal()">
                    ➕ Add New Event
                </button>
            </div>
        </div>

        <!-- 4 SUMMARY METRIC CARDS -->
        <div class="metrics-summary-grid">
            <div class="metric-mini-card">
                <div class="metric-icon-wrap">🏆</div>
                <div>
                    <div class="metric-title-sub">Total Events</div>
                    <div class="metric-num-big" id="metricTotalEvents"><?= intval($totalEventsCount) ?></div>
                </div>
            </div>

            <div class="metric-mini-card accent-orange">
                <div class="metric-icon-wrap">👥</div>
                <div>
                    <div class="metric-title-sub">Total Registrations</div>
                    <div class="metric-num-big"><?= intval($totalRegistrations) ?></div>
                </div>
            </div>

            <div class="metric-mini-card accent-green">
                <div class="metric-icon-wrap">🖼️</div>
                <div>
                    <div class="metric-title-sub">With Posters</div>
                    <div class="metric-num-big"><?= intval($eventsWithPosterCount) ?></div>
                </div>
            </div>

            <div class="metric-mini-card accent-blue">
                <div class="metric-icon-wrap">🏷️</div>
                <div>
                    <div class="metric-title-sub">Categories</div>
                    <div class="metric-num-big"><?= count($allCategories) ?></div>
                </div>
            </div>
        </div>

        <!-- TOOLBAR -->
        <div class="management-toolbar">
            <div class="search-input-wrapper">
                <span class="search-icon-symbol">🔍</span>
                <input type="text" id="liveEventSearch" placeholder="Search events by title, venue, organizer..." onkeyup="filterEventsRealtime()">
            </div>

            <div class="filter-group-wrapper">
                <select class="filter-select-input" id="categoryFilter" onchange="filterEventsRealtime()">
                    <option value="ALL">All Categories (<?= intval($totalEventsCount) ?>)</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="filter-select-input" id="posterFilter" onchange="filterEventsRealtime()">
                    <option value="ALL">All Poster Statuses</option>
                    <option value="WITH">With Poster</option>
                    <option value="WITHOUT">No Poster</option>
                </select>

                <button type="button" class="btn-reset-filters" onclick="resetFilters()" title="Reset filters">
                    🔄 Reset
                </button>

                <button type="button" class="btn-csv-export" onclick="exportEventsToCSV()" title="Export events to CSV">
                    📥 Export CSV
                </button>

                <div style="display: flex; gap: 4px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 2px;">
                    <button type="button" class="btn-view-toggle active" id="btnViewTable" onclick="toggleView('table')" title="Table View">📑 Table</button>
                    <button type="button" class="btn-view-toggle" id="btnViewGrid" onclick="toggleView('grid')" title="Grid View">🗂️ Cards</button>
                </div>
            </div>
        </div>

        <!-- VIEW 1: DATA TABLE VIEW -->
        <div class="records-table-card" id="tableViewContainer">
            <table class="events-styled-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#ID</th>
                        <th style="width: 80px;">Poster</th>
                        <th>Event Title &amp; Details</th>
                        <th>Category</th>
                        <th>Date &amp; Venue</th>
                        <th>Organizer</th>
                        <th style="text-align: center;">Registrations</th>
                        <th style="text-align: center; width: 170px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="eventsTableBody">
                    <?php if (!empty($allEvents)): ?>
                        <?php foreach ($allEvents as $ev): 
                            $hasPoster = !empty($ev['poster']) && file_exists(__DIR__ . "/../" . $ev['poster']);
                            $catClass = 'pill-tech';
                            if (stripos($ev['tag'], 'cultural') !== false) $catClass = 'pill-cult';
                            elseif (stripos($ev['tag'], 'workshop') !== false) $catClass = 'pill-work';
                            elseif (stripos($ev['tag'], 'sport') !== false) $catClass = 'pill-sport';
                            elseif (stripos($ev['tag'], 'academic') !== false) $catClass = 'pill-acad';
                        ?>
                            <tr class="event-data-row"
                                data-id="<?= intval($ev['event_id']) ?>"
                                data-title="<?= htmlspecialchars(strtolower($ev['title'])) ?>"
                                data-tag="<?= htmlspecialchars($ev['tag']) ?>"
                                data-venue="<?= htmlspecialchars(strtolower($ev['venue'])) ?>"
                                data-organizer="<?= htmlspecialchars(strtolower($ev['organizer'])) ?>"
                                data-hasposter="<?= $hasPoster ? '1' : '0' ?>">
                                <td style="font-weight: bold; color: #64748b;">#<?= intval($ev['event_id']) ?></td>
                                <td>
                                    <?php if ($hasPoster): ?>
                                        <img src="../<?= htmlspecialchars($ev['poster']) ?>" alt="Poster" class="table-poster-thumb" onclick='viewEventDossier(<?= json_encode($ev, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Click to view poster">
                                    <?php else: ?>
                                        <div class="table-poster-placeholder" title="No poster uploaded">🖼️</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: bold; color: #1B1F44; font-size: 14px; margin-bottom: 2px;">
                                        <?= htmlspecialchars($ev['title']) ?>
                                    </div>
                                    <div style="font-size: 12px; color: #64748b; max-width: 320px; white-space: normal; line-height: 1.3;">
                                        <?= htmlspecialchars(mb_strimwidth($ev['description'] ?? '', 0, 85, '...')) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="pill-badge <?= $catClass ?>"><?= htmlspecialchars($ev['tag']) ?></span>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($ev['event_date']) ?></div>
                                    <div style="font-size: 12px; color: #64748b;">📍 <?= htmlspecialchars($ev['venue']) ?></div>
                                </td>
                                <td>
                                    <div style="font-size: 13px; color: #334155;">🏢 <?= htmlspecialchars($ev['organizer']) ?></div>
                                </td>
                                <td style="text-align: center;">
                                    <span class="pill-badge" style="background: #f1f5f9; color: #1B1F44;">
                                        👥 <?= intval($ev['reg_count']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns-group">
                                        <button type="button" class="btn-act btn-act-view" onclick='viewEventDossier(<?= json_encode($ev, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View full details and poster">👁️ View</button>
                                        <button type="button" class="btn-act btn-act-edit" onclick='openEditEventModal(<?= json_encode($ev, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit event and update poster">✏️ Edit</button>
                                        <button type="button" class="btn-act btn-act-del" onclick="openDeleteEventModal(<?= intval($ev['event_id']) ?>, '<?= htmlspecialchars(addslashes($ev['title'])) ?>')" title="Delete event">🗑️</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #64748b;">
                                No campus events found in database. Click <b>"➕ Add New Event"</b> to create one.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div id="noEventRowsMessage" style="display: none; text-align: center; padding: 35px; color: #64748b;">
                🔍 No events matched your search / filter criteria.
            </div>
            
            <div style="padding: 12px 18px; font-size: 12.5px; color: #64748b; display: flex; justify-content: space-between; border-top: 1px solid #f1f5f9;">
                <span>Displaying <b id="displayEventsCount"><?= count($allEvents) ?></b> of <?= count($allEvents) ?> events</span>
                <span>Live from MySQL <code>events</code></span>
            </div>
        </div>

        <!-- VIEW 2: CARDS GRID VIEW -->
        <div class="events-grid-view" id="gridViewContainer">
            <?php foreach ($allEvents as $ev): 
                $hasPoster = !empty($ev['poster']) && file_exists(__DIR__ . "/../" . $ev['poster']);
            ?>
                <div class="event-card-item event-data-grid-item"
                     data-id="<?= intval($ev['event_id']) ?>"
                     data-title="<?= htmlspecialchars(strtolower($ev['title'])) ?>"
                     data-tag="<?= htmlspecialchars($ev['tag']) ?>"
                     data-venue="<?= htmlspecialchars(strtolower($ev['venue'])) ?>"
                     data-organizer="<?= htmlspecialchars(strtolower($ev['organizer'])) ?>"
                     data-hasposter="<?= $hasPoster ? '1' : '0' ?>">
                    
                    <div class="card-poster-wrap">
                        <?php if ($hasPoster): ?>
                            <img src="../<?= htmlspecialchars($ev['poster']) ?>" alt="<?= htmlspecialchars($ev['title']) ?> Poster">
                        <?php else: ?>
                            <span style="color: #94a3b8; font-size: 13px;">🎨 No Poster Uploaded</span>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <span class="pill-badge pill-tech"><?= htmlspecialchars($ev['tag']) ?></span>
                        <span class="pill-badge" style="background: #f1f5f9; color: #1B1F44;">👥 <?= intval($ev['reg_count']) ?> Regs</span>
                    </div>

                    <h4 style="margin: 0 0 6px; color: #1B1F44; font-size: 15px;"><?= htmlspecialchars($ev['title']) ?></h4>
                    <p style="margin: 0 0 6px; font-size: 12.5px; color: #64748b;">
                        📅 <b>Date:</b> <?= htmlspecialchars($ev['event_date']) ?> &bull; 📍 <b>Venue:</b> <?= htmlspecialchars($ev['venue']) ?>
                    </p>
                    <p style="margin: 0 0 8px; font-size: 12px; color: #475569;">
                        🏢 <b>Organizer:</b> <?= htmlspecialchars($ev['organizer']) ?>
                    </p>
                    <p style="margin: 0 0 12px; font-size: 12px; color: #64748b; line-height: 1.4;">
                        <?= htmlspecialchars(mb_strimwidth($ev['description'] ?? '', 0, 110, '...')) ?>
                    </p>

                    <div style="display: flex; gap: 6px; border-top: 1px solid #f1f5f9; padding-top: 10px;">
                        <button type="button" class="btn-act btn-act-view" style="flex: 1; justify-content: center;" onclick='viewEventDossier(<?= json_encode($ev, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>👁️ View</button>
                        <button type="button" class="btn-act btn-act-edit" style="flex: 1; justify-content: center;" onclick='openEditEventModal(<?= json_encode($ev, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>✏️ Edit</button>
                        <button type="button" class="btn-act btn-act-del" style="flex: 1; justify-content: center;" onclick="openDeleteEventModal(<?= intval($ev['event_id']) ?>, '<?= htmlspecialchars(addslashes($ev['title'])) ?>')">🗑️ Del</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- =========================================================================
         MODALS: ADD, EDIT, DELETE & VIEW EVENT
         ========================================================================= -->

    <!-- 1. ADD EVENT MODAL (POSTER UPLOAD & VALIDATION) -->
    <div class="modal-overlay" id="addEventModal" onclick="handleModalBackdrop(event, 'addEventModal')">
        <div class="modal-container">
            <div class="modal-head">
                <h3>➕ Add New Campus Event</h3>
                <button type="button" class="modal-close-cross" onclick="closeAddEventModal()">&times;</button>
            </div>
            <form id="addEventForm" method="POST" action="manage_events.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="modal-content-body">
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Event Title *</label>
                        <input type="text" name="title" id="m_add_title" required placeholder="e.g., CodeStorm 2026 Hackathon" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Category / Tag *</label>
                            <select name="tag" id="m_add_tag" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                                <option value="Technical Fest">Technical Fest</option>
                                <option value="Cultural Fest">Cultural Fest</option>
                                <option value="Workshop">Workshop</option>
                                <option value="Sports Meet">Sports Meet</option>
                                <option value="Academic">Academic</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Event Date *</label>
                            <input type="text" name="event_date" id="m_add_date" required placeholder="e.g., October 20 - 22, 2026" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Venue / Location *</label>
                            <input type="text" name="venue" id="m_add_venue" required placeholder="e.g., Central Auditorium" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Organizer *</label>
                            <input type="text" name="organizer" id="m_add_organizer" required placeholder="e.g., Computer Engineering Dept" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        </div>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Description *</label>
                        <textarea name="description" id="m_add_desc" rows="3" required placeholder="Event agenda, requirements, and rules..." style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box; resize: vertical;"></textarea>
                    </div>

                    <!-- Poster File Upload & Validation Section -->
                    <div style="background: #f8fafc; border: 1px dashed #94a3b8; border-radius: 6px; padding: 12px;">
                        <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">🖼️ Event Poster (Optional)</label>
                        <input type="file" name="poster" id="m_add_poster_input" accept=".jpg,.jpeg,.png,.webp,.gif" onchange="validateAndPreview(this, 'm_add_preview_box', 'm_add_err_box')" style="width: 100%; font-size: 12.5px;">
                        <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">
                            ✓ <b>Allowed Types:</b> JPG, JPEG, PNG, WEBP, GIF &bull; <b>Max Size:</b> 2 MB
                        </div>
                        <div id="m_add_err_box" style="color: #dc3545; font-size: 12px; font-weight: bold; margin-top: 6px; display: none;"></div>
                        <div id="m_add_preview_box" style="margin-top: 10px; display: none;">
                            <img src="" alt="Poster Preview" style="max-height: 140px; border-radius: 6px; border: 1px solid #cbd5e1; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
                        </div>
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn-reset-filters" onclick="closeAddEventModal()">Cancel</button>
                    <button type="submit" class="btn-add-primary" style="background-color: orange; color: #1B1F44;">➕ Add Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. EDIT EVENT MODAL (POSTER REPLACE & VALIDATION) -->
    <div class="modal-overlay" id="editEventModal" onclick="handleModalBackdrop(event, 'editEventModal')">
        <div class="modal-container">
            <div class="modal-head" style="background-color: #0284c7; border-bottom: 3px solid #0369a1;">
                <h3>✏️ Edit Campus Event</h3>
                <button type="button" class="modal-close-cross" onclick="closeEditEventModal()">&times;</button>
            </div>
            <form id="editEventForm" method="POST" action="manage_events.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="event_id" id="m_edit_id" value="">
                <div class="modal-content-body">
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Event Title *</label>
                        <input type="text" name="title" id="m_edit_title" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Category / Tag *</label>
                            <select name="tag" id="m_edit_tag" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                                <option value="Technical Fest">Technical Fest</option>
                                <option value="Cultural Fest">Cultural Fest</option>
                                <option value="Workshop">Workshop</option>
                                <option value="Sports Meet">Sports Meet</option>
                                <option value="Academic">Academic</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Event Date *</label>
                            <input type="text" name="event_date" id="m_edit_date" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Venue / Location *</label>
                            <input type="text" name="venue" id="m_edit_venue" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Organizer *</label>
                            <input type="text" name="organizer" id="m_edit_organizer" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box;">
                        </div>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">Description *</label>
                        <textarea name="description" id="m_edit_desc" rows="3" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box; resize: vertical;"></textarea>
                    </div>

                    <!-- Current Poster Status -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; margin-bottom: 10px; font-size: 12.5px;">
                        <div style="font-weight: bold; color: #1B1F44; margin-bottom: 4px;">Current Poster:</div>
                        <div id="m_edit_current_poster_box"></div>
                    </div>

                    <!-- Replace Poster Input -->
                    <div style="background: #f8fafc; border: 1px dashed #94a3b8; border-radius: 6px; padding: 12px;">
                        <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; color: #1B1F44;">🖼️ Replace Poster (Optional)</label>
                        <input type="file" name="poster" id="m_edit_poster_input" accept=".jpg,.jpeg,.png,.webp,.gif" onchange="validateAndPreview(this, 'm_edit_preview_box', 'm_edit_err_box')" style="width: 100%; font-size: 12.5px;">
                        <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">
                            Leave blank to retain current poster &bull; Allowed: JPG, PNG, WEBP, GIF (Max: 2 MB)
                        </div>
                        <div id="m_edit_err_box" style="color: #dc3545; font-size: 12px; font-weight: bold; margin-top: 6px; display: none;"></div>
                        <div id="m_edit_preview_box" style="margin-top: 10px; display: none;">
                            <img src="" alt="New Poster Preview" style="max-height: 140px; border-radius: 6px; border: 1px solid #cbd5e1; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
                        </div>
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn-reset-filters" onclick="closeEditEventModal()">Cancel</button>
                    <button type="submit" class="btn-act-edit" style="padding: 9px 16px; font-size: 13px;">💾 Update Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. DELETE EVENT MODAL -->
    <div class="modal-overlay" id="deleteEventModal" onclick="handleModalBackdrop(event, 'deleteEventModal')">
        <div class="modal-container" style="max-width: 440px;">
            <div class="modal-head" style="background-color: #dc3545; border-bottom: 3px solid #b02a37;">
                <h3>🗑️ Confirm Event Deletion</h3>
                <button type="button" class="modal-close-cross" onclick="closeDeleteEventModal()">&times;</button>
            </div>
            <form id="deleteEventForm" method="POST" action="manage_events.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="event_id" id="m_del_id" value="">
                <div class="modal-content-body" style="text-align: center; padding: 22px;">
                    <div style="font-size: 38px; margin-bottom: 10px;">⚠️</div>
                    <h4 style="margin: 0 0 8px; color: #1B1F44; font-size: 17px;">Permanently delete this event?</h4>
                    <p style="color: #475569; font-size: 13.5px; margin: 0 0 14px;">
                        <strong id="m_del_title">--</strong>
                    </p>
                    <div style="background-color: #fff3cd; color: #856404; font-size: 12px; padding: 10px; border-radius: 6px; border: 1px solid #ffeeba; text-align: left;">
                        Note: Deleting will remove this event, its uploaded poster file, and all student registrations for this event from MySQL.
                    </div>
                </div>
                <div class="modal-foot" style="justify-content: center; gap: 12px;">
                    <button type="button" class="btn-reset-filters" onclick="closeDeleteEventModal()">No, Cancel</button>
                    <button type="submit" class="btn-act-del" style="padding: 8px 16px; font-size: 13px;">🗑️ Yes, Delete Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. VIEW EVENT DOSSIER MODAL -->
    <div class="modal-overlay" id="viewEventModal" onclick="handleModalBackdrop(event, 'viewEventModal')">
        <div class="modal-container" style="max-width: 580px;">
            <div class="modal-head">
                <h3>👁️ Campus Event Dossier</h3>
                <button type="button" class="modal-close-cross" onclick="closeViewEventModal()">&times;</button>
            </div>
            <div class="modal-content-body">
                <div id="m_view_poster_box" style="text-align: center; margin-bottom: 15px; border-radius: 8px; overflow: hidden; background: #0f172a; max-height: 280px; display: flex; align-items: center; justify-content: center;">
                    <img id="m_view_poster_img" src="" alt="Event Poster" style="max-width: 100%; max-height: 280px; object-fit: contain;">
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <span class="pill-badge pill-tech" id="m_view_tag">Category</span>
                    <span class="pill-badge" id="m_view_regs" style="background-color: #f1f5f9; color: #1B1F44; font-weight: bold;">👥 0 Registered</span>
                </div>
                <h3 id="m_view_title" style="margin: 0 0 10px; color: #1B1F44; font-size: 18px;">Event Title</h3>

                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13.5px;">
                    <span style="color: #64748b; font-weight: 600;">📅 Date &amp; Time:</span>
                    <span style="font-weight: bold; color: #1e293b;" id="m_view_date">--</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13.5px;">
                    <span style="color: #64748b; font-weight: 600;">📍 Venue:</span>
                    <span style="font-weight: bold; color: #1e293b;" id="m_view_venue">--</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13.5px;">
                    <span style="color: #64748b; font-weight: 600;">🏢 Organizer:</span>
                    <span style="font-weight: bold; color: #1e293b;" id="m_view_organizer">--</span>
                </div>
                <div style="margin-top: 14px;">
                    <div style="font-size: 13px; font-weight: bold; color: #1B1F44; margin-bottom: 6px;">Description:</div>
                    <div id="m_view_desc" style="font-size: 13px; color: #475569; line-height: 1.5; background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0; white-space: pre-wrap;">--</div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-reset-filters" onclick="closeViewEventModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- HOME BUTTON AT BOTTOM RIGHT -->
    <a href="../index.html" class="home-btn">🏠 Home</a>

    <!-- FOOTER -->
    <footer>
        &copy; 2026 STUDENT HUB • CHARUSAT University • All Rights Reserved.
    </footer>

    <!-- INTERACTIVE JAVASCRIPT -->
    <script>
        // 1. File Validation & Preview
        function validateAndPreview(input, previewId, errorId) {
            var file = input.files[0];
            var previewBox = document.getElementById(previewId);
            var errBox = document.getElementById(errorId);

            if (errBox) {
                errBox.textContent = '';
                errBox.style.display = 'none';
            }
            if (previewBox) previewBox.style.display = 'none';

            if (!file) return;

            // Whitelist extensions
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

            // Max 2 MB = 2 * 1024 * 1024 bytes
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

            // Client-side image preview
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

        // 2. Modal Openers & Closers
        function openAddEventModal() {
            var prev = document.getElementById('m_add_preview_box');
            if (prev) prev.style.display = 'none';
            var err = document.getElementById('m_add_err_box');
            if (err) err.style.display = 'none';
            var inp = document.getElementById('m_add_poster_input');
            if (inp) inp.value = '';
            document.getElementById('addEventModal').style.display = 'flex';
        }
        function closeAddEventModal() {
            document.getElementById('addEventModal').style.display = 'none';
        }

        function openEditEventModal(ev) {
            document.getElementById('m_edit_id').value = ev.event_id;
            document.getElementById('m_edit_title').value = ev.title;
            document.getElementById('m_edit_tag').value = ev.tag;
            document.getElementById('m_edit_date').value = ev.event_date;
            document.getElementById('m_edit_venue').value = ev.venue;
            document.getElementById('m_edit_organizer').value = ev.organizer;
            document.getElementById('m_edit_desc').value = ev.description;

            var currBox = document.getElementById('m_edit_current_poster_box');
            if (ev.poster) {
                currBox.innerHTML = '<div style="display: flex; align-items: center; gap: 10px;">' +
                    '<img src="../' + ev.poster + '" style="height: 50px; width: 70px; object-fit: cover; border-radius: 4px; border: 1px solid #cbd5e1;">' +
                    '<div><code style="font-size: 11px;">' + ev.poster + '</code><br><span style="color: #16a34a; font-size: 11px; font-weight: bold;">✓ Current Poster Active</span></div>' +
                    '</div>';
            } else {
                currBox.innerHTML = '<span style="color: #64748b;">No poster currently attached to this event.</span>';
            }

            var prev = document.getElementById('m_edit_preview_box');
            if (prev) prev.style.display = 'none';
            var err = document.getElementById('m_edit_err_box');
            if (err) err.style.display = 'none';
            var inp = document.getElementById('m_edit_poster_input');
            if (inp) inp.value = '';

            document.getElementById('editEventModal').style.display = 'flex';
        }
        function closeEditEventModal() {
            document.getElementById('editEventModal').style.display = 'none';
        }

        function openDeleteEventModal(id, title) {
            document.getElementById('m_del_id').value = id;
            document.getElementById('m_del_title').textContent = title;
            document.getElementById('deleteEventModal').style.display = 'flex';
        }
        function closeDeleteEventModal() {
            document.getElementById('deleteEventModal').style.display = 'none';
        }

        function viewEventDossier(ev) {
            document.getElementById('m_view_title').textContent = ev.title;
            document.getElementById('m_view_tag').textContent = ev.tag;
            document.getElementById('m_view_regs').textContent = '👥 ' + (ev.reg_count || 0) + ' Registered';
            document.getElementById('m_view_date').textContent = ev.event_date;
            document.getElementById('m_view_venue').textContent = ev.venue;
            document.getElementById('m_view_organizer').textContent = ev.organizer;
            document.getElementById('m_view_desc').textContent = ev.description;

            var posterBox = document.getElementById('m_view_poster_box');
            var posterImg = document.getElementById('m_view_poster_img');
            if (ev.poster) {
                posterImg.src = '../' + ev.poster;
                posterBox.style.display = 'flex';
            } else {
                posterBox.style.display = 'none';
            }

            document.getElementById('viewEventModal').style.display = 'flex';
        }
        function closeViewEventModal() {
            document.getElementById('viewEventModal').style.display = 'none';
        }

        function handleModalBackdrop(e, id) {
            if (e.target.id === id) {
                document.getElementById(id).style.display = 'none';
            }
        }

        window.addEventListener("keydown", function(e) {
            if (e.key === "Escape") {
                closeAddEventModal();
                closeEditEventModal();
                closeDeleteEventModal();
                closeViewEventModal();
            }
        });

        // 3. Realtime Search & Filters
        function filterEventsRealtime() {
            var query = document.getElementById('liveEventSearch').value.toLowerCase().trim();
            var category = document.getElementById('categoryFilter').value;
            var poster = document.getElementById('posterFilter').value;

            // Filter Table rows
            var rows = document.querySelectorAll('.event-data-row');
            var visibleCount = 0;
            rows.forEach(function(row) {
                var title = row.getAttribute('data-title') || '';
                var venue = row.getAttribute('data-venue') || '';
                var organizer = row.getAttribute('data-organizer') || '';
                var tag = row.getAttribute('data-tag') || '';
                var hasPoster = row.getAttribute('data-hasposter') || '0';

                var matchQuery = (title.indexOf(query) !== -1 || venue.indexOf(query) !== -1 || organizer.indexOf(query) !== -1);
                var matchCat = (category === 'ALL' || tag === category);
                var matchPoster = (poster === 'ALL' || (poster === 'WITH' && hasPoster === '1') || (poster === 'WITHOUT' && hasPoster === '0'));

                if (matchQuery && matchCat && matchPoster) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Filter Grid cards
            var gridItems = document.querySelectorAll('.event-data-grid-item');
            gridItems.forEach(function(card) {
                var title = card.getAttribute('data-title') || '';
                var venue = card.getAttribute('data-venue') || '';
                var organizer = card.getAttribute('data-organizer') || '';
                var tag = card.getAttribute('data-tag') || '';
                var hasPoster = card.getAttribute('data-hasposter') || '0';

                var matchQuery = (title.indexOf(query) !== -1 || venue.indexOf(query) !== -1 || organizer.indexOf(query) !== -1);
                var matchCat = (category === 'ALL' || tag === category);
                var matchPoster = (poster === 'ALL' || (poster === 'WITH' && hasPoster === '1') || (poster === 'WITHOUT' && hasPoster === '0'));

                card.style.display = (matchQuery && matchCat && matchPoster) ? '' : 'none';
            });

            var noMsg = document.getElementById('noEventRowsMessage');
            if (noMsg) noMsg.style.display = (visibleCount === 0) ? 'block' : 'none';

            var countEl = document.getElementById('displayEventsCount');
            if (countEl) countEl.textContent = visibleCount;
        }

        function resetFilters() {
            document.getElementById('liveEventSearch').value = '';
            document.getElementById('categoryFilter').value = 'ALL';
            document.getElementById('posterFilter').value = 'ALL';
            filterEventsRealtime();
        }

        // 4. View Mode Toggle (Table / Grid)
        function toggleView(mode) {
            var tableContainer = document.getElementById('tableViewContainer');
            var gridContainer = document.getElementById('gridViewContainer');
            var btnTable = document.getElementById('btnViewTable');
            var btnGrid = document.getElementById('btnViewGrid');

            if (mode === 'grid') {
                tableContainer.style.display = 'none';
                gridContainer.style.display = 'grid';
                btnTable.classList.remove('active');
                btnGrid.classList.add('active');
            } else {
                tableContainer.style.display = 'block';
                gridContainer.style.display = 'none';
                btnTable.classList.add('active');
                btnGrid.classList.remove('active');
            }
        }

        // 5. CSV Export
        function exportEventsToCSV() {
            var rows = document.querySelectorAll('.event-data-row');
            if (rows.length === 0) {
                alert('No event records available to export.');
                return;
            }

            var csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Event ID,Title,Category,Date,Venue,Organizer,Has Poster,Registrations\n";

            rows.forEach(function(row) {
                if (row.style.display !== 'none') {
                    var id = row.getAttribute('data-id');
                    var title = '"' + (row.getAttribute('data-title') || '').replace(/"/g, '""') + '"';
                    var tag = '"' + (row.getAttribute('data-tag') || '') + '"';
                    var venue = '"' + (row.getAttribute('data-venue') || '').replace(/"/g, '""') + '"';
                    var organizer = '"' + (row.getAttribute('data-organizer') || '').replace(/"/g, '""') + '"';
                    var hasPoster = (row.getAttribute('data-hasposter') === '1') ? 'Yes' : 'No';
                    var regs = row.querySelector('.pill-badge[style*="f1f5f9"]').textContent.replace(/[^0-9]/g, '');

                    csvContent += [id, title, tag, '""', venue, organizer, hasPoster, regs].join(',') + '\n';
                }
            });

            var encodedUri = encodeURI(csvContent);
            var link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "studenthub_events_" + new Date().toISOString().slice(0, 10) + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // =====================================================================
        // PRACTICAL 13: FETCH API ASYNCHRONOUS CRUD FOR EVENTS (NO PAGE RELOAD)
        // =====================================================================

        function showEventBanner(type, messageText) {
            var alertBox = document.getElementById("feedbackAlert");
            var alertText = document.getElementById("feedbackAlertText");
            if (!alertBox || !alertText) return;

            alertBox.className = "alert-box " + type;
            alertBox.style.display = "flex";

            var isSuccess = (type === "success");
            alertText.innerHTML = (isSuccess ? "<strong>✓ Success:</strong> " : "<strong>⚠️ Notice:</strong> ") + String(messageText).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
            window.scrollTo({ top: 0, behavior: "smooth" });
        }

        // 1. Fetch API: Add Event Asynchronously
        var addEvForm = document.getElementById("addEventForm");
        if (addEvForm) {
            addEvForm.addEventListener("submit", function(e) {
                e.preventDefault();
                var formData = new FormData(addEvForm);

                fetch("../api/events.php?action=add", {
                    method: "POST",
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(result) {
                    if (result.status === "success") {
                        showEventBanner("success", result.message);
                        closeAddEventModal();
                        addEvForm.reset();
                        // Re-fetch events list via Fetch API to refresh table & cards
                        loadEventsAsync();
                    } else {
                        showEventBanner("error", result.message);
                    }
                })
                .catch(function(err) {
                    showEventBanner("error", "Error adding event: " + err.message);
                });
            });
        }

        // 2. Fetch API: Update Event Asynchronously
        var editEvForm = document.getElementById("editEventForm");
        if (editEvForm) {
            editEvForm.addEventListener("submit", function(e) {
                e.preventDefault();
                var formData = new FormData(editEvForm);

                fetch("../api/events.php?action=update", {
                    method: "POST",
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(result) {
                    if (result.status === "success") {
                        showEventBanner("success", result.message);
                        closeEditEventModal();
                        loadEventsAsync();
                    } else {
                        showEventBanner("error", result.message);
                    }
                })
                .catch(function(err) {
                    showEventBanner("error", "Error updating event: " + err.message);
                });
            });
        }

        // 3. Fetch API: Delete Event Asynchronously
        var deleteEvForm = document.getElementById("deleteEventForm");
        if (deleteEvForm) {
            deleteEvForm.addEventListener("submit", function(e) {
                e.preventDefault();
                var formData = new FormData(deleteEvForm);

                fetch("../api/events.php?action=delete", {
                    method: "POST",
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(result) {
                    if (result.status === "success") {
                        showEventBanner("success", result.message);
                        closeDeleteEventModal();
                        loadEventsAsync();
                    } else {
                        showEventBanner("error", result.message);
                    }
                })
                .catch(function(err) {
                    showEventBanner("error", "Error deleting event: " + err.message);
                });
            });
        }

        // 4. Fetch API: List Events Asynchronously
        function loadEventsAsync() {
            fetch("../api/events.php?action=list")
                .then(function(res) { return res.json(); })
                .then(function(result) {
                    if (result.status === "success" && Array.isArray(result.data)) {
                        renderEventsData(result.data);
                    }
                })
                .catch(function(err) {
                    console.error("Fetch API events list error:", err);
                });
        }

        function renderEventsData(eventsList) {
            var tbody = document.getElementById("eventsTableBody");
            var grid = document.getElementById("gridViewContainer");
            if (!tbody || !grid) return;

            var tableHtml = "";
            var gridHtml = "";

            eventsList.forEach(function(ev) {
                var hasPoster = ev.poster ? "1" : "0";
                var posterBadge = ev.poster ? '<span class="pill-badge pill-active">Yes</span>' : '<span class="pill-badge pill-gen">No</span>';
                var titleEsc = String(ev.title).replace(/"/g, '&quot;');
                var venueEsc = String(ev.venue).replace(/"/g, '&quot;');
                var orgEsc = String(ev.organizer).replace(/"/g, '&quot;');
                var descEsc = String(ev.description).replace(/"/g, '&quot;');

                // Table row
                tableHtml += '<tr class="event-data-row" id="ev-row-' + ev.event_id + '"' +
                    ' data-id="' + ev.event_id + '"' +
                    ' data-title="' + titleEsc.toLowerCase() + '"' +
                    ' data-venue="' + venueEsc.toLowerCase() + '"' +
                    ' data-organizer="' + orgEsc.toLowerCase() + '"' +
                    ' data-tag="' + ev.tag + '"' +
                    ' data-hasposter="' + hasPoster + '"' +
                    ' data-rawtitle="' + titleEsc + '"' +
                    ' data-rawdate="' + String(ev.event_date).replace(/"/g, '&quot;') + '"' +
                    ' data-rawvenue="' + venueEsc + '"' +
                    ' data-raworganizer="' + orgEsc + '"' +
                    ' data-rawdesc="' + descEsc + '"' +
                    ' data-rawposter="' + (ev.poster || '') + '">' +
                    '<td><b>#' + ev.event_id + '</b></td>' +
                    '<td>' +
                        (ev.poster ? '<img src="../' + ev.poster + '" style="width: 44px; height: 32px; object-fit: cover; border-radius: 4px; border: 1px solid #cbd5e1; vertical-align: middle; margin-right: 8px;">' : '') +
                        '<b>' + titleEsc + '</b>' +
                    '</td>' +
                    '<td><span class="pill-badge pill-tag">' + ev.tag + '</span></td>' +
                    '<td>' + ev.event_date + '</td>' +
                    '<td>' + venueEsc + '</td>' +
                    '<td>' + orgEsc + '</td>' +
                    '<td>' + posterBadge + '</td>' +
                    '<td style="text-align: center;">' +
                        '<div class="action-buttons-wrap">' +
                            '<button type="button" class="btn-action-view" onclick="openViewEventModal(this)">👁️ View</button>' +
                            '<button type="button" class="btn-action-edit" onclick="openEditEventModal(this)">✏️ Edit</button>' +
                            '<button type="button" class="btn-action-delete" onclick="openDeleteEventModal(this)">🗑️ Delete</button>' +
                        '</div>' +
                    '</td>' +
                '</tr>';
            });

            tbody.innerHTML = tableHtml;
            filterEventsRealtime();
        }
    </script>
    <script src="../js/script.js"></script>
</body>
</html>
