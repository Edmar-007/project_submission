CREATE DATABASE IF NOT EXISTS project_submission_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE project_submission_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS account_activation_tokens;
DROP TABLE IF EXISTS password_reset_tokens;
DROP TABLE IF EXISTS backup_runs;
DROP TABLE IF EXISTS subject_resources;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS reactivation_requests;
DROP TABLE IF EXISTS submission_history;
DROP TABLE IF EXISTS submission_members;
DROP TABLE IF EXISTS submission_activity_sections;
DROP TABLE IF EXISTS submission_activities;
DROP TABLE IF EXISTS submissions;
DROP TABLE IF EXISTS team_members;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS section_subjects;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS sections;
DROP TABLE IF EXISTS semesters;
DROP TABLE IF EXISTS school_years;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS admins;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin',
    status ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
    submission_deadline DATETIME NULL,
    deadline_warning_hours INT NOT NULL DEFAULT 72,
    deadline_warning_sent_at DATETIME NULL,
    deadline_locked_notice_sent_at DATETIME NULL,
    allow_late_submissions TINYINT(1) NOT NULL DEFAULT 0,
    late_submission_until DATETIME NULL,
    teacher_submission_locked TINYINT(1) NOT NULL DEFAULT 0,
    teacher_submission_lock_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    avatar_path VARCHAR(255) NULL,
    status ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
    submission_deadline DATETIME NULL,
    deadline_warning_hours INT NOT NULL DEFAULT 72,
    deadline_warning_sent_at DATETIME NULL,
    deadline_locked_notice_sent_at DATETIME NULL,
    allow_late_submissions TINYINT(1) NOT NULL DEFAULT 0,
    late_submission_until DATETIME NULL,
    teacher_submission_locked TINYINT(1) NOT NULL DEFAULT 0,
    teacher_submission_lock_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE school_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(20) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE semesters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_year_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_semesters_school_year FOREIGN KEY (school_year_id) REFERENCES school_years(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uniq_semester (school_year_id, name)
);

CREATE TABLE sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_name VARCHAR(100) NOT NULL,
    school_year_id INT NOT NULL,
    semester_id INT NOT NULL,
    status ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sections_school_year FOREIGN KEY (school_year_id) REFERENCES school_years(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_sections_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY uniq_section_year_sem (section_name, school_year_id, semester_id)
);

CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    avatar_path VARCHAR(255) NULL,
    section_id INT NOT NULL,
    account_status ENUM('pending', 'active', 'view_only', 'inactive', 'archived') NOT NULL DEFAULT 'pending',
    can_submit TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_students_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(50) NOT NULL,
    subject_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    teacher_id INT NOT NULL,
    school_year_id INT NOT NULL,
    semester_id INT NOT NULL,
    status ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
    submission_deadline DATETIME NULL,
    deadline_warning_hours INT NOT NULL DEFAULT 72,
    deadline_warning_sent_at DATETIME NULL,
    deadline_locked_notice_sent_at DATETIME NULL,
    allow_late_submissions TINYINT(1) NOT NULL DEFAULT 0,
    late_submission_until DATETIME NULL,
    teacher_submission_locked TINYINT(1) NOT NULL DEFAULT 0,
    teacher_submission_lock_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_subjects_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_subjects_school_year FOREIGN KEY (school_year_id) REFERENCES school_years(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_subjects_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY uniq_subject_term_teacher (subject_code, teacher_id, school_year_id, semester_id)
);

CREATE TABLE section_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    subject_id INT NOT NULL,
    activity_id INT NULL,
    submission_deadline DATETIME NULL,
    deadline_warning_hours INT NOT NULL DEFAULT 72,
    deadline_warning_sent_at DATETIME NULL,
    deadline_locked_notice_sent_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_section_subjects_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_section_subjects_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uniq_section_subject (section_id, subject_id)
);


CREATE TABLE submission_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    status ENUM('draft','published','closed','archived') NOT NULL DEFAULT 'draft',
    opens_at DATETIME NULL,
    deadline_at DATETIME NULL,
    allow_late TINYINT(1) NOT NULL DEFAULT 0,
    late_until DATETIME NULL,
    is_locked TINYINT(1) NOT NULL DEFAULT 0,
    lock_note TEXT NULL,
    submission_mode ENUM('individual','team') NOT NULL DEFAULT 'team',
    min_members INT NOT NULL DEFAULT 1,
    max_members INT NOT NULL DEFAULT 5,
    allow_resubmission TINYINT(1) NOT NULL DEFAULT 0,
    max_resubmissions INT NOT NULL DEFAULT 1,
    require_file TINYINT(1) NOT NULL DEFAULT 0,
    require_repository TINYINT(1) NOT NULL DEFAULT 1,
    require_live_url TINYINT(1) NOT NULL DEFAULT 1,
    require_demo_access TINYINT(1) NOT NULL DEFAULT 0,
    require_notes TINYINT(1) NOT NULL DEFAULT 0,
    created_by_teacher_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_submission_activities_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_submission_activities_teacher FOREIGN KEY (created_by_teacher_id) REFERENCES teachers(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_submission_activities_subject (subject_id, status, deadline_at),
    INDEX idx_submission_activities_teacher (created_by_teacher_id, status)
);

CREATE TABLE submission_activity_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_id INT NOT NULL,
    section_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_submission_activity_sections_activity FOREIGN KEY (activity_id) REFERENCES submission_activities(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_submission_activity_sections_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uniq_activity_section (activity_id, section_id)
);

CREATE TABLE teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    activity_id INT NULL,
    section_id INT NOT NULL,
    leader_student_id INT NOT NULL,
    team_name VARCHAR(150) NOT NULL,
    status ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
    submission_deadline DATETIME NULL,
    deadline_warning_hours INT NOT NULL DEFAULT 72,
    deadline_warning_sent_at DATETIME NULL,
    deadline_locked_notice_sent_at DATETIME NULL,
    allow_late_submissions TINYINT(1) NOT NULL DEFAULT 0,
    late_submission_until DATETIME NULL,
    teacher_submission_locked TINYINT(1) NOT NULL DEFAULT 0,
    teacher_submission_lock_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_teams_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_teams_activity FOREIGN KEY (activity_id) REFERENCES submission_activities(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_teams_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_teams_leader FOREIGN KEY (leader_student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uniq_leader_activity_team (leader_student_id, activity_id)
);

CREATE TABLE team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    student_id INT NOT NULL,
    role ENUM('leader', 'member') NOT NULL DEFAULT 'member',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_team_members_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_team_members_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uniq_team_member (team_id, student_id)
);

CREATE TABLE submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    student_id INT NOT NULL,
    submitted_by_student_id INT NULL,
    section_id INT NOT NULL,
    subject_id INT NOT NULL,
    activity_id INT NULL,
    attempt_no INT NOT NULL DEFAULT 1,
    assigned_system VARCHAR(255) NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    project_url TEXT NOT NULL,
    video_url TEXT NOT NULL,
    admin_username VARCHAR(100) NULL,
    admin_password VARCHAR(255) NULL,
    user_username VARCHAR(100) NULL,
    user_password VARCHAR(255) NULL,
    contact_email VARCHAR(150) NOT NULL,
    attachment_path VARCHAR(255) NULL,
    status ENUM('pending', 'reviewed', 'graded', 'archived') NOT NULL DEFAULT 'pending',
    grade VARCHAR(50) NULL,
    teacher_feedback TEXT NULL,
    review_notes TEXT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_submissions_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_submissions_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_submissions_submitted_by FOREIGN KEY (submitted_by_student_id) REFERENCES students(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_submissions_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_submissions_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_submissions_activity FOREIGN KEY (activity_id) REFERENCES submission_activities(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_team_activity_attempt (team_id, activity_id, attempt_no),
    INDEX idx_student_activity_attempt (student_id, activity_id, attempt_no)
);

CREATE TABLE submission_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    student_id INT NULL,
    member_name VARCHAR(150) NOT NULL,
    student_id_snapshot VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_submission_members_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_submission_members_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE submission_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    team_id INT NOT NULL,
    subject_id INT NOT NULL,
    section_id INT NOT NULL,
    version_no INT NOT NULL,
    action_type ENUM('created', 'edited', 'reviewed', 'graded', 'deleted', 'restored') NOT NULL DEFAULT 'created',
    actor_user_id INT NULL,
    actor_role ENUM('student', 'teacher', 'admin', 'system') NOT NULL DEFAULT 'system',
    actor_name VARCHAR(150) NULL,
    status VARCHAR(50) NOT NULL,
    grade VARCHAR(50) NULL,
    assigned_system VARCHAR(255) NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    project_url TEXT NULL,
    video_url TEXT NULL,
    contact_email VARCHAR(150) NULL,
    attachment_path VARCHAR(255) NULL,
    teacher_feedback TEXT NULL,
    review_notes TEXT NULL,
    snapshot_payload LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_submission_history_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_submission_history_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_submission_history_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_submission_history_submission (submission_id, version_no),
    INDEX idx_submission_history_team (team_id),
    INDEX idx_submission_history_subject (subject_id)
);

CREATE TABLE subject_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    is_visible_to_students TINYINT(1) NOT NULL DEFAULT 1,
    created_by_teacher_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_subject_resources_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_subject_resources_teacher FOREIGN KEY (created_by_teacher_id) REFERENCES teachers(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE backup_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    run_key VARCHAR(120) NOT NULL UNIQUE,
    zip_path VARCHAR(255) NOT NULL,
    manifest_path VARCHAR(255) NULL,
    uploaded_to_drive TINYINT(1) NOT NULL DEFAULT 0,
    drive_file_id VARCHAR(255) NULL,
    status ENUM('success', 'failed') NOT NULL DEFAULT 'success',
    notes TEXT NULL,
    created_by_admin_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE reactivation_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    current_section_id INT NOT NULL,
    requested_section_id INT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'denied') NOT NULL DEFAULT 'pending',
    admin_note TEXT NULL,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reactivation_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reactivation_current_section FOREIGN KEY (current_section_id) REFERENCES sections(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reactivation_requested_section FOREIGN KEY (requested_section_id) REFERENCES sections(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reactivation_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES admins(id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('admin', 'teacher', 'student') NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning') NOT NULL DEFAULT 'info',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user (user_type, user_id),
    INDEX idx_notifications_read (is_read)
);

CREATE TABLE password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('teacher', 'student') NOT NULL,
    user_id INT NOT NULL,
    token VARCHAR(128) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE account_activation_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    token VARCHAR(128) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activation_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_type ENUM('admin', 'teacher', 'student') NOT NULL,
    actor_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(100) NOT NULL,
    target_id INT NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO school_years (label, is_active) VALUES ('2025-2026', 1);
INSERT INTO semesters (school_year_id, name, is_active) VALUES (1, '1st Semester', 1), (1, '2nd Semester', 0);
INSERT INTO admins (full_name, username, password_hash, role, status) VALUES
('Main Admin', 'admin', '$2y$12$oV5xYO4.0PGbc.mPuIpMDuZOjr0kaBLfe/aEnaZV.UamYhpkXAyqC', 'super_admin', 'active');
INSERT INTO teachers (teacher_id, full_name, email, username, password_hash, status) VALUES
('T-001', 'Teacher One', 'teacher1@example.com', 'teacher1', '$2y$12$XPo/qSaTKIxVIejTHc1Yn.XacZYkzAZ3Ju5k3quDoXYTLD0hTusSi', 'active'),
('T-002', 'Teacher Two', 'teacher2@example.com', 'teacher2', '$2y$12$XPo/qSaTKIxVIejTHc1Yn.XacZYkzAZ3Ju5k3quDoXYTLD0hTusSi', 'active');
INSERT INTO sections (section_name, school_year_id, semester_id, status, notes) VALUES
('BSIT 22006', 1, 1, 'active', 'Regular section'),
('BSIT 22008', 1, 1, 'active', 'Regular section'),
('BSIT 22010', 1, 1, 'active', 'Regular section');
INSERT INTO students (student_id, full_name, email, username, password_hash, section_id, account_status, can_submit) VALUES
('2025-0001', 'Juan Dela Cruz', 'juan@example.com', 'juan2025', '$2y$12$DdPE2fkqQDLDfwhC/Jq6t.x4PreQgB72MECuYbs1bH8NtjKdcRO4i', 1, 'active', 1),
('2025-0002', 'Maria Santos', 'maria@example.com', 'maria2025', '$2y$12$DdPE2fkqQDLDfwhC/Jq6t.x4PreQgB72MECuYbs1bH8NtjKdcRO4i', 1, 'active', 1),
('2025-0003', 'Pedro Reyes', 'pedro@example.com', 'pedro2025', '$2y$12$DdPE2fkqQDLDfwhC/Jq6t.x4PreQgB72MECuYbs1bH8NtjKdcRO4i', 2, 'view_only', 0),
('2025-0004', 'Ana Cruz', 'ana@example.com', '2025-0004', '$2y$12$DdPE2fkqQDLDfwhC/Jq6t.x4PreQgB72MECuYbs1bH8NtjKdcRO4i', 2, 'pending', 0);
INSERT INTO subjects (subject_code, subject_name, description, teacher_id, school_year_id, semester_id, status) VALUES
('IM101', 'Information Management', 'Project submissions for Information Management.', 1, 1, 1, 'active'),
('WS201', 'Web Systems', 'Project submissions for Web Systems.', 2, 1, 1, 'active');
INSERT INTO section_subjects (section_id, subject_id) VALUES (1,1), (2,1), (3,2);
INSERT INTO teams (subject_id, section_id, leader_student_id, team_name, status) VALUES
(1, 1, 1, 'IM101 Team Juan', 'active');
INSERT INTO team_members (team_id, student_id, role) VALUES
(1, 1, 'leader'),
(1, 2, 'member');
INSERT INTO submissions (team_id, student_id, submitted_by_student_id, section_id, subject_id, assigned_system, company_name, project_url, video_url, admin_username, admin_password, user_username, user_password, contact_email, status, grade, teacher_feedback, review_notes)
VALUES
(1, 1, 1, 1, 1, 'Student Project Portal', 'Campus Group', 'https://example.com/project', 'https://example.com/video', 'admin_demo', 'demo123', 'user_demo', 'demo123', 'group@example.com', 'reviewed', NULL, 'Initial seeded team submission visible to both leader and members.', 'Seed data');
INSERT INTO submission_members (submission_id, member_name) VALUES
(1, 'Juan Dela Cruz'),
(1, 'Maria Santos');

INSERT INTO notifications (user_type, user_id, title, message, type) VALUES
('student', 1, 'Welcome', 'Your account has been created successfully.', 'success'),
('student', 3, 'View-only account', 'Your account is currently view-only. Request reactivation if you need submission access again.', 'warning'),
('teacher', 1, 'Subject Ready', 'Information Management is ready for section assignment and review.', 'info'),
('admin', 1, 'System Ready', 'Initial academic structure has been seeded successfully.', 'success');




-- V8 notes: mail is optional and logs to backend/logs/mail.log when disabled.
