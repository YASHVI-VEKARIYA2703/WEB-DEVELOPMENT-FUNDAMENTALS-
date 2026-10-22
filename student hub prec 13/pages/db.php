<?php
// ==========================================================================
// Practical 10: Secure Login/Logout with Sessions, Role-Based Access, Timeout, and Remember-Me Token
// Problem Definition:
// Develop secure login and logout functionality using PHP sessions. Verify
// password using password_verify(), regenerate session ID after login,
// restrict dashboard access, implement role-based redirection, session
// timeout, and optional secure remember-me token.
// ==========================================================================

$host     = "localhost";
$dbname   = "studenthub";
$username = "root";
$password = "";

// 1. Establish MySQLi connection to MySQL server
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect($host, $username, $password);

// Check MySQLi connection
if (!$conn) {
    if (strpos($_SERVER['REQUEST_URI'] ?? '', 'api/') !== false || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode(["status" => "error", "message" => "Database connection error: " . mysqli_connect_error() . ". Please start MySQL in XAMPP."]);
        exit;
    }
    die("Database Connection Error: " . mysqli_connect_error());
}

// 2. Create database if it does not already exist
$createDbSql = "CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
mysqli_query($conn, $createDbSql);

// Select the database
mysqli_select_db($conn, $dbname);

// 3. Ensure tables exist (students, events, registrations, auth_tokens)
$createStudents = "
    CREATE TABLE IF NOT EXISTS students (
        student_id INT AUTO_INCREMENT PRIMARY KEY,
        fullname VARCHAR(100) NOT NULL,
        enrollment_no VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        mobile VARCHAR(15) NOT NULL,
        department VARCHAR(50) NOT NULL,
        semester VARCHAR(20) NOT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'student',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
";
mysqli_query($conn, $createStudents);

// Check if 'role' column exists in students table, add if missing
$checkRoleCol = mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'role'");
if ($checkRoleCol && mysqli_num_rows($checkRoleCol) === 0) {
    mysqli_query($conn, "ALTER TABLE students ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'student' AFTER password");
}

$createEvents = "
    CREATE TABLE IF NOT EXISTS events (
        event_id INT AUTO_INCREMENT PRIMARY KEY,
        tag VARCHAR(50) NOT NULL,
        title VARCHAR(150) NOT NULL,
        event_date VARCHAR(50) NOT NULL,
        venue VARCHAR(100) NOT NULL,
        organizer VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        poster VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
";
mysqli_query($conn, $createEvents);

// Check if 'poster' column exists in events table, add if missing
$checkPosterCol = mysqli_query($conn, "SHOW COLUMNS FROM events LIKE 'poster'");
if ($checkPosterCol && mysqli_num_rows($checkPosterCol) === 0) {
    mysqli_query($conn, "ALTER TABLE events ADD COLUMN poster VARCHAR(255) DEFAULT NULL AFTER description");
}

$createRegistrations = "
    CREATE TABLE IF NOT EXISTS registrations (
        registration_id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        event_id INT NOT NULL,
        registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(20) DEFAULT 'Confirmed',
        UNIQUE KEY unique_student_event (student_id, event_id),
        FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE ON UPDATE CASCADE,
        FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB;
";
mysqli_query($conn, $createRegistrations);

// 4. Ensure auth_tokens table exists for secure Remember-Me functionality
$createAuthTokens = "
    CREATE TABLE IF NOT EXISTS auth_tokens (
        token_id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        selector VARCHAR(32) NOT NULL UNIQUE,
        token_hash VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB;
";
mysqli_query($conn, $createAuthTokens);

// 5. Ensure demo admin account exists with role 'admin'
$checkAdmin = mysqli_query($conn, "SELECT student_id FROM students WHERE role = 'admin' OR enrollment_no = 'ADMIN001' OR email = 'admin@charusat.edu.in' LIMIT 1");
if ($checkAdmin && mysqli_num_rows($checkAdmin) === 0) {
    $adminPassHash = password_hash("admin123", PASSWORD_DEFAULT);
    $adminInsert = mysqli_prepare($conn, "
        INSERT INTO students (fullname, enrollment_no, email, mobile, department, semester, password, role)
        VALUES ('System Administrator', 'ADMIN001', 'admin@charusat.edu.in', '9898000000', 'Administration', 'Staff', ?, 'admin')
    ");
    if ($adminInsert) {
        mysqli_stmt_bind_param($adminInsert, "s", $adminPassHash);
        mysqli_stmt_execute($adminInsert);
        mysqli_stmt_close($adminInsert);
    }
}

// 6. Ensure student 1 (Yashvi Vekariya) has a valid working password hash for 'yashvi123'
$checkStudent1 = mysqli_query($conn, "SELECT student_id, password FROM students WHERE student_id = 1 OR enrollment_no = '25DCE122'");
if ($checkStudent1 && $row1 = mysqli_fetch_assoc($checkStudent1)) {
    if (!password_verify("yashvi123", $row1['password'])) {
        $studentPassHash = password_hash("yashvi123", PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE students SET password = '$studentPassHash' WHERE student_id = {$row1['student_id']}");
    }
}

// Seed events table from DATA/events.json if empty
$checkEvents = mysqli_query($conn, "SELECT COUNT(*) AS total FROM events");
if ($checkEvents) {
    $row = mysqli_fetch_assoc($checkEvents);
    if ($row && $row['total'] == 0) {
        $eventsJsonPath = __DIR__ . "/../DATA/events.json";
        if (file_exists($eventsJsonPath)) {
            $eventsData = json_decode(file_get_contents($eventsJsonPath), true);
            if (is_array($eventsData)) {
                $seedStmt = mysqli_prepare($conn, "
                    INSERT INTO events (event_id, tag, title, event_date, venue, organizer, description)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                if ($seedStmt) {
                    foreach ($eventsData as $ev) {
                        mysqli_stmt_bind_param(
                            $seedStmt,
                            "issssss",
                            $ev['id'],
                            $ev['tag'],
                            $ev['title'],
                            $ev['date'],
                            $ev['venue'],
                            $ev['organizer'],
                            $ev['description']
                        );
                        mysqli_stmt_execute($seedStmt);
                    }
                    mysqli_stmt_close($seedStmt);
                }
            }
        }
    }
}

// 7. Maintain PDO connection so existing pages (e.g., view_records.php) continue to function smoothly
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Optional fallback
}
?>
