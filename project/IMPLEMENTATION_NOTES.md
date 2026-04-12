# Implementation Notes

This build applies the new activity-based submission model in one pass.

## Implemented
- Subject -> Submission Activity -> Submission workflow
- Teacher activity creation inside `teacher/subject_view.php`
- Activity restrictions: status, open/deadline, late window, team/individual mode, min/max members, section targeting, required fields, lock/unlock
- Student activity browsing in `student/subjects.php`
- New activity-based submit flow in `student/submit.php`
- Live teammate lookup endpoint in `student/member_search.php`
- Shared submission visibility foundation through `submission_members.student_id`
- Improved import templates in `backend/templates/student_import_template.xlsx` and `.csv`
- Subject modal section validation bug fixed in `teacher/subjects.php`
- Safer modal focus/escape behavior in `backend/assets/app.js`
- Stronger demo credential encryption fallback in `backend/config/app.php`
- Schema and runtime migration support for activity tables and columns in `backend/schema.sql` and `backend/helpers/query.php`

## Important follow-up testing to do in XAMPP
1. Open `teacher/subject_view.php?id=<subject>` and create a new activity.
2. Confirm the activity appears for students in the same section.
3. Submit once as a leader, add members through search, and verify members see the shared record in `student/my_submissions.php`.
4. Review the submission in teacher pages.
5. Test existing edit/review pages with an activity-backed submission.

## Known limits of this one-pass build
- Existing admin reports/pages were not fully redesigned to expose activity-specific dashboards.
- Legacy submissions without `activity_id` remain readable, but older pages still display them as a general submission when needed.
- The teacher activity UI currently supports create and state changes in the workspace; a dedicated activity edit page was not added yet.
- Full automated browser testing was not run in this environment.
