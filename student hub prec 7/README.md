# STUDENT HUB - Web Development Framework (WDF)

## Practical 7

### Problem Definition
> **Process submitted registration/contact form data using PHP. Validate and sanitize inputs on the server side and store records in CSV or JSON file format. Display success/error messages.**

---

### Implementation Details

1. **Form Submission (`pages/register.php`)**:
   - Collects student details: Full Name, Enrollment No, Email, Mobile Number, Department, Semester, Password, and Confirm Password.
   - Form method is `POST` and action points to `process_registration.php`.

2. **Server-Side Sanitization (`pages/process_registration.php`)**:
   - `trim()` and `strip_tags()` to strip out leading/trailing spaces and unwanted HTML tags.
   - `filter_var($email, FILTER_SANITIZE_EMAIL)` to sanitize email addresses.

3. **Server-Side Validation (`pages/process_registration.php`)**:
   - **Full Name**: Required; validated via regular expression `^[a-zA-Z\s]+$` (letters and spaces only).
   - **Enrollment Number**: Required.
   - **Email**: Required; validated using `filter_var($email, FILTER_VALIDATE_EMAIL)`.
   - **Mobile Number**: Required; validated using regex `^[0-9]{10}$` (exact 10 digits).
   - **Department & Semester**: Required; verified against allowed lists.
   - **Password**: Required; minimum length of 6 characters.
   - **Confirm Password**: Verified to match the password.

4. **Data Storage (`DATA/` directory)**:
   - **CSV Storage (`DATA/registration.csv`)**: Appends records using `fputcsv()`. If the file is newly created, a header row is automatically inserted:
     `Full Name, Enrollment No, Email, Mobile, Department, Semester, Registration Time`
   - **JSON Storage (`DATA/registration.json`)**: Appends records as structured JSON objects with `JSON_PRETTY_PRINT`.

5. **Success / Error Feedback**:
   - Retains the exact **STUDENT HUB** portal appearance (header iframe, navigation bar, colors, card styling, and footer).
   - **Success**: Displays a success banner and a clean table summarizing the sanitized submitted data, along with buttons to register another student or proceed to login.
   - **Error**: Displays an error banner with a bulleted list of all validation issues and a button to return to the form and correct the input.

---

### How to Run on XAMPP

1. Place the project folder into your XAMPP `htdocs` directory:
   ```
   C:\xampp\htdocs\WDF\student hub prec 7\
   ```
2. Start the **Apache** server from the XAMPP Control Panel.
3. Open your web browser and navigate to:
   ```
   http://localhost/WDF/student%20hub%20prec%207/index.php
   ```
   or go directly to the registration page:
   ```
   http://localhost/WDF/student%20hub%20prec%207/pages/register.php
   ```
4. Fill out the registration form and click **Register Now**.
5. Check the stored data in:
   - `DATA/registration.csv`
   - `DATA/registration.json`
