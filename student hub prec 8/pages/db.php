<?php
// ==========================================================================
// Practical 8: Database Connection using PDO (PHP Data Objects)
// Problem Definition:
// Design the database for StudentHub with students, events and registrations.
// Create PHP database connection using MYSQL statements.
// ==========================================================================

$host     = "localhost";
$dbname   = "studenthub";
$username = "root";
$password = "";

try {
    // 1. Establish PDO connection to MySQL server
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);

    // 2. Set error reporting mode to exceptions for easy debugging
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 3. Create database if it does not already exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // 4. Ensure tables exist (students, events, registrations)
    $pdo->exec("
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
    ");

    $pdo->exec("
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
    ");

    $pdo->exec("
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
    ");

    // Seed events table from DATA/events.json if empty
    $checkEvents = $pdo->query("SELECT COUNT(*) AS total FROM events")->fetch();
    if ($checkEvents && $checkEvents['total'] == 0) {
        $eventsJsonPath = __DIR__ . "/../DATA/events.json";
        if (file_exists($eventsJsonPath)) {
            $eventsData = json_decode(file_get_contents($eventsJsonPath), true);
            if (is_array($eventsData)) {
                $seedStmt = $pdo->prepare("
                    INSERT INTO events (event_id, tag, title, event_date, venue, organizer, description)
                    VALUES (:id, :tag, :title, :date, :venue, :organizer, :desc)
                ");
                foreach ($eventsData as $ev) {
                    $seedStmt->execute([
                        ':id'        => $ev['id'],
                        ':tag'       => $ev['tag'],
                        ':title'     => $ev['title'],
                        ':date'      => $ev['date'],
                        ':venue'     => $ev['venue'],
                        ':organizer' => $ev['organizer'],
                        ':desc'      => $ev['description']
                    ]);
                }
            }
        }
    }

} catch (PDOException $e) {
    // Beginner-friendly error handling
    die("Database Connection Error: " . $e->getMessage());
}
?>
