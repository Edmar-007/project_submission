# Project Submission Management System — V8 rebuild

This is a fresh rebuilt V8 package based on the available V7 project, prepared to run on **XAMPP + Apache + MySQL** without Vite or Node.js.

## Included in this rebuilt V8 package
- admin, teacher, and student portals
- section, subject, student, and teacher management
- archive lifecycle for sections, subjects, students, and submissions
- grading, feedback, reactivation requests, and notifications
- optional mail configuration with safe mail logging fallback
- password reset pages for students and teachers
- optional submission attachment upload (PDF/image up to 5 MB)
- system tools page with SQL backup export
- database port defaulted to **33060** to match your setup

## Default demo accounts after importing `backend/schema.sql`
- Admin: `admin` / `admin123`
- Teacher: `teacher1` / `teacher123`
- Student: `juan2025` / `student123`

## Setup
1. Copy `project_submission_app` into `htdocs`
2. Start Apache + MySQL in XAMPP
3. Import `backend/schema.sql` in phpMyAdmin
4. Open `http://localhost/project_submission_app/`

## Configuration
### Database
Database settings live in:
- `backend/config/app.php`
- `backend/config/db.php`

Default DB port is `33060`.

### Mail
Mail settings live in:
- `backend/config/mail.php`

If mail is disabled, outgoing emails are written to:
- `backend/logs/mail.log`

## Notes
- This package is **Apache/XAMPP-first** and does not require Vite.
- Replace demo seeded passwords before production use.
- Uploaded files are stored in `uploads/`.


## Team-based submission
- Leaders submit once per subject.
- Add members by student ID, one per line.
- Every member added to the team can log in and see the same project, links, and teacher feedback.
