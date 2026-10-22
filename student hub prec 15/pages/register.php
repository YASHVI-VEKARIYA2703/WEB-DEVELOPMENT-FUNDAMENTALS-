<?php
// ==========================================================================
// Practical 9: Secure User Registration with Database Insert, Duplicate Email Check, and Password Hashing
// Problem Definition:
// Implement secure user registration by inserting user details into MySQL
// using MYSQLi. Validate input on frontend and backend, check duplicate
// email/username, hash passwords using password_hash(), and display
// appropriate messages.
// ==========================================================================

require_once __DIR__ . "/auth.php";

// If already logged in, redirect based on role
if (is_logged_in()) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$popupType    = "";
$popupTitle   = "";
$popupMessage = "";
$popupErrors  = [];

$name       = "";
$enrollment = "";
$email      = "";
$mobile     = "";
$department = "Computer Engineering";
$semester   = "Semester 3";

// Process form submission on the SAME page
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // 1. Sanitize inputs on the server side
    $name       = isset($_POST["fullname"]) ? trim(strip_tags($_POST["fullname"])) : "";
    $enrollment = isset($_POST["enrollment"]) ? trim(strip_tags($_POST["enrollment"])) : "";
    $email      = isset($_POST["email"]) ? filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL) : "";
    $mobile     = isset($_POST["mobile"]) ? trim(strip_tags($_POST["mobile"])) : "";
    $department = isset($_POST["department"]) ? trim(strip_tags($_POST["department"])) : "Computer Engineering";
    $semester   = isset($_POST["semester"]) ? trim(strip_tags($_POST["semester"])) : "Semester 3";
    $password   = isset($_POST["password"]) ? $_POST["password"] : "";
    $cpassword  = isset($_POST["cpassword"]) ? $_POST["cpassword"] : "";

    $errors = [];

    // 2. Validate inputs on the server side (Backend Validation)
    if (empty($name)) {
        $errors[] = "Full Name is required.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $name)) {
        $errors[] = "Full Name should only contain letters and spaces.";
    }

    if (empty($enrollment)) {
        $errors[] = "Enrollment Number is required.";
    }

    if (empty($email)) {
        $errors[] = "Email Address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (empty($mobile)) {
        $errors[] = "Mobile Number is required.";
    } elseif (!preg_match("/^[0-9]{10}$/", $mobile)) {
        $errors[] = "Mobile Number must be exactly 10 digits.";
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }

    if ($password !== $cpassword) {
        $errors[] = "Password and Confirm Password do not match.";
    }

    // 3. Duplicate Checks & Database Insert using MySQLi
    if (empty($errors)) {

        // Duplicate Email Check using MySQLi Prepared Statement
        $checkEmailSql = "SELECT student_id FROM students WHERE email = ?";
        $emailStmt = mysqli_prepare($conn, $checkEmailSql);
        if ($emailStmt) {
            mysqli_stmt_bind_param($emailStmt, "s", $email);
            mysqli_stmt_execute($emailStmt);
            mysqli_stmt_store_result($emailStmt);
            if (mysqli_stmt_num_rows($emailStmt) > 0) {
                $errors[] = "Email Address is already registered. Please use another email.";
            }
            mysqli_stmt_close($emailStmt);
        }

        // Duplicate Enrollment / Username Check using MySQLi Prepared Statement
        $checkEnrollSql = "SELECT student_id FROM students WHERE enrollment_no = ?";
        $enrollStmt = mysqli_prepare($conn, $checkEnrollSql);
        if ($enrollStmt) {
            mysqli_stmt_bind_param($enrollStmt, "s", $enrollment);
            mysqli_stmt_execute($enrollStmt);
            mysqli_stmt_store_result($enrollStmt);
            if (mysqli_stmt_num_rows($enrollStmt) > 0) {
                $errors[] = "Enrollment Number is already registered. Please check and try again.";
            }
            mysqli_stmt_close($enrollStmt);
        }

        // If no duplicates, hash password and insert into MySQL database
        if (empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $insertSql = "INSERT INTO students (fullname, enrollment_no, email, mobile, department, semester, password) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insertSql);

            if ($stmt) {
                mysqli_stmt_bind_param(
                    $stmt,
                    "sssssss",
                    $name,
                    $enrollment,
                    $email,
                    $mobile,
                    $department,
                    $semester,
                    $hashedPassword
                );

                if (mysqli_stmt_execute($stmt)) {
                    $newId = mysqli_insert_id($conn);
                    $popupType    = "success";
                    $popupTitle   = "Registration Successful";
                    $popupMessage = "Registration Successful!";

                    // Backup to CSV and JSON files
                    $regTime = date("Y-m-d H:i:s");
                    $csvFile = __DIR__ . "/../DATA/registration.csv";
                    $isNewFile = !file_exists($csvFile) || filesize($csvFile) === 0;
                    $fp = fopen($csvFile, "a");
                    if ($fp !== false) {
                        if ($isNewFile) {
                            fputcsv($fp, ["Full Name", "Enrollment No", "Email", "Mobile", "Department", "Semester", "Registration Time"]);
                        }
                        fputcsv($fp, [$name, $enrollment, $email, $mobile, $department, $semester, $regTime]);
                        fclose($fp);
                    }

                    $jsonFile = __DIR__ . "/../DATA/registration.json";
                    $allRecords = [];
                    if (file_exists($jsonFile) && filesize($jsonFile) > 0) {
                        $jsonContent = file_get_contents($jsonFile);
                        $decoded = json_decode($jsonContent, true);
                        if (is_array($decoded)) {
                            $allRecords = $decoded;
                        }
                    }
                    $allRecords[] = [
                        "student_id"        => $newId,
                        "fullname"          => $name,
                        "enrollment"        => $enrollment,
                        "email"             => $email,
                        "mobile"            => $mobile,
                        "department"        => $department,
                        "semester"          => $semester,
                        "registration_time" => $regTime
                    ];
                    file_put_contents($jsonFile, json_encode($allRecords, JSON_PRETTY_PRINT));

                    // Reset form values on success
                    $name = $enrollment = $email = $mobile = "";
                } else {
                    $popupType    = "error";
                    $popupTitle   = "Registration Failed";
                    $popupMessage = "Database Insert Error: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $popupType    = "error";
                $popupTitle   = "Registration Failed";
                $popupMessage = "Database Query Error: " . mysqli_error($conn);
            }
        } else {
            $popupType    = "error";
            $popupTitle   = "Registration Failed";
            $popupMessage = "Duplicate record detected:";
            $popupErrors  = $errors;
        }
    } else {
        $popupType    = "error";
        $popupTitle   = "Registration Failed";
        $popupMessage = "Please correct the following errors:";
        $popupErrors  = $errors;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration - Student Hub</title>
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
        /* Modal Popup Styles matching Student Hub theme */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.55);
        }

        .modal-content {
            background-color: #ffffff;
            margin: 12% auto;
            border-radius: 8px;
            width: 480px;
            max-width: 90%;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35);
            border-top: 5px solid #1B1F44;
            overflow: hidden;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            padding: 14px 20px;
            background-color: #1B1F44;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            color: #ffd700;
        }

        .modal-close {
            color: white;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .modal-close:hover {
            color: orange;
        }

        .modal-body {
            padding: 20px;
            font-size: 14px;
            line-height: 1.7;
            color: #333333;
        }

        .modal-footer {
            padding: 12px 20px;
            background-color: #f1f5f9;
            text-align: right;
        }

        .modal-btn-close {
            padding: 8px 18px;
            background-color: #1B1F44;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: 0.3s;
        }

        .modal-btn-close:hover {
            background-color: orange;
            color: #1B1F44;
        }

        /* Dark mode compatibility */
        body.dark-mode .modal-content {
            background-color: #2b2b2b;
            color: #f1f1f1;
        }
        body.dark-mode .modal-body {
            color: #e0e0e0;
        }
        body.dark-mode .modal-footer {
            background-color: #1e1e1e;
        }
    </style>
</head>
<body class="register-body">

    <!-- HEADER -->
    <iframe
        src="header.html"
        class="header-frame">
    </iframe>

    <h1 class="page-title">STUDENT REGISTRATION</h1>

    <!-- NAVIGATION -->
    <nav>
        <a href="dashboard.php">DASHBOARD</a>
        <a href="event.html">EVENTS</a>
        <a href="course.html">COURSES</a>
        <a href="register.php" class="active">REGISTER</a>
        <a href="login.php">LOGIN</a>
        <a href="contact.html">CONTACT</a>
    </nav>
    
    <!-- REGISTRATION FORM BOX -->
    <div class="form-box">
        <h2>Create Account</h2>

        <div id="successBanner" class="success-banner"></div>

        <form id="registerForm" action="register.php" method="POST">
            <label for="fullname">Full Name:</label>
            <input type="text" id="fullname" name="fullname" placeholder="Enter Full Name" value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" required>
            <span class="error-message" id="fullname-error"></span>

            <label for="enrollment">Enrollment No:</label>
            <input type="text" id="enrollment" name="enrollment" placeholder="Enter Enrollment No" value="<?php echo htmlspecialchars($enrollment, ENT_QUOTES, 'UTF-8'); ?>" required>
            <span class="error-message" id="enrollment-error"></span>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" placeholder="Enter Email Address" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
            <span class="error-message" id="email-error"></span>

            <label for="mobile">Mobile Number:</label>
            <input type="tel" id="mobile" name="mobile" placeholder="Enter Mobile Number" value="<?php echo htmlspecialchars($mobile, ENT_QUOTES, 'UTF-8'); ?>" required>
            <span class="error-message" id="mobile-error"></span>

            <label for="department">Department:</label>
            <select id="department" name="department">
                <option <?php echo ($department === 'Computer Engineering') ? 'selected' : ''; ?>>Computer Engineering</option>
                <option <?php echo ($department === 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                <option <?php echo ($department === 'Computer Science & Engineering') ? 'selected' : ''; ?>>Computer Science &amp; Engineering</option>
                <option <?php echo ($department === 'Electronics & Communication') ? 'selected' : ''; ?>>Electronics &amp; Communication</option>
            </select>

            <label for="semester">Semester:</label>
            <select id="semester" name="semester">
                <option <?php echo ($semester === 'Semester 1') ? 'selected' : ''; ?>>Semester 1</option>
                <option <?php echo ($semester === 'Semester 2') ? 'selected' : ''; ?>>Semester 2</option>
                <option <?php echo ($semester === 'Semester 3') ? 'selected' : ''; ?>>Semester 3</option>
                <option <?php echo ($semester === 'Semester 4') ? 'selected' : ''; ?>>Semester 4</option>
                <option <?php echo ($semester === 'Semester 5') ? 'selected' : ''; ?>>Semester 5</option>
                <option <?php echo ($semester === 'Semester 6') ? 'selected' : ''; ?>>Semester 6</option>
                <option <?php echo ($semester === 'Semester 7') ? 'selected' : ''; ?>>Semester 7</option>
                <option <?php echo ($semester === 'Semester 8') ? 'selected' : ''; ?>>Semester 8</option>
            </select>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" placeholder="Enter Password (min. 6 characters)" required>
            <span class="error-message" id="password-error"></span>

            <label for="cpassword">Confirm Password:</label>
            <input type="password" id="cpassword" name="cpassword" placeholder="Re-enter Password" required>
            <span class="error-message" id="cpassword-error"></span>

            <input type="submit" value="Register Now">
            <input type="reset" value="Clear Form">

            <p style="text-align: center; margin-top: 15px; font-size: 14px;">
                Already registered? <a href="login.php" style="color: blue; font-weight: bold;">Login here</a> | 
                <a href="view_records.php" style="color: #1B1F44; font-weight: bold;">View Database Records</a>
            </p>
        </form>
    </div>

    <!-- POPUP MODAL (PRACTICAL 9: SAME PAGE REGISTRATION POPUP) -->
    <?php if (!empty($popupType)) { ?>
    <div id="statusModal" class="modal" style="display: block;">
        <div class="modal-content">
            <div class="modal-header" style="<?php echo ($popupType === 'success') ? 'background-color: #1B1F44;' : 'background-color: #dc3545;'; ?>">
                <h3><?php echo ($popupType === 'success') ? '✅ ' . htmlspecialchars($popupTitle) : '❌ ' . htmlspecialchars($popupTitle); ?></h3>
                <span class="modal-close" onclick="closeStatusModal()">&times;</span>
            </div>
            <div class="modal-body">
                <?php if ($popupType === 'success') { ?>
                    <p style="color: #155724; font-weight: bold; font-size: 16px; margin: 15px 0; text-align: center;">
                        🎉 <?php echo htmlspecialchars($popupMessage, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                <?php } else { ?>
                    <p style="color: #721c24; font-weight: bold; font-size: 15px; margin-bottom: 10px;">
                        ⚠️ <?php echo htmlspecialchars($popupMessage, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <?php if (!empty($popupErrors)) { ?>
                        <ul style="color: #721c24; margin-left: 20px; font-size: 14px; margin-top: 5px;">
                            <?php foreach ($popupErrors as $err) { ?>
                                <li><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                <?php } ?>
            </div>
            <div class="modal-footer" style="<?php echo ($popupType === 'success') ? 'display: flex; justify-content: center; gap: 12px;' : ''; ?>">
                <?php if ($popupType === 'success') { ?>
                    <a href="login.php" class="modal-btn-close" style="text-decoration: none; display: inline-block;">Go to Login</a>
                    <a href="../index.php" class="modal-btn-close" style="text-decoration: none; display: inline-block; background-color: orange; color: #1B1F44;">Go to Home</a>
                <?php } else { ?>
                    <button type="button" class="modal-btn-close" onclick="closeStatusModal()">Try Again</button>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php } ?>

    <!-- HOME BUTTON AT BOTTOM RIGHT -->
    <a href="../index.php" class="home-btn">🏠 Home</a>

    <!-- FOOTER -->
    <footer>
        &copy; 2026 STUDENT HUB • CHARUSAT University • All Rights Reserved.
    </footer>

    <!-- SCRIPT -->
    <script src="../js/script.js"></script>
    <script>
        // Modal popup close function
        function closeStatusModal() {
            var modal = document.getElementById("statusModal");
            if (modal) {
                modal.style.display = "none";
            }
        }

        // Close modal if user clicks outside of modal content
        window.addEventListener("click", function (event) {
            var modal = document.getElementById("statusModal");
            if (event.target === modal) {
                closeStatusModal();
            }
        });
    </script>
</body>
</html>