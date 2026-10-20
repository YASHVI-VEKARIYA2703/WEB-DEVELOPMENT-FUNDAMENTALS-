# STUDENT HUB - Web Development Framework (WDF)

## Practical 12: Event Management CRUD with Poster Upload and File Validation

### Problem Definition
> **Develop event management functionality for admin users. Admin can add, view, update, delete, and upload event posters with file type and file size validation.**

---

### Practical 12 Implementation Highlights

1. **Event Management Module (`pages/manage_events.php`)**:
   - Dedicated management dashboard for admin users.
   - Dual view modes: Interactive **Data Table View** and responsive **Card Grid View**.
   - Integrated also directly into the **Administrator Control Panel** (`pages/admin_dashboard.php` -> Tab 2: Events & Registrations).
   - Real-time search by title, venue, organizer, and description + Category and Poster Status filters.

2. **Poster Upload & Strict File Validation**:
   - **File Size Validation**: Maximum file size limited to **2 MB** (2,097,152 bytes). Files exceeding this limit are rejected with a clear error message.
   - **File Type Validation**: Only allowed image formats are accepted: `.jpg`, `.jpeg`, `.png`, `.webp`, `.gif`. Disallowed file types (e.g. `.php`, `.exe`, `.pdf`) are strictly blocked.
   - **MIME Type & Content Verification**: Checks image MIME type via `finfo_file` / `mime_content_type` and verifies binary image integrity via `getimagesize()`.
   - **Secure Storage**: Files are saved with collision-free unique names (`event_{timestamp}_{random}.{ext}`) in `uploads/events/` with safe file permissions.

3. **Event CRUD Operations via MySQLi Prepared Statements**:
   - **Add Event (`action=add`)**: Inserts new event record with optional/validated poster into MySQL.
   - **View Event (`👁️ View`)**: Displays complete event details, registrations, and high-resolution poster image preview in a responsive modal dossier.
   - **Edit Event (`action=edit`)**: Allows updating title, category, date, venue, organizer, and description. Supports retaining existing poster or uploading a replacement (old file on disk is safely unlinked).
   - **Delete Event (`action=delete`)**: Permanently removes event record and associated registrations from MySQL via cascade, and safely deletes the uploaded poster file from the server.

---

## Practical 11: Student Management Module (Add, View, Edit, Delete, Search & Filter)

### Problem Definition
> **Create a student management module to add, view, edit, delete, search and filter student records. All database operations must use MYSQLi statements and clear success/failure messages.**

---

### Implementation Details

1. **Student Management Module (`pages/manage_students.php`)**:
   - Dedicated management interface styled consistently with the StudentHub design language (`#1B1F44` navy, orange accents, responsive card layout).
   - Provides full CRUD capability with clear success / failure messages.
   - Also integrated directly into the Administrator Control Panel (`pages/admin_dashboard.php`).

2. **Add Student Record (`action=add`)**:
   - Collects Full Name, Enrollment No, Email Address, Mobile Number, Department, Semester, and Password.
   - Frontend and backend validation for email format, 10-digit mobile number, and minimum password length.
   - Duplicate checks for Enrollment Number and Email using MySQLi Prepared Statements:
     ```php
     $checkStmt = mysqli_prepare($conn, "SELECT student_id, enrollment_no, email FROM students WHERE enrollment_no = ? OR email = ?");
     mysqli_stmt_bind_param($checkStmt, "ss", $enrollment, $email);
     ```
   - Password hashed using bcrypt `password_hash($password, PASSWORD_DEFAULT)`.
   - Record inserted via MySQLi Prepared Statement:
     ```php
     $insertStmt = mysqli_prepare($conn, "INSERT INTO students (fullname, enrollment_no, email, mobile, department, semester, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, 'student')");
     mysqli_stmt_bind_param($insertStmt, "sssssss", $fullname, $enrollment, $email, $mobile, $department, $semester, $passHash);
     ```
   - Displays clear success message on insert or specific failure message on validation/duplicate error.

3. **View Student Records**:
   - Responsive table display with student ID, name with initials avatar, enrollment code, email, mobile, department badge, semester, and active status.
   - Interactive Profile Dossier modal (`👁️ View`) displaying complete student credentials, registration date, and direct email link.
   - All records fetched via MySQLi Prepared Statements.

4. **Edit Student Record (`action=edit`)**:
   - Interactive Edit modal pre-populated with student details.
   - Allows updating Full Name, Enrollment No, Email, Mobile, Department, and Semester.
   - Optional password reset field (leave blank to preserve existing password).
   - Validates uniqueness of updated enrollment and email against all other student records:
     ```php
     $dupStmt = mysqli_prepare($conn, "SELECT student_id FROM students WHERE (enrollment_no = ? OR email = ?) AND student_id != ?");
     mysqli_stmt_bind_param($dupStmt, "ssi", $enrollment, $email, $studentId);
     ```
   - Executes update via MySQLi Prepared Statement:
     ```php
     $updateStmt = mysqli_prepare($conn, "UPDATE students SET fullname = ?, enrollment_no = ?, email = ?, mobile = ?, department = ?, semester = ? WHERE student_id = ? AND role != 'admin'");
     ```
   - Displays clear success/failure feedback banner.

5. **Delete Student Record (`action=delete`)**:
   - Confirm deletion modal displaying student name and enrollment number to prevent accidental deletion.
   - Protects administrative accounts from deletion (`role != 'admin'`).
   - Deletes record using MySQLi Prepared Statement:
     ```php
     $delStmt = mysqli_prepare($conn, "DELETE FROM students WHERE student_id = ? AND role != 'admin'");
     mysqli_stmt_bind_param($delStmt, "i", $studentId);
     ```
   - Foreign key `ON DELETE CASCADE` automatically removes related registrations without SQL constraint violations.
   - Displays clear success/failure notification.

6. **Search & Filter Functionality**:
   - **Search**: Real-time interactive instant search across student name, enrollment ID, email, and mobile + parameterized server-side search via MySQLi (`LIKE ?`).
   - **Department Filter**: Dropdown filter for Computer Engineering, IT, CSE, EC, Mechanical, Civil, or All.
   - **Semester Filter**: Dropdown filter for Semester 1 through Semester 8.
   - Real-time display counter showing matching vs. total student records.
   - Reset button to quickly clear active search and filter conditions.

7. **Clear Success & Failure Messages**:
   - Success alerts rendered in green with confirmation details (`alert-box success`).
   - Error/Failure alerts rendered in red with specific reason for error (`alert-box error`).
   - Dismissible with close button.

---

### Demo Test Credentials

| Role | Enrollment No / ID | Email | Password | Destination |
| :--- | :--- | :--- | :--- | :--- |
| **Student** | `25DCE122` | `yashvi@charusat.edu.in` | `password123` | `dashboard.php` |
| **Student** | `25it044` | `priya@charusat.edu.in` | `priya123` | `dashboard.php` |
| **Admin** | `ADMIN001` | `admin@charusat.edu.in` | `admin123` | `admin_dashboard.php` |

---

### How to Run on XAMPP

1. Ensure this project folder is inside your XAMPP `htdocs` directory:
   ```
   C:\xampp\htdocs\WDF\student hub prec 12\
   ```
2. Open the **XAMPP Control Panel** and start both **Apache** and **MySQL**.
3. Open your web browser and navigate to:
   - **Event Management Module (Practical 12)**:
     ```
     http://localhost/WDF/student%20hub%20prec%2012/pages/manage_events.php
     ```
   - **Administrator Control Panel** (Events & Students Tabs):
     ```
     http://localhost/WDF/student%20hub%20prec%2012/pages/admin_dashboard.php
     ```
   - **Student Management Module**:
     ```
     http://localhost/WDF/student%20hub%20prec%2012/pages/manage_students.php
     ```
   - **Home Page**:
     ```
     http://localhost/WDF/student%20hub%20prec%2012/index.php
     ```
   - **Login Page**:
     ```
     http://localhost/WDF/student%20hub%20prec%2012/pages/login.php
     ```
   - **Student Dashboard**:
     ```
     http://localhost/WDF/student%20hub%20prec%2012/pages/dashboard.php
     ```
   - **View Database Records**:
     ```
     http://localhost/WDF/student%20hub%20prec%2012/pages/view_records.php
     ```
