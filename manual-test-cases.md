# Manual acceptance tests (run after XAMPP setup)

These are expected results, not claims of completed database tests. Use fictional records only. Start with a fresh demo database and change both temporary passwords.

| ID | Steps | Expected result |
|---|---|---|
| T01 | Log in as teacher; open Students; create `2026-002`, a fictional name, section and temporary password; edit name and section. | Student profile appears; changes persist after refresh; login account is linked. |
| T02 | Attendance → choose that student → save Present for today → save Absent for the same date. | One record remains for the date; status updates; summary shows 0 present, 1 absent, 0% rate. |
| T03 | Grades → same student → ITEC 116, Prelim, 88 → update to 92.5. | One grade for the subject/period remains; grade and average show 92.50. |
| T04 | Log out; sign in as `2026-002` with temporary password; change password; visit dashboard and summary. | Only that student's attendance and grades appear; no teacher navigation or edit form. |
| T05 | Teacher creates a second student. Log in as first student and append `&student=1` to `?page=summary`. Also open `?page=students`. | The summary still shows the logged-in student only; Students route returns 403. |
| T06 | Attempt to submit a teacher-only POST while signed in as student, with that session's valid CSRF token. | Server returns 403; records unchanged. |
| T07 | Use browser dev tools to remove/change CSRF token before submitting a form. | Server returns 403; records unchanged. |
| T08 | Try a duplicate student ID; a future attendance date; a grade above 100. For the latter two, bypass browser validation using dev tools to test PHP. | Server rejects the change; no duplicate/orphan records and no invalid values are stored. |
| T09 | Enter a name such as `<img src=x onerror=alert(1)>` (or an SQL-looking string) as test data. | Value appears as text, no script execution or SQL modification. |
| T10 | Reset a student's password while they are logged in elsewhere; request another page in that student session. | Old session cannot access records; new temporary password requires change. |
| T11 | Sign out, then request a protected URL directly. | Redirect to login; no protected records. |
| T12 | Attempt 9 incorrect logins on one existing demo account within 15 minutes. | Account throttled; wait for window expiry before further login tests. |
| T13 | Disable JavaScript, log in, and navigate using the sidebar. | Core forms and links work; enhancements such as search and password toggle are unavailable. |
| T14 | Stop MySQL and submit a valid login form. | Generic error page; no database details or stack trace revealed. Restart MySQL afterward. |
