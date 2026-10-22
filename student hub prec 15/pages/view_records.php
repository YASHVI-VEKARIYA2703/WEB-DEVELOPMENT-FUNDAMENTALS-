<?php
// ==========================================================================
// Practical 8: View Database Records & Demonstrate Prepared Statements
// Problem Definition:
// Design the database for StudentHub with students, events and registrations.
// Create PHP database connection using MYSQL statements.
// ==========================================================================

require_once __DIR__ . "/auth.php";

$message = "";
$messageType = "";

// Handle Quick Event Registration Form Submission using Prepared Statement
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "register_event") {
    $studentId = isset($_POST["student_id"]) ? intval($_POST["student_id"]) : 0;
    $eventId   = isset($_POST["event_id"]) ? intval($_POST["event_id"]) : 0;

    if ($studentId > 0 && $eventId > 0) {
        try {
            // Check if registration already exists using prepared statement
            $checkRegStmt = $pdo->prepare("SELECT registration_id FROM registrations WHERE student_id = :sid AND event_id = :eid");
            $checkRegStmt->execute([':sid' => $studentId, ':eid' => $eventId]);

            if ($checkRegStmt->rowCount() > 0) {
                $message = "This student is already registered for this event.";
                $messageType = "error";
            } else {
                // Insert into registrations table using prepared statement
                $insertRegStmt = $pdo->prepare("
                    INSERT INTO registrations (student_id, event_id, status)
                    VALUES (:sid, :eid, 'Confirmed')
                ");
                $insertRegStmt->execute([':sid' => $studentId, ':eid' => $eventId]);

                $message = "Event registration successful! Record added to MySQL registrations table.";
                $messageType = "success";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    } else {
        $message = "Please select both a student and an event.";
        $messageType = "error";
    }
}

// 1. Fetch Students using Prepared Statement
$studentStmt = $pdo->prepare("SELECT student_id, fullname, enrollment_no, email, mobile, department, semester, created_at FROM students ORDER BY student_id DESC");
$studentStmt->execute();
$students = $studentStmt->fetchAll();

// 2. Fetch Events using Prepared Statement
$eventStmt = $pdo->prepare("SELECT event_id, tag, title, event_date, venue, organizer FROM events ORDER BY event_id ASC");
$eventStmt->execute();
$events = $eventStmt->fetchAll();

// 3. Fetch Registrations (JOIN query demonstrating Foreign Key Relationships)
$regStmt = $pdo->prepare("
    SELECT 
        r.registration_id,
        s.fullname AS student_name,
        s.enrollment_no,
        e.title AS event_title,
        e.tag AS event_tag,
        e.event_date,
        r.registration_date,
        r.status
    FROM registrations r
    INNER JOIN students s ON r.student_id = s.student_id
    INNER JOIN events e ON r.event_id = e.event_id
    ORDER BY r.registration_id DESC
");
$regStmt->execute();
$registrations = $regStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Records - Student Hub</title>
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
        .container {
            max-width: 1050px;
            margin: 20px auto 50px;
            padding: 0 15px;
        }

        .record-card {
            background-color: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-top: 4px solid #1B1F44;
        }

        .record-card h2 {
            color: #1B1F44;
            font-size: 20px;
            margin-bottom: 15px;
            border-bottom: 2px solid orange;
            padding-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .record-card h2 span.badge {
            background-color: #1B1F44;
            color: white;
            font-size: 13px;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: normal;
        }

        .db-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 10px;
            overflow-x: auto;
            display: block;
        }

        .db-table th, .db-table td {
            padding: 9px 12px;
            border: 1px solid #ddd;
            text-align: left;
            white-space: nowrap;
        }

        .db-table th {
            background-color: #f1f5f9;
            color: #1B1F44;
            font-weight: bold;
        }

        .db-table tr:nth-child(even) {
            background-color: #f9fbfd;
        }

        .db-table tr:hover {
            background-color: #f1f5f9;
        }

        .status-badge {
            background-color: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
        }

        .tag-badge {
            background-color: #e9ecef;
            color: #1B1F44;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }

        .alert-msg {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-msg.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-msg.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-inline-box {
            background-color: #f8f9fa;
            border: 1px solid #e2e8f0;
            padding: 18px;
            border-radius: 6px;
            margin-top: 15px;
        }

        .form-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .form-group {
            flex: 1;
            min-width: 220px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: bold;
            color: #1B1F44;
        }

        .form-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 13px;
            box-sizing: border-box;
        }

        .btn-submit {
            padding: 9px 18px;
            background-color: #1B1F44;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
            transition: 0.3s;
        }

        .btn-submit:hover {
            background-color: orange;
            color: #1B1F44;
        }

        .empty-text {
            color: #777;
            font-style: italic;
            padding: 15px;
            text-align: center;
        }
    </style>
</head>
<body class="register-body">

    <!-- HEADER -->
    <iframe
        src="header.html"
        class="header-frame">
    </iframe>

    <h1 class="page-title">MYSQL DATABASE RECORDS</h1>

    <!-- NAVIGATION (ROLE & SESSION ADAPTIVE) -->
    <nav>
        <?php if (is_logged_in()): ?>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="admin_dashboard.php" style="background-color: #28a745; color: white;">ADMIN PANEL</a>
                <a href="manage_students.php">MANAGE STUDENTS</a>
            <?php endif; ?>
            <a href="dashboard.php">DASHBOARD</a>
            <a href="event.html">EVENTS</a>
            <a href="course.html">COURSES</a>
            <a href="contact.html">CONTACT</a>
            <a href="logout.php" style="background-color: #dc3545; color: white;" title="End session and log out">LOGOUT 🚪</a>
        <?php else: ?>
            <a href="dashboard.php">DASHBOARD</a>
            <a href="event.html">EVENTS</a>
            <a href="course.html">COURSES</a>
            <a href="register.php">REGISTER</a>
            <a href="login.php">LOGIN</a>
            <a href="contact.html">CONTACT</a>
        <?php endif; ?>
    </nav>
    <div class="container">

        <?php if (!empty($message)) { ?>
            <div class="alert-msg <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php } ?>

        <!-- SECTION 1: REGISTRATIONS (MANY-TO-MANY RELATIONSHIP) -->
        <div class="record-card">
            <h2>
                1. Event Registrations Table (`registrations`)
                <span class="badge"><?php echo count($registrations); ?> Registered</span>
            </h2>
            <p style="font-size: 13px; color: #555; margin-bottom: 10px;">
                Demonstrates the <strong>Many-to-Many</strong> relationship between Students and Events linked via Foreign Keys (<code>student_id</code> &amp; <code>event_id</code>) using an <code>INNER JOIN</code> query.
            </p>

            <?php if (count($registrations) > 0) { ?>
                <table class="db-table">
                    <thead>
                        <tr>
                            <th>Reg. ID</th>
                            <th>Student Name</th>
                            <th>Enrollment No</th>
                            <th>Event Title</th>
                            <th>Category</th>
                            <th>Event Date</th>
                            <th>Registered On</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $r) { ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($r['registration_id']); ?></td>
                                <td><strong><?php echo htmlspecialchars($r['student_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($r['enrollment_no']); ?></td>
                                <td><?php echo htmlspecialchars($r['event_title']); ?></td>
                                <td><span class="tag-badge"><?php echo htmlspecialchars($r['event_tag']); ?></span></td>
                                <td><?php echo htmlspecialchars($r['event_date']); ?></td>
                                <td><?php echo htmlspecialchars($r['registration_date']); ?></td>
                                <td><span class="status-badge"><?php echo htmlspecialchars($r['status']); ?></span></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else { ?>
                <div class="empty-text">No event registrations found yet. Use the form below to register a student for an event.</div>
            <?php } ?>

            <!-- QUICK EVENT REGISTRATION FORM -->
            <div class="form-inline-box">
                <h3 style="font-size: 15px; color: #1B1F44; margin-bottom: 10px;">
                    📝 Register a Student for an Event (PDO Prepared Statement Insert)
                </h3>
                <form method="POST" action="view_records.php">
                    <input type="hidden" name="action" value="register_event">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="student_id">Select Student:</label>
                            <select name="student_id" id="student_id" required>
                                <option value="">-- Choose Student --</option>
                                <?php foreach ($students as $s) { ?>
                                    <option value="<?php echo $s['student_id']; ?>">
                                        <?php echo htmlspecialchars($s['fullname'] . ' (' . $s['enrollment_no'] . ')'); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="event_id">Select Event:</label>
                            <select name="event_id" id="event_id" required>
                                <option value="">-- Choose Event --</option>
                                <?php foreach ($events as $e) { ?>
                                    <option value="<?php echo $e['event_id']; ?>">
                                        <?php echo htmlspecialchars($e['title'] . ' [' . $e['tag'] . ']'); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <div>
                            <button type="submit" class="btn-submit">Register for Event</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- SECTION 2: STUDENTS TABLE -->
        <div class="record-card">
            <h2>
                2. Students Table (`students`)
                <span class="badge"><?php echo count($students); ?> Students</span>
            </h2>
            <p style="font-size: 13px; color: #555; margin-bottom: 10px;">
                Populated via the <strong>Registration Form</strong> (<code>register.php</code>) using PDO Prepared Statements.
            </p>

            <?php if (count($students) > 0) { ?>
                <table class="db-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Enrollment No</th>
                            <th>Email Address</th>
                            <th>Mobile</th>
                            <th>Department</th>
                            <th>Semester</th>
                            <th>Joined On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s) { ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($s['student_id']); ?></td>
                                <td><strong><?php echo htmlspecialchars($s['fullname']); ?></strong></td>
                                <td><?php echo htmlspecialchars($s['enrollment_no']); ?></td>
                                <td><?php echo htmlspecialchars($s['email']); ?></td>
                                <td><?php echo htmlspecialchars($s['mobile']); ?></td>
                                <td><?php echo htmlspecialchars($s['department']); ?></td>
                                <td><?php echo htmlspecialchars($s['semester']); ?></td>
                                <td><?php echo htmlspecialchars($s['created_at']); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else { ?>
                <div class="empty-text">No students registered yet. <a href="register.php">Click here to register a student</a>.</div>
            <?php } ?>
        </div>

        <!-- SECTION 3: EVENTS TABLE -->
        <div class="record-card">
            <h2>
                3. Campus Events Table (`events`)
                <span class="badge"><?php echo count($events); ?> Events</span>
            </h2>
            <p style="font-size: 13px; color: #555; margin-bottom: 10px;">
                Stores university events, hackathons, and workshops available in StudentHub.
            </p>

            <?php if (count($events) > 0) { ?>
                <table class="db-table">
                    <thead>
                        <tr>
                            <th>Event ID</th>
                            <th>Category</th>
                            <th>Event Title</th>
                            <th>Date</th>
                            <th>Venue</th>
                            <th>Organizer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $e) { ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($e['event_id']); ?></td>
                                <td><span class="tag-badge"><?php echo htmlspecialchars($e['tag']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($e['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($e['event_date']); ?></td>
                                <td><?php echo htmlspecialchars($e['venue']); ?></td>
                                <td><?php echo htmlspecialchars($e['organizer']); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else { ?>
                <div class="empty-text">No events found in database.</div>
            <?php } ?>
        </div>

        <!-- ACTION BUTTONS -->
        <div style="text-align: center; margin-top: 25px;">
            <a href="register.php" style="display: inline-block; padding: 10px 20px; background-color: #1B1F44; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; margin-right: 10px;">+ New Student Registration</a>
            <a href="event.html" style="display: inline-block; padding: 10px 20px; background-color: orange; color: #1B1F44; text-decoration: none; border-radius: 5px; font-weight: bold;">View Events Catalog</a>
        </div>

    </div>

    <!-- HOME BUTTON AT BOTTOM RIGHT -->
    <a href="../index.php" class="home-btn">🏠 Home</a>

    <!-- FOOTER -->
    <footer>
        &copy; 2026 STUDENT HUB • CHARUSAT University • All Rights Reserved.
    </footer>

    <!-- SCRIPT -->
    <script src="../js/script.js"></script>
</body>
</html>
