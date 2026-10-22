<?php
// =============================================================================
// Practical 13: Simple PHP JSON API for Events
// Problem Definition:
// Create simple PHP JSON API endpoints for events or students and
// consume them using Fetch API. Perform list, add, update, and delete
// operations asynchronously without full page reload.
// =============================================================================

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

// Include database connection
require_once __DIR__ . "/../pages/db.php";

// Read action from query string or body
$action = isset($_GET["action"]) ? $_GET["action"] : "";

// Check for JSON body if raw JSON is sent via fetch
$rawInput = file_get_contents("php://input");
$jsonData = json_decode($rawInput, true);

$data = [];
if (!empty($_POST)) {
    $data = $_POST;
} elseif (is_array($jsonData)) {
    $data = $jsonData;
}

if (empty($action) && isset($data["action"])) {
    $action = $data["action"];
}

// -----------------------------------------------------------------------------
// 1. LIST OPERATION (action=list or GET request without action)
// -----------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "GET" && ($action === "" || $action === "list")) {
    if (isset($_GET["event_id"])) {
        $id = intval($_GET["event_id"]);
        $stmt = mysqli_prepare($conn, "SELECT event_id, tag, title, event_date, venue, organizer, description, poster, created_at FROM events WHERE event_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $event = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if ($event) {
            echo json_encode(["status" => "success", "data" => $event]);
        } else {
            echo json_encode(["status" => "error", "message" => "Event not found."]);
        }
        exit;
    }

    $sql = "SELECT event_id, tag, title, event_date, venue, organizer, description, poster, created_at FROM events ORDER BY event_id DESC";
    $res = mysqli_query($conn, $sql);
    $events = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $row['id'] = $row['event_id'];
            $row['date'] = $row['event_date'];
            $row['button'] = 'Register for Event';
            $row['link'] = 'register.php';
            $events[] = $row;
        }
    }

    echo json_encode(["status" => "success", "data" => $events]);
    exit;
}

// -----------------------------------------------------------------------------
// 2. ADD OPERATION (action=add)
// -----------------------------------------------------------------------------
if ($action === "add") {
    $tag         = isset($data["tag"]) ? trim($data["tag"]) : "Technical Fest";
    $title       = isset($data["title"]) ? trim($data["title"]) : "";
    $eventDate   = isset($data["event_date"]) ? trim($data["event_date"]) : "";
    $venue       = isset($data["venue"]) ? trim($data["venue"]) : "";
    $organizer   = isset($data["organizer"]) ? trim($data["organizer"]) : "";
    $description = isset($data["description"]) ? trim($data["description"]) : "";

    if (empty($title) || empty($tag) || empty($eventDate) || empty($venue) || empty($organizer) || empty($description)) {
        echo json_encode(["status" => "error", "message" => "All fields (Title, Category, Date, Venue, Organizer, Description) are required."]);
        exit;
    }

    $posterPath = null;
    if (isset($_FILES["poster"]) && $_FILES["poster"]["error"] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES["poster"]["name"], PATHINFO_EXTENSION));
        if (in_array($ext, ["jpg", "jpeg", "png", "webp", "gif"])) {
            $uploadDir = __DIR__ . "/../uploads/events/";
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $filename = "event_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
            if (move_uploaded_file($_FILES["poster"]["tmp_name"], $uploadDir . $filename)) {
                $posterPath = "uploads/events/" . $filename;
            }
        }
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO events (tag, title, event_date, venue, organizer, description, poster) VALUES (?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "sssssss", $tag, $title, $eventDate, $venue, $organizer, $description, $posterPath);

    if (mysqli_stmt_execute($stmt)) {
        $newId = mysqli_insert_id($conn);
        echo json_encode([
            "status" => "success",
            "message" => "Event #$newId ('$title') added successfully!",
            "event_id" => $newId
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database Error: " . mysqli_error($conn)]);
    }
    mysqli_stmt_close($stmt);
    exit;
}

// -----------------------------------------------------------------------------
// 3. UPDATE OPERATION (action=update or action=edit)
// -----------------------------------------------------------------------------
if ($action === "update" || $action === "edit") {
    $eventId     = isset($data["event_id"]) ? intval($data["event_id"]) : 0;
    $tag         = isset($data["tag"]) ? trim($data["tag"]) : "";
    $title       = isset($data["title"]) ? trim($data["title"]) : "";
    $eventDate   = isset($data["event_date"]) ? trim($data["event_date"]) : "";
    $venue       = isset($data["venue"]) ? trim($data["venue"]) : "";
    $organizer   = isset($data["organizer"]) ? trim($data["organizer"]) : "";
    $description = isset($data["description"]) ? trim($data["description"]) : "";

    if ($eventId <= 0 || empty($title) || empty($tag) || empty($eventDate) || empty($venue) || empty($organizer) || empty($description)) {
        echo json_encode(["status" => "error", "message" => "All fields are required."]);
        exit;
    }

    $posterPath = null;
    $hasNewPoster = false;
    if (isset($_FILES["poster"]) && $_FILES["poster"]["error"] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES["poster"]["name"], PATHINFO_EXTENSION));
        if (in_array($ext, ["jpg", "jpeg", "png", "webp", "gif"])) {
            $uploadDir = __DIR__ . "/../uploads/events/";
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $filename = "event_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
            if (move_uploaded_file($_FILES["poster"]["tmp_name"], $uploadDir . $filename)) {
                $posterPath = "uploads/events/" . $filename;
                $hasNewPoster = true;
            }
        }
    }

    if ($hasNewPoster) {
        $stmt = mysqli_prepare($conn, "UPDATE events SET tag = ?, title = ?, event_date = ?, venue = ?, organizer = ?, description = ?, poster = ? WHERE event_id = ?");
        mysqli_stmt_bind_param($stmt, "sssssssi", $tag, $title, $eventDate, $venue, $organizer, $description, $posterPath, $eventId);
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE events SET tag = ?, title = ?, event_date = ?, venue = ?, organizer = ?, description = ? WHERE event_id = ?");
        mysqli_stmt_bind_param($stmt, "ssssssi", $tag, $title, $eventDate, $venue, $organizer, $description, $eventId);
    }

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(["status" => "success", "message" => "Event #$eventId updated successfully!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database Update Error: " . mysqli_error($conn)]);
    }
    mysqli_stmt_close($stmt);
    exit;
}

// -----------------------------------------------------------------------------
// 4. DELETE OPERATION (action=delete)
// -----------------------------------------------------------------------------
if ($action === "delete") {
    $eventId = isset($data["event_id"]) ? intval($data["event_id"]) : (isset($_GET["event_id"]) ? intval($_GET["event_id"]) : 0);

    if ($eventId <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid event ID."]);
        exit;
    }

    $delStmt = mysqli_prepare($conn, "DELETE FROM events WHERE event_id = ?");
    mysqli_stmt_bind_param($delStmt, "i", $eventId);

    if (mysqli_stmt_execute($delStmt) && mysqli_stmt_affected_rows($delStmt) > 0) {
        echo json_encode(["status" => "success", "message" => "Event #$eventId deleted successfully!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Unable to delete event: " . mysqli_error($conn)]);
    }
    mysqli_stmt_close($delStmt);
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid or unsupported API action."]);
