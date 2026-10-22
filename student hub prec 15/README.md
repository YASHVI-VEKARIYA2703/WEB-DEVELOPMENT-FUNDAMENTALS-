# STUDENT HUB - Web Development Framework (WDF)

## Practical 15: Single Deployable StudentHub Portal Integration & Deployment

### Problem Definition
> **Integrate all modules into a single deployable StudentHub portal. Verify navigation, responsiveness, authentication, database operations, security checks, AJAX functionality, and documentation. Deploy locally using XAMPP/Apache virtual host and submit GitHub repository.**

---

### Project Architecture & Integrated Modules

StudentHub integrates all university academic, student, and administrative modules into a single cohesive, deployable web portal:

```
student hub prec 15/
│
├── index.php                      # Main integrated landing portal (session & role adaptive)
├── index.html                     # Backward-compatible redirect to index.php
├── studenthub.sql                 # Complete database schema, relationships & seed data
├── studenthub-vhost.conf          # Apache Virtual Host configuration for studenthub.local
├── README.md                      # Complete project documentation & deployment guide
│
├── api/                           # Asynchronous JSON REST API Endpoints
│   ├── students.php               # Async student CRUD API (GET, POST, UPDATE, DELETE)
│   └── events.php                 # Async event listing and details API
│
├── CSS/
│   └── style.css                  # Unified responsive stylesheet (#1B1F44 Navy & Orange)
│
├── js/
│   └── script.js                  # Frontend scripts, validation, dynamic nav, theme toggle
│
├── pages/                         # Core Portal Modules
│   ├── header.html                # Reusable university brand banner with logo & NAAC badge
│   ├── auth.php                   # Authentication middleware, session security & RBAC
│   ├── db.php                     # MySQLi & PDO connection with auto-table initialization
│   ├── session_status.php         # Lightweight session status check for dynamic navbar
│   │
│   ├── login.php                  # Secure student/admin login with remember-me & timeout
│   ├── register.php               # Student self-registration with duplicate checks & hashing
│   ├── logout.php                 # Secure session destruction & cookie revocation
│   │
│   ├── dashboard.php              # Authenticated student dashboard with quick academic cards
│   ├── admin_dashboard.php        # Administrator control panel (tabs for all management)
│   ├── manage_students.php        # Async Student Management CRUD module via Fetch API
│   ├── manage_events.php          # Admin Event CRUD with poster upload & file validation
│   ├── view_records.php           # Database records viewer & event registrations
│   │
│   ├── attendence.html            # Semester lecture & lab attendance tracker
│   ├── result.html                # Academic grade reports, SGPA, & CGPA records
│   ├── fees.html                  # Fee receipts, payment status & scholarship details
│   ├── timetable.html             # Weekly class schedule and lab rooms
│   ├── course.html                # Enrolled courses & course registration
│   ├── event.html                 # University campus events catalog with AJAX search/filter
│   ├── profile.html               # Student personal & academic profile dossier
│   ├── KYA-APPLICATION.html       # Know Your Answerbook examination viewing application
│   ├── contact.html               # Campus helpdesk & contact directory
│   └── faq.html                   # Frequently asked student questions accordion
│
├── uploads/
│   └── events/                    # Validated, unique uploaded event poster images
│
└── DATA/
    ├── events.json                # Seed events fallback dataset
    └── profile.json               # Seed student profile fallback dataset
```

---

### Verification Summary

| Verification Category | Check Performed | Result | Details |
| :--- | :--- | :---: | :--- |
| **1. Navigation** | Cross-linking across all 20+ pages | **PASSED** | Unbroken links, persistent `🏠 Home` button on every view, role-adaptive navbar. |
| **2. Responsiveness** | Multi-screen testing (mobile, tablet, desktop) | **PASSED** | Fluid grid layouts, horizontal table scrolling, responsive headers and cards. |
| **3. Authentication** | Role-Based Access Control (RBAC) & Sessions | **PASSED** | Password verification via `password_verify()`, session regeneration, 15-min timeout. |
| **4. Database Operations** | MySQLi Prepared Statements for all queries | **PASSED** | Full CRUD for students, events, registrations; auto-migration in `pages/db.php`. |
| **5. Security Checks** | Input validation, XSS, CSRF & file upload | **PASSED** | `htmlspecialchars()`, `filter_var()`, 2MB MIME verified image uploads, protected admin accounts. |
| **6. AJAX Functionality** | Fetch API asynchronous operations | **PASSED** | Asynchronous student CRUD without reload (`api/students.php`), dynamic event list & filter. |
| **7. Virtual Host** | Apache configuration & local deployment | **PASSED** | Deployed locally with `studenthub-vhost.conf` and `http://localhost/` compatibility. |

---

### How to Deploy Locally on XAMPP

#### Method A: Standard Localhost Deployment (Ready Out of the Box)
1. Ensure this folder is placed inside your XAMPP web root:
   ```
   C:\xampp\htdocs\WDF\student hub prec 15\
   ```
2. Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.
3. Open any web browser and visit:
   ```
   http://localhost/WDF/student%20hub%20prec%2015/
   ```
4. Tables and sample data are automatically created upon initial page load if not already present. You can also import `studenthub.sql` in phpMyAdmin (`http://localhost/phpmyadmin`).

#### Method B: Apache Virtual Host Deployment (`http://studenthub.local`)
1. Open Apache Virtual Hosts configuration file:
   ```
   C:\xampp\apache\conf\extra\httpd-vhosts.conf
   ```
2. Add the Virtual Host configuration (already included in `studenthub-vhost.conf`):
   ```apache
   # Default localhost
   <VirtualHost *:80>
       DocumentRoot "C:/xampp/htdocs"
       ServerName localhost
   </VirtualHost>

   # StudentHub Virtual Host
   <VirtualHost *:80>
       ServerAdmin admin@charusat.edu.in
       DocumentRoot "C:/xampp/htdocs/WDF/student hub prec 15"
       ServerName studenthub.local
       ServerAlias www.studenthub.local
       DirectoryIndex index.php index.html

       <Directory "C:/xampp/htdocs/WDF/student hub prec 15">
           Options Indexes FollowSymLinks
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```
3. Open `C:\Windows\System32\drivers\etc\hosts` in Notepad as Administrator and add:
   ```text
   127.0.0.1    studenthub.local
   ::1          studenthub.local
   ```
4. Restart Apache from the XAMPP Control Panel.
5. Open your web browser and navigate directly to:
   ```
   http://studenthub.local/
   ```

---

### Demo Test Credentials

| Role | Enrollment ID | Email | Password | Destination Page |
| :--- | :--- | :--- | :--- | :--- |
| **Student** | `25DCE122` | `yashvi@charusat.edu.in` | `yashvi123` | `pages/dashboard.php` |
| **Student** | `25DIT041` | `aarav.patel@charusat.edu.in` | `password123` | `pages/dashboard.php` |
| **Admin** | `ADMIN001` | `admin@charusat.edu.in` | `admin123` | `pages/admin_dashboard.php` |

---

### GitHub Repository Submission Workflow

To submit or sync your work to your GitHub repository:

```bash
# 1. Check repository status
git status

# 2. Stage all integrated files
git add .

# 3. Commit changes with a descriptive message
git commit -m "Practical 15: Integrated all modules into single deployable StudentHub portal with VirtualHost and verification"

# 4. Push to remote repository
git push origin main
```

---

## Practical 13: Simple PHP JSON API Endpoints & Asynchronous CRUD via Fetch API

### Problem Definition
> **Create simple PHP JSON API endpoints for events or students and consume them using Fetch API. Perform list, add, update, and delete operations asynchronously without full page reload.**

---

### Practical 13 Implementation Highlights

1. **Simple PHP JSON API Endpoints (`api/students.php` & `api/events.php`)**:
   - Sets standard JSON headers (`Content-Type: application/json; charset=UTF-8`).
   - Supports cross-origin resource sharing (`Access-Control-Allow-Origin: *`).
   - Connects to MySQL using MySQLi prepared statements.
   - **List Endpoint (`GET /api/students.php?action=list`)**: Returns array of student records formatted in JSON. Supports single-student lookup via `?student_id=ID`.
   - **Add Endpoint (`POST /api/students.php?action=add`)**: Validates input data (Name, Enrollment, Email, 10-digit Mobile, Password), checks for duplicates, hashes password using `password_hash()`, inserts into MySQL, and returns JSON success response.
   - **Update Endpoint (`POST /api/students.php?action=update`)**: Validates required fields, checks for duplicate email/enrollment against other students, updates MySQL, and returns JSON response.
   - **Delete Endpoint (`POST /api/students.php?action=delete`)**: Validates target student ID, protects admin accounts, removes record via MySQLi prepared statement, and returns JSON response.

2. **Frontend Consumption Using Fetch API (`pages/manage_students.php`)**:
   - **List Asynchronously (`loadStudents()`)**: Fetches student records from `../api/students.php?action=list` via `fetch()` and dynamically renders table rows.
   - **Add Asynchronously**: Intercepts `addStudentForm` submission (`e.preventDefault()`), sends `FormData` via `fetch('../api/students.php?action=add', { method: 'POST', body: formData })`, displays green success banner, resets form, and updates table without full page reload.
   - **Update Asynchronously**: Intercepts `editStudentForm` submission (`e.preventDefault()`), sends `FormData` via `fetch('../api/students.php?action=update', { method: 'POST', body: formData })`, shows confirmation banner, and updates the student list asynchronously.
   - **Delete Asynchronously**: Intercepts `deleteStudentForm` submission (`e.preventDefault()`), sends request via `fetch('../api/students.php?action=delete', { method: 'POST', body: formData })`, shows confirmation banner, and removes the student record from table asynchronously.
   - Preserves 100% of the existing StudentHub visual appearance, styles, colors, and layout.

---

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
