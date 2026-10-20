# STUDENT HUB - Web Development Framework (WDF)

## Practical 10: Secure Login/Logout with Sessions, Role-Based Access, Timeout, and Remember-Me Token

### Problem Definition
> **Develop secure login and logout functionality using PHP sessions. Verify password using `password_verify()`, regenerate session ID after login, restrict dashboard access, implement role-based redirection, session timeout, and optional secure remember-me token.**

---

### Implementation Details

1. **Secure Session Initialization (`pages/auth.php`)**:
   - Configures secure session cookies using `session_set_cookie_params()`:
     - `httponly = true`: Prevents client-side script access to the session cookie, guarding against Cross-Site Scripting (XSS).
     - `samesite = 'Lax'`: Mitigates Cross-Site Request Forgery (CSRF).
     - `secure`: Automatically set when running over HTTPS.
   - Automatically initializes session on page load using `init_session()`.

2. **Password Verification (`password_verify()`) (`pages/login.php`)**:
   - Queries student or administrator record by Enrollment Number OR Email using parameterized MySQLi Prepared Statements (`SELECT ... WHERE enrollment_no = ? OR email = ?`).
   - Verifies user-entered password against the securely stored `password_hash()` bcrypt hash using PHP's native `password_verify($password, $user['password'])`.

3. **Session ID Regeneration (`session_regenerate_id(true)`)**:
   - Immediately upon successful authentication in `login_user()`, invokes `session_regenerate_id(true)`.
   - Wipes the previous session ID and assigns a fresh ID to defend against **Session Fixation attacks**.

4. **Restricted Dashboard Access (`pages/dashboard.php`)**:
   - Unauthenticated users attempting to access the dashboard are blocked by `require_login()`.
   - Automatically redirects unauthenticated requests to `login.php?msg=auth_required`.
   - `pages/dashboard.html` redirects to `dashboard.php` to ensure server-side authorization is always enforced.

5. **Role-Based Redirection & Access Control**:
   - The database maintains user roles (`student`, `admin`) in the `students` table.
   - **Student Login**: Automatically directed to the Student Dashboard (`pages/dashboard.php`), displaying live session info (name, enrollment, department, semester).
   - **Admin Login**: Automatically directed to the Administrator Control Panel (`pages/admin_dashboard.php`), displaying database statistics (registered students, active events, registrations) and management tools.
   - Unauthorized role access attempts are caught by `require_role('admin')` and redirected to `dashboard.php?msg=access_denied`.

6. **Session Inactivity Timeout (15-Minute Timeout)**:
   - Tracks the timestamp of the last user activity in `$_SESSION['last_activity']`.
   - If inactivity exceeds 900 seconds (15 minutes), the session is cleanly invalidated via `logout_user(false)` and the user is redirected to `login.php?msg=timeout`.

7. **Cryptographically Secure Remember-Me Token (`auth_tokens` table)**:
   - When the user selects "Remember Me":
     - Generates a **split token**: a public 16-byte `selector` and a private 32-byte `validator` generated via `random_bytes()`.
     - Stores the `selector` and SHA-256 hash of the validator (`hash('sha256', $validator)`) in the MySQL `auth_tokens` table with a 30-day expiration (`expires_at`).
     - Places an `HttpOnly`, `SameSite=Lax` cookie `remember_token` on the client.
     - On future visits with an inactive session, `check_remember_me_token()` matches the selector and verifies the token using timing-safe `hash_equals()`, restoring the session automatically.

8. **Secure Logout (`pages/logout.php`)**:
   - Revokes and deletes the Remember-Me token from the database for the current device.
   - Unsets all session variables (`$_SESSION = []`).
   - Deletes the session cookie with past expiration.
   - Calls `session_destroy()` to terminate the server-side session.
   - Redirects to `login.php?msg=logged_out`.

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
   C:\xampp\htdocs\WDF\student hub prec 10\
   ```
2. Open the **XAMPP Control Panel** and start both **Apache** and **MySQL**.
3. Open your web browser and navigate to:
   - **Home Page**:
     ```
     http://localhost/WDF/student%20hub%20prec%2010/index.php
     ```
   - **Login Page**:
     ```
     http://localhost/WDF/student%20hub%20prec%2010/pages/login.php
     ```
   - **Student Dashboard** (Protected):
     ```
     http://localhost/WDF/student%20hub%20prec%2010/pages/dashboard.php
     ```
   - **Admin Dashboard** (Protected - Admin Only):
     ```
     http://localhost/WDF/student%20hub%20prec%2010/pages/admin_dashboard.php
     ```
   - **Student Registration**:
     ```
     http://localhost/WDF/student%20hub%20prec%2010/pages/register.php
     ```
   - **View Database Records**:
     ```
     http://localhost/WDF/student%20hub%20prec%2010/pages/view_records.php
     ```
