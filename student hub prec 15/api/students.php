<?php
// =============================================================================
// Practical 13: Simple PHP JSON API for Students
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

// Merge inputs so both FormData and JSON payload work
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
    // If a specific student ID is requested
    if (isset($_GET["student_id"])) {
        $id = intval($_GET["student_id"]);
        $stmt = mysqli_prepare($conn, "SELECT student_id, fullname, enrollment_no, email, mobile, department, semester, role, created_at FROM students WHERE student_id = ? AND role != 'admin' LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $student = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if ($student) {
            echo json_encode(["status" => "success", "data" => $student]);
        } else {
            echo json_encode(["status" => "error", "message" => "Student record not found."]);
        }
        exit;
    }

    // List all students (exclude admin accounts)
    $sql = "SELECT student_id, fullname, enrollment_no, email, mobile, department, semester, role, created_at FROM students WHERE role != 'admin' ORDER BY student_id DESC";
    $res = mysqli_query($conn, $sql);
    $students = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $students[] = $row;
        }
    }

    echo json_encode(["status" => "success", "data" => $students]);
    exit;
}

// -----------------------------------------------------------------------------
// 2. ADD OPERATION (action=add)
// -----------------------------------------------------------------------------
if ($action === "add") {
    $fullname   = isset($data["fullname"]) ? trim($data["fullname"]) : "";
    $enrollment = isset($data["enrollment_no"]) ? trim(strtoupper($data["enrollment_no"])) : "";
    $email      = isset($data["email"]) ? trim(strtolower($data["email"])) : "";
    $mobile     = isset($data["mobile"]) ? trim($data["mobile"]) : "";
    $department = isset($data["department"]) ? trim($data["department"]) : "Computer Engineering";
    $semester   = isset($data["semester"]) ? trim($data["semester"]) : "Semester 3";
    $password   = isset($data["password"]) ? $data["password"] : "";

    // Validation
    if (empty($fullname) || empty($enrollment) || empty($email) || empty($mobile) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "All fields (Name, Enrollment, Email, Mobile, Password) are required."]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["status" => "error", "message" => "Please enter a valid email address."]);
        exit;
    }

    if (!preg_match("/^[0-9]{10}$/", $mobile)) {
        echo json_encode(["status" => "error", "message" => "Mobile number must be exactly 10 digits."]);
        exit;
    }

    if (strlen($password) < 6) {
        echo json_encode(["status" => "error", "message" => "Password must be at least 6 characters long."]);
        exit;
    }

    // Check duplicate enrollment or email
    $checkStmt = mysqli_prepare($conn, "SELECT student_id, enrollment_no, email FROM students WHERE enrollment_no = ? OR email = ? LIMIT 1");
    mysqli_stmt_bind_param($checkStmt, "ss", $enrollment, $email);
    mysqli_stmt_execute($checkStmt);
    $dupRes = mysqli_stmt_get_result($checkStmt);
    $existing = mysqli_fetch_assoc($dupRes);
    mysqli_stmt_close($checkStmt);

    if ($existing) {
        if (strtoupper($existing["enrollment_no"]) === $enrollment) {
            echo json_encode(["status" => "error", "message" => "Enrollment Number '$enrollment' already exists in the database."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Email address '$email' is already registered with another student."]);
        }
        exit;
    }

    // Insert student
    $passHash = password_hash($password, PASSWORD_DEFAULT);
    $insertStmt = mysqli_prepare($conn, "INSERT INTO students (fullname, enrollment_no, email, mobile, department, semester, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, 'student')");
    mysqli_stmt_bind_param($insertStmt, "sssssss", $fullname, $enrollment, $email, $mobile, $department, $semester, $passHash);

    if (mysqli_stmt_execute($insertStmt)) {
        $newId = mysqli_insert_id($conn);
        echo json_encode([
            "status" => "success",
            "message" => "Student #$newId ('$fullname' - $enrollment) added successfully!",
            "data" => [
                "student_id" => $newId,
                "fullname" => $fullname,
                "enrollment_no" => $enrollment,
                "email" => $email,
                "mobile" => $mobile,
                "department" => $department,
                "semester" => $semester,
                "role" => "student",
                "created_at" => date("Y-m-d H:i:s")
            ]
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database Error: " . mysqli_error($conn)]);
    }
    mysqli_stmt_close($insertStmt);
    exit;
}

// -----------------------------------------------------------------------------
// 3. UPDATE OPERATION (action=update or action=edit)
// -----------------------------------------------------------------------------
if ($action === "update" || $action === "edit") {
    $studentId  = isset($data["student_id"]) ? intval($data["student_id"]) : 0;
    $fullname   = isset($data["fullname"]) ? trim($data["fullname"]) : "";
    $enrollment = isset($data["enrollment_no"]) ? trim(strtoupper($data["enrollment_no"])) : "";
    $email      = isset($data["email"]) ? trim(strtolower($data["email"])) : "";
    $mobile     = isset($data["mobile"]) ? trim($data["mobile"]) : "";
    $department = isset($data["department"]) ? trim($data["department"]) : "";
    $semester   = isset($data["semester"]) ? trim($data["semester"]) : "";
    $newPass    = isset($data["new_password"]) ? trim($data["new_password"]) : "";

    if ($studentId <= 0 || empty($fullname) || empty($enrollment) || empty($email) || empty($mobile) || empty($department) || empty($semester)) {
        echo json_encode(["status" => "error", "message" => "All student profile fields are required."]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["status" => "error", "message" => "Invalid email format."]);
        exit;
    }

    if (!preg_match("/^[0-9]{10}$/", $mobile)) {
        echo json_encode(["status" => "error", "message" => "Mobile number must be 10 digits."]);
        exit;
    }

    // Verify student exists and is not admin
    $checkStmt = mysqli_prepare($conn, "SELECT student_id, role FROM students WHERE student_id = ? LIMIT 1");
    mysqli_stmt_bind_param($checkStmt, "i", $studentId);
    mysqli_stmt_execute($checkStmt);
    $stRow = mysqli_fetch_assoc(mysqli_stmt_get_result($checkStmt));
    mysqli_stmt_close($checkStmt);

    if (!$stRow) {
        echo json_encode(["status" => "error", "message" => "Student record #$studentId does not exist."]);
        exit;
    }

    if ($stRow["role"] === "admin") {
        echo json_encode(["status" => "error", "message" => "Administrator accounts cannot be modified here."]);
        exit;
    }

    // Check duplicate for other records
    $dupStmt = mysqli_prepare($conn, "SELECT student_id, enrollment_no, email FROM students WHERE (enrollment_no = ? OR email = ?) AND student_id != ? LIMIT 1");
    mysqli_stmt_bind_param($dupStmt, "ssi", $enrollment, $email, $studentId);
    mysqli_stmt_execute($dupStmt);
    $dupRow = mysqli_fetch_assoc(mysqli_stmt_get_result($dupStmt));
    mysqli_stmt_close($dupStmt);

    if ($dupRow) {
        if (strtoupper($dupRow["enrollment_no"]) === $enrollment) {
            echo json_encode(["status" => "error", "message" => "Enrollment Number '$enrollment' is already used by another student."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Email '$email' is already used by another student."]);
        }
        exit;
    }

    if (!empty($newPass)) {
        if (strlen($newPass) < 6) {
            echo json_encode(["status" => "error", "message" => "New password must be at least 6 characters."]);
            exit;
        }
        $passHash = password_hash($newPass, PASSWORD_DEFAULT);
        $updateStmt = mysqli_prepare($conn, "UPDATE students SET fullname = ?, enrollment_no = ?, email = ?, mobile = ?, department = ?, semester = ?, password = ? WHERE student_id = ? AND role != 'admin'");
        mysqli_stmt_bind_param($updateStmt, "sssssssi", $fullname, $enrollment, $email, $mobile, $department, $semester, $passHash, $studentId);
    } else {
        $updateStmt = mysqli_prepare($conn, "UPDATE students SET fullname = ?, enrollment_no = ?, email = ?, mobile = ?, department = ?, semester = ? WHERE student_id = ? AND role != 'admin'");
        mysqli_stmt_bind_param($updateStmt, "ssssssi", $fullname, $enrollment, $email, $mobile, $department, $semester, $studentId);
    }

    if (mysqli_stmt_execute($updateStmt)) {
        echo json_encode(["status" => "success", "message" => "Student record #$studentId ('$fullname') updated successfully!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database Update Error: " . mysqli_error($conn)]);
    }
    mysqli_stmt_close($updateStmt);
    exit;
}

// -----------------------------------------------------------------------------
// 4. DELETE OPERATION (action=delete)
// -----------------------------------------------------------------------------
if ($action === "delete") {
    $studentId = isset($data["student_id"]) ? intval($data["student_id"]) : (isset($_GET["student_id"]) ? intval($_GET["student_id"]) : 0);

    if ($studentId <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid student ID."]);
        exit;
    }

    // Verify student exists and is not admin
    $findStmt = mysqli_prepare($conn, "SELECT student_id, fullname, enrollment_no, role FROM students WHERE student_id = ? LIMIT 1");
    mysqli_stmt_bind_param($findStmt, "i", $studentId);
    mysqli_stmt_execute($findStmt);
    $target = mysqli_fetch_assoc(mysqli_stmt_get_result($findStmt));
    mysqli_stmt_close($findStmt);

    if (!$target) {
        echo json_encode(["status" => "error", "message" => "Student record #$studentId not found."]);
        exit;
    }

    if ($target["role"] === "admin") {
        echo json_encode(["status" => "error", "message" => "Administrator accounts cannot be deleted."]);
        exit;
    }

    $delStmt = mysqli_prepare($conn, "DELETE FROM students WHERE student_id = ? AND role != 'admin'");
    mysqli_stmt_bind_param($delStmt, "i", $studentId);

    if (mysqli_stmt_execute($delStmt) && mysqli_stmt_affected_rows($delStmt) > 0) {
        echo json_encode(["status" => "success", "message" => "Student record #$studentId ('{$target['fullname']}') deleted successfully!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Unable to delete student record: " . mysqli_error($conn)]);
    }
    mysqli_stmt_close($delStmt);
    exit;
}

// If no matching action
echo json_encode(["status" => "error", "message" => "Invalid or unsupported API action."]);
