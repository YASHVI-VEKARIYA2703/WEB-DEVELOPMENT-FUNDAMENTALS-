# STUDENT HUB - Web Development Framework (WDF)

## Practical 8: MySQL Schema Design, ER Model, PDO Connectivity, and Prepared Statements

### Problem Definition
> **Design the database for StudentHub with students, events and registrations. Create PHP database connection using MYSQL statements.**

---

### Implementation Details

1. **Database Schema Design (`studenthub.sql`)**:
   - **Database Name**: `studenthub`
   - **`students` Table**:
     Stores student personal and academic information, along with secured password hashes.
     - `student_id` (INT, Primary Key, Auto Increment)
     - `fullname` (VARCHAR(100), NOT NULL)
     - `enrollment_no` (VARCHAR(50), NOT NULL, UNIQUE)
     - `email` (VARCHAR(100), NOT NULL, UNIQUE)
     - `mobile` (VARCHAR(15), NOT NULL)
     - `department` (VARCHAR(50), NOT NULL)
     - `semester` (VARCHAR(20), NOT NULL)
     - `password` (VARCHAR(255), NOT NULL)
     - `created_at` (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)

   - **`events` Table**:
     Stores campus events, workshops, tech fests, and sports meets.
     - `event_id` (INT, Primary Key, Auto Increment)
     - `tag` (VARCHAR(50), NOT NULL)
     - `title` (VARCHAR(150), NOT NULL)
     - `event_date` (VARCHAR(50), NOT NULL)
     - `venue` (VARCHAR(100), NOT NULL)
     - `organizer` (VARCHAR(100), NOT NULL)
     - `description` (TEXT, NOT NULL)
     - `created_at` (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)

   - **`registrations` Table (Associative / Junction Table)**:
     Resolves the **Many-to-Many (M:N)** relationship between students and events.
     - `registration_id` (INT, Primary Key, Auto Increment)
     - `student_id` (INT, Foreign Key referencing `students.student_id`)
     - `event_id` (INT, Foreign Key referencing `events.event_id`)
     - `registration_date` (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)
     - `status` (VARCHAR(20), DEFAULT 'Confirmed')
     - Composite Unique Constraint on `(student_id, event_id)` to prevent duplicate registrations for the same event.

2. **Entity-Relationship (ER) Model (`docs/ER_MODEL.md`)**:
   - **Cardinality**:
     - 1 Student can register for $N$ Events ($1 : N$).
     - 1 Event can have $N$ registered Students ($1 : N$).
     - Relationship between Students and Events is **Many-to-Many ($M : N$)**, implemented through the `registrations` junction table with foreign keys (`ON DELETE CASCADE`).

3. **Database Connection using PDO (`pages/db.php`)**:
   - Uses **PHP Data Objects (PDO)** with the MySQL driver for secure, modern database connectivity.
   - Configured with `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` for structured error handling.
   - Sets `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`.
   - Includes auto-initialization of the database and tables for smooth beginner setup.

4. **Prepared Statements & Server-Side Processing (`pages/process_registration.php`)**:
   - All SQL operations utilize parameterized **Prepared Statements** to eliminate SQL Injection vulnerabilities:
     - `SELECT student_id FROM students WHERE enrollment_no = :enrollment OR email = :email` (Duplicate verification).
     - `INSERT INTO students (...) VALUES (:fullname, :enrollment, :email, ...)` (Secure record insertion).
   - Validates and sanitizes inputs before executing database queries.
   - Preserves CSV (`DATA/registration.csv`) and JSON (`DATA/registration.json`) backups.

5. **Viewing Records & JOIN Queries (`pages/view_records.php`)**:
   - Displays real-time data from all 3 tables (`students`, `events`, and `registrations`).
   - Uses an `INNER JOIN` query to demonstrate foreign key relationships:
     ```sql
     SELECT r.registration_id, s.fullname, s.enrollment_no, e.title, e.tag, e.event_date, r.registration_date, r.status
     FROM registrations r
     INNER JOIN students s ON r.student_id = s.student_id
     INNER JOIN events e ON r.event_id = e.event_id;
     ```
   - Includes a quick form to register students for events via prepared statement.

---

### How to Run on XAMPP

1. Place this project folder in your XAMPP `htdocs` directory:
   ```
   C:\xampp\htdocs\WDF\student hub prec 8\
   ```
2. Open the **XAMPP Control Panel** and start both **Apache** and **MySQL**.
3. (Optional) Open **phpMyAdmin** (`http://localhost/phpmyadmin/`) and import `studenthub.sql` (or let `db.php` automatically create the database on first access).
4. Open your web browser and navigate to:
   - **Home Page**:
     ```
     http://localhost/WDF/student%20hub%20prec%208/index.php
     ```
   - **Student Registration**:
     ```
     http://localhost/WDF/student%20hub%20prec%208/pages/register.php
     ```
   - **View MySQL Database Records**:
     ```
     http://localhost/WDF/student%20hub%20prec%208/pages/view_records.php
     ```
