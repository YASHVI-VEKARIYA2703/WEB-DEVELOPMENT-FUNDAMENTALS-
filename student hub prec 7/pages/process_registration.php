<?php
// ==========================================================================
// Practical 7: Process Submitted Form Data using PHP
// Problem Definition:
// Process submitted registration/contact form data using PHP. Validate and
// sanitize inputs on the server side and store records in CSV or JSON file
// format. Display success/error messages.
// ==========================================================================

$errors = [];
$success = false;
$submitted = false;

// Step 1: Check if form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submitted = true;

    // Step 2: Sanitize inputs on the server side
    // Using trim and strip_tags to remove unwanted whitespace and HTML tags
    $name       = isset($_POST["fullname"]) ? trim(strip_tags($_POST["fullname"])) : "";
    $enrollment = isset($_POST["enrollment"]) ? trim(strip_tags($_POST["enrollment"])) : "";
    $email      = isset($_POST["email"]) ? filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL) : "";
    $mobile     = isset($_POST["mobile"]) ? trim(strip_tags($_POST["mobile"])) : "";
    $department = isset($_POST["department"]) ? trim(strip_tags($_POST["department"])) : "";
    $semester   = isset($_POST["semester"]) ? trim(strip_tags($_POST["semester"])) : "";
    $password   = isset($_POST["password"]) ? $_POST["password"] : "";
    $cpassword  = isset($_POST["cpassword"]) ? $_POST["cpassword"] : "";

    // Step 3: Validate inputs on the server side

    // Full Name validation (required and letters with spaces only)
    if (empty($name)) {
        $errors[] = "Full Name is required.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $name)) {
        $errors[] = "Full Name should only contain alphabets and spaces.";
    }

    // Enrollment number validation
    if (empty($enrollment)) {
        $errors[] = "Enrollment Number is required.";
    }

    // Email validation
    if (empty($email)) {
        $errors[] = "Email Address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    // Mobile Number validation (must be 10 digits)
    if (empty($mobile)) {
        $errors[] = "Mobile Number is required.";
    } elseif (!preg_match("/^[0-9]{10}$/", $mobile)) {
        $errors[] = "Mobile Number must be exactly 10 digits.";
    }

    // Department validation
    $validDepartments = [
        "Computer Engineering",
        "Information Technology",
        "Computer Science & Engineering",
        "Electronics & Communication"
    ];
    if (empty($department) || !in_array($department, $validDepartments)) {
        $errors[] = "Please select a valid department.";
    }

    // Semester validation
    $validSemesters = [
        "Semester 1", "Semester 2", "Semester 3", "Semester 4",
        "Semester 5", "Semester 6", "Semester 7", "Semester 8"
    ];
    if (empty($semester) || !in_array($semester, $validSemesters)) {
        $errors[] = "Please select a valid semester.";
    }

    // Password validation (min 6 characters)
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }

    // Confirm Password validation
    if ($password !== $cpassword) {
        $errors[] = "Password and Confirm Password do not match.";
    }

    // Step 4: Store records in CSV and JSON file format if validation succeeds
    if (empty($errors)) {
        $regTime = date("Y-m-d H:i:s");

        // --- A. Store in CSV Format ---
        $csvFile = __DIR__ . "/../DATA/registration.csv";
        $isNewFile = !file_exists($csvFile) || filesize($csvFile) === 0;

        $fp = fopen($csvFile, "a");
        if ($fp !== false) {
            // Write column headers if new file
            if ($isNewFile) {
                fputcsv($fp, [
                    "Full Name",
                    "Enrollment No",
                    "Email",
                    "Mobile",
                    "Department",
                    "Semester",
                    "Registration Time"
                ]);
            }

            // Write registration record
            fputcsv($fp, [
                $name,
                $enrollment,
                $email,
                $mobile,
                $department,
                $semester,
                $regTime
            ]);
            fclose($fp);

            // --- B. Store in JSON Format ---
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
                "fullname"          => $name,
                "enrollment"        => $enrollment,
                "email"             => $email,
                "mobile"            => $mobile,
                "department"        => $department,
                "semester"          => $semester,
                "registration_time" => $regTime
            ];

            file_put_contents($jsonFile, json_encode($allRecords, JSON_PRETTY_PRINT));

            $success = true;
        } else {
            $errors[] = "Unable to open DATA/registration.csv file for writing.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Status - Student Hub</title>
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
        /* Specific status styling matching Student Hub theme */
        .status-container {
            width: 550px;
            max-width: 90%;
            margin: 25px auto 50px;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-top: 5px solid #1B1F44;
        }

        .alert-box {
            padding: 14px 18px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 15px;
            line-height: 1.5;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0 25px;
            font-size: 14px;
        }

        .details-table th,
        .details-table td {
            padding: 10px 12px;
            border: 1px solid #e0e0e0;
            text-align: left;
        }

        .details-table th {
            background-color: #f1f5f9;
            color: #1B1F44;
            width: 38%;
            font-weight: bold;
        }

        .details-table td {
            color: #333333;
            background-color: #ffffff;
        }

        .btn-action {
            display: inline-block;
            padding: 10px 20px;
            background-color: #1B1F44;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            font-size: 14px;
            transition: 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-action:hover {
            background-color: orange;
            color: #1B1F44;
        }

        .btn-secondary {
            background-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            color: white;
        }

        .error-list {
            margin: 10px 0 0 20px;
            padding: 0;
        }

        .error-list li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body class="register-body">

    <!-- HEADER -->
    <iframe
        src="header.html"
        class="header-frame">
    </iframe>

    <h1 class="page-title">REGISTRATION STATUS</h1>

    <!-- NAVIGATION -->
    <nav>
        <a href="dashboard.html">DASHBOARD</a>
        <a href="event.html">EVENTS</a>
        <a href="course.html">COURSES</a>
        <a href="register.php" class="active">REGISTER</a>
        <a href="login.html">LOGIN</a>
        <a href="contact.html">CONTACT</a>
    </nav>

    <!-- PROBLEM DEFINITION BANNER (PRACTICAL 7) -->
    <div style="width: 550px; max-width: 90%; margin: 20px auto 0; background-color: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 12px 16px; border-radius: 8px; font-size: 13px; line-height: 1.5; text-align: left; box-shadow: 0 2px 6px rgba(0,0,0,0.08);">
        <b>📌 Practical 7 - Problem Definition:</b><br>
        Process submitted registration/contact form data using PHP. Validate and sanitize inputs on the server side and store records in CSV or JSON file format. Display success/error messages.
    </div>

    <!-- MAIN STATUS CONTAINER -->
    <div class="status-container">

        <?php if (!$submitted) { ?>
            <!-- IF ACCESSED DIRECTLY WITHOUT SUBMITTING -->
            <div class="alert-box alert-error" style="text-align: center;">
                <strong>⚠️ Notice:</strong> No form data was submitted. Please complete and submit the registration form.
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="register.php" class="btn-action">Go to Registration Form</a>
            </div>

        <?php } elseif ($success) { ?>
            <!-- STEP 5A: DISPLAY SUCCESS MESSAGE AND RECORD SUMMARY -->
            <h2 style="text-align: center; color: #1B1F44; margin-bottom: 15px;">
                Registration Completed
            </h2>

            <div class="alert-box alert-success">
                <strong>✅ Success!</strong> Form submitted successfully. Data has been validated, sanitized, and stored into <code>DATA/registration.csv</code> and <code>DATA/registration.json</code>.
            </div>

            <h3 style="color: #1B1F44; font-size: 16px; margin-bottom: 10px; border-bottom: 2px solid orange; padding-bottom: 5px;">
                Submitted Student Information
            </h3>

            <table class="details-table">
                <tr>
                    <th>Full Name:</th>
                    <td><?php echo htmlspecialchars($name, ENT_QUOTES, "UTF-8"); ?></td>
                </tr>
                <tr>
                    <th>Enrollment No:</th>
                    <td><?php echo htmlspecialchars($enrollment, ENT_QUOTES, "UTF-8"); ?></td>
                </tr>
                <tr>
                    <th>Email Address:</th>
                    <td><?php echo htmlspecialchars($email, ENT_QUOTES, "UTF-8"); ?></td>
                </tr>
                <tr>
                    <th>Mobile Number:</th>
                    <td><?php echo htmlspecialchars($mobile, ENT_QUOTES, "UTF-8"); ?></td>
                </tr>
                <tr>
                    <th>Department:</th>
                    <td><?php echo htmlspecialchars($department, ENT_QUOTES, "UTF-8"); ?></td>
                </tr>
                <tr>
                    <th>Semester:</th>
                    <td><?php echo htmlspecialchars($semester, ENT_QUOTES, "UTF-8"); ?></td>
                </tr>
                <tr>
                    <th>Storage Format:</th>
                    <td>CSV (<code>DATA/registration.csv</code>) &amp; JSON (<code>DATA/registration.json</code>)</td>
                </tr>
                <tr>
                    <th>Submission Time:</th>
                    <td><?php echo htmlspecialchars($regTime, ENT_QUOTES, "UTF-8"); ?></td>
                </tr>
            </table>

            <div style="text-align: center; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                <a href="register.php" class="btn-action">Register Another Student</a>
                <a href="login.html" class="btn-action" style="background-color: orange; color: #1B1F44;">Proceed to Login</a>
            </div>

        <?php } else { ?>
            <!-- STEP 5B: DISPLAY VALIDATION ERROR MESSAGES -->
            <h2 style="text-align: center; color: #dc3545; margin-bottom: 15px;">
                Registration Failed
            </h2>

            <div class="alert-box alert-error">
                <strong>❌ Error:</strong> Please correct the following issues and resubmit:
                <ul class="error-list">
                    <?php foreach ($errors as $error) { ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?></li>
                    <?php } ?>
                </ul>
            </div>

            <div style="text-align: center; margin-top: 25px;">
                <a href="javascript:history.back()" class="btn-action">← Go Back &amp; Correct Form</a>
            </div>

        <?php } ?>

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