# ClassTrack — Student Attendance and Academic Progress Monitoring System

A small ITEC 116 project using HTML, CSS, JavaScript, PHP (PDO), and MySQL/MariaDB. No frameworks, CDN, or package installation needed. The system integrates student information, attendance and grades using relational database keys.

## Install in XAMPP (Windows)

1. Extract the ZIP. Copy the **student-monitor** folder into `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel. PHP 8.1+ with `pdo_mysql` enabled is required.
3. Open `http://localhost/phpmyadmin/`. Select **Import**, choose `student-monitor/database/setup.sql`, then click **Import** (or Go). Import once into a fresh database. The script creates `student_monitor`, all five tables and sample data; it does not drop existing tables. If the database exists, back it up before choosing a new database or making changes.
4. Open `http://localhost/student-monitor/` (or `http://localhost/student-monitor/public/`).
5. Use the local demo credentials below. Both demo users must set a new password on first login; choose a unique password of at least 12 characters and keep it for subsequent logins.

| Role | Username | Temporary password |
|---|---|---|
| Teacher | `teacher` | `TeacherDemo!2026` |
| Student | `2026-001` | `StudentDemo!2026` |

The example student is Alex Reyes, BSIT 3-1. Demo attendance and grades are seeded. The teacher creates subsequent student accounts on the Students page. The student ID becomes the username (case-insensitive); the teacher privately provides a temporary password. No public registration or self-assignment of roles is included.

If your MySQL username, password, port or database name differs, edit `app/config.php` or set `MONITOR_DB_HOST`, `MONITOR_DB_PORT`, `MONITOR_DB_NAME`, `MONITOR_DB_USER` and `MONITOR_DB_PASS`. Never put real database credentials in a public directory. For a port other than Apache's default, adjust the localhost URL accordingly.

## Workflow

Teacher signs in → changes temporary password if required → dashboard → Students (add/edit) → Attendance or Grades (filter by section, choose student) → save record → Progress summary. Each record is linked to the same student profile. Sign out via the sidebar.

Student signs in → changes temporary password if required → own dashboard → attendance / grades / progress summary (read-only) → sign out. The student identity is derived from the authenticated session, never from a student ID supplied in a URL.

## Features and scope

- Teacher: view dashboard totals, add/edit/search student profiles, filter by section, record or correct Present/Absent attendance per date, enter or update grades per subject and grading period, view individual summaries, reset student passwords.
- Student: view own attendance, grades and progress, change password, sign out. No record editing.
- Attendance rate = Present / total recorded dates × 100. Missing dates are not assumed absent.
- Grades are percentages, 0–100, up to two decimal places, with Prelim/Midterm/Final periods. The average is unweighted, not official GWA. Each student has one attendance record per date and one grade per subject/period. Saving again updates the existing record.
- Simplifying design choices: one assigned teacher per student, one daily attendance record (not per subject), one academic cycle (no school-year/semester filter), no deletion or bulk upload, no administrator or separate teacher registration. These keep the scope aligned with the proposal. A multi-teacher school deployment would need enrollment/subject assignments, term separation and administrative provisioning.

## Security layers

| Layer | Implementation |
|---|---|
| PHP | Password hashing/verification; role and record ownership checks; server-side validation; CSRF token verification for all POST requests; login rate limits by account/IP; regenerated sessions at login and logout; 30-minute idle timeout; credential change invalidates prior sessions; forced temporary password change; POST-only state changes; generic errors and server logs. |
| Database | Native PDO prepared statements, foreign keys, unique indexes, check constraint for grades, transactions for linked account/profile changes. |
| HTML | Required fields, types, patterns, length and grade/date bounds; no role selector; escaped output; autocomplete hints. |
| JavaScript | Password confirmation, duplicate submit prevention, confirmations, textContent-based UI updates; no passwords/session tokens stored in localStorage. Client-side checks can be bypassed, so PHP repeats the security-relevant validation. |
| Browser/server | CSP blocks inline/third-party scripts; frame protection; no-store caching; HttpOnly/SameSite cookies; Secure cookie and HSTS when HTTPS is active; Apache denies access to app/database/tests directories. |

HTML and JS are not separate authentication systems. They run on an untrusted client. PHP is the authority for identity, access control and all database writes. CSS is only presentation.

## Deployment notes

Designed for local XAMPP coursework, not an audited production system. Before using real student information: use HTTPS, replace demo accounts/credentials, configure a least-privilege database user, set Apache's document root to `public/`, store secrets outside the served tree, add backups and monitoring, arrange formal teacher provisioning and data retention, and review applicable school privacy policies. Do not expose the default XAMPP root account or phpMyAdmin to the internet. Apache `.htaccess` protection requires AllowOverride settings that honor access controls; a public-only document root is stronger. Login limits have a fixed 15-minute window (8 attempts/account and 40/IP), so shared-network users may temporarily hit the IP limit. For long-running deployments, periodically remove old rows from `login_limits` and tune throttling. No automated recovery email or MFA is included.

## Files

```
student-monitor/
├── index.php                 # redirects to public entry point
├── .htaccess                 # disables directory browsing
├── app/
│   ├── config.php            # database settings
│   ├── bootstrap.php         # sessions, headers, validation, queries, access checks
│   └── actions.php           # login/logout, password and monitoring mutations
├── public/
│   ├── index.php             # routes, HTML page structure and dashboards
│   └── assets/
│       ├── style.css         # responsive styles and animations
│       └── app.js            # interaction and client validation
├── database/setup.sql        # schema and sample accounts/data
└── tests/validation.php      # CLI validation tests
```

## Testing

Run syntax checks (`php -l public/index.php`, etc.), `php tests/validation.php` and `node --check public/assets/app.js` if Node is installed. The package was checked with PHP 8.4 and Node; all PHP files linted, JS parsed, all 14 standalone validation checks passed. Local HTTP tests confirmed login page 200, guest-only redirect 303, invalid CSRF 403, unsupported method 405 and security headers. Browser markup and JS can be checked separately, but the test environment did not contain MySQL/MariaDB or a PDO driver. Full end-to-end database and authenticated UI tests remain to be performed in XAMPP. See `tests/manual-test-cases.md`.
