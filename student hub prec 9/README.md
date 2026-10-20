# STUDENT HUB - Web Development Framework (WDF)

## Practical 9: Secure User Registration with Database Insert, Duplicate Email Check, and Password Hashing

### Problem Definition
> **Implement secure user registration by inserting user details into MySQL using MYSQLi. Validate input on frontend and backend, check duplicate email/username, hash passwords using password_hash(), and display appropriate messages.**

---

### Implementation Details

1. **Frontend Validation (`pages/register.php` & `js/script.js`)**:
   - Validates user input in real-time and on form submit before sending data to server.
   - **Full Name**: Required, letters and spaces only, no digits.
   - **Enrollment No**: Required, non-empty.
   - **Email Address**: Required, standard email regex format (`user@domain.ext`).
   - **Mobile Number**: Required, exactly 10 digits.
   - **Password**: Required, minimum 6 characters.
   - **Confirm Password**: Required, must match entered password.
   - Displays clear red error feedback under input fields and prevents form submission if invalid.

2. **Database Connection using MySQLi (`pages/db.php`)**:
   - Connects to MySQL using **MySQLi** (`mysqli_connect()`).
   - Checks connection status with `mysqli_connect_error()`.
   - Automatically creates the `studenthub` database and required tables (`students`, `events`, `registrations`) if they do not exist.
   - Provides smooth beginner-friendly database setup.

3. **Backend Validation, Duplicate Check & Same-Page Popup Modal (`pages/register.php`)**:
   - Sanitizes and validates all incoming POST parameters on the server side.
   - **Duplicate Email Check**:
     Executes a MySQLi prepared statement (`SELECT student_id FROM students WHERE email = ?`) to check if the email is already in the database.
   - **Duplicate Enrollment / Username Check**:
     Executes a MySQLi prepared statement (`SELECT student_id FROM students WHERE enrollment_no = ?`) to check if the enrollment number is already registered.
   - **Same-Page Feedback Popup Modal**:
     - When registration is successful, displays a success popup modal on the same registration page confirming database insert with the assigned Student ID.
     - When registration fails (e.g. duplicate email, duplicate enrollment, or invalid inputs), displays an error popup modal on the same registration page detailing the errors and preserving entered values.

4. **Secure Password Hashing (`password_hash()`)**:
   - Secures student passwords using PHP's standard `password_hash($password, PASSWORD_DEFAULT)` function before storing them into MySQL.
   - Ensures raw passwords are never saved in plaintext in the database.

5. **Secure Record Insertion with MySQLi Prepared Statements**:
   - Uses parameterized MySQLi Prepared Statements to prevent SQL injection:
     ```php
     $insertSql = "INSERT INTO students (fullname, enrollment_no, email, mobile, department, semester, password) VALUES (?, ?, ?, ?, ?, ?, ?)";
     $stmt = mysqli_prepare($conn, $insertSql);
     mysqli_stmt_bind_param($stmt, "sssssss", $name, $enrollment, $email, $mobile, $department, $semester, $hashedPassword);
     mysqli_stmt_execute($stmt);
     ```
   - Retrieves the auto-generated student ID with `mysqli_insert_id($conn)`.
   - Maintains CSV (`DATA/registration.csv`) and JSON (`DATA/registration.json`) backups.

---

### How to Run on XAMPP

1. Ensure this project folder is inside your XAMPP `htdocs` directory:
   ```
   C:\xampp\htdocs\WDF\student hub prec 9\
   ```
2. Open the **XAMPP Control Panel** and start both **Apache** and **MySQL**.
3. Open your web browser and navigate to:
   - **Home Page**:
     ```
     http://localhost/WDF/student%20hub%20prec%209/index.php
     ```
   - **Student Registration**:
     ```
     http://localhost/WDF/student%20hub%20prec%209/pages/register.php
     ```
   - **View Database Records**:
     ```
     http://localhost/WDF/student%20hub%20prec%209/pages/view_records.php
     ```
