<?php
// ==========================================================================
// Practical 9: Secure User Registration with Database Insert, Duplicate Email Check, and Password Hashing
// Problem Definition:
// Implement secure user registration by inserting user details into MySQL
// using MYSQLi. Validate input on frontend and backend, check duplicate
// email/username, hash passwords using password_hash(), and display
// appropriate messages.
// ==========================================================================

$host     = "localhost";
$dbname   = "studenthub";
$username = "root";
$password = "";

// 1. Establish MySQLi connection to MySQL server (Beginner style)
$conn = mysqli_connect($host, $username, $password);

// Check MySQLi connection
if (!$conn) {
    die("Database Connection Error: " . mysqli_connect_error());
}

// 2. Create database if it does not already exist
$createDbSql = "CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
mysqli_query($conn, $createDbSql);

// Select the database
mysqli_select_db($conn, $dbname);

// 3. Ensure tables exist (students, events, registrations)
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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
";
mysqli_query($conn, $createStudents);

$createEvents = "
    CREATE TABLE IF NOT EXISTS events (
        event_id INT AUTO_INCREMENT PRIMARY KEY,
        tag VARCHAR(50) NOT NULL,
        title VARCHAR(150) NOT NULL,
        event_date VARCHAR(50) NOT NULL,
        venue VARCHAR(100) NOT NULL,
        organizer VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
";
mysqli_query($conn, $createEvents);

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

// 4. Maintain PDO connection so existing pages (e.g., view_records.php) continue to function smoothly
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Optional fallback
}
?>
