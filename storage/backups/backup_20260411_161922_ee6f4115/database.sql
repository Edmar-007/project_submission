-- Backup generated on 2026-04-11 16:19:22

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `account_activation_tokens`;
CREATE TABLE `account_activation_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `fk_activation_student` (`student_id`),
  CONSTRAINT `fk_activation_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','admin') NOT NULL DEFAULT 'admin',
  `status` enum('active','inactive','archived') NOT NULL DEFAULT 'active',
  `submission_deadline` datetime DEFAULT NULL,
  `deadline_warning_hours` int(11) NOT NULL DEFAULT 72,
  `deadline_warning_sent_at` datetime DEFAULT NULL,
  `deadline_locked_notice_sent_at` datetime DEFAULT NULL,
  `allow_late_submissions` tinyint(1) NOT NULL DEFAULT 0,
  `late_submission_until` datetime DEFAULT NULL,
  `teacher_submission_locked` tinyint(1) NOT NULL DEFAULT 0,
  `teacher_submission_lock_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admins` (`id`, `full_name`, `username`, `password_hash`, `role`, `status`, `submission_deadline`, `deadline_warning_hours`, `deadline_warning_sent_at`, `deadline_locked_notice_sent_at`, `allow_late_submissions`, `late_submission_until`, `teacher_submission_locked`, `teacher_submission_lock_note`, `created_at`, `updated_at`) VALUES ('1', 'Main Admin', 'admin', '$2y$12$oV5xYO4.0PGbc.mPuIpMDuZOjr0kaBLfe/aEnaZV.UamYhpkXAyqC', 'super_admin', 'active', NULL, '72', NULL, NULL, '0', NULL, '0', NULL, '2026-04-11 07:56:17', '2026-04-11 07:56:17');

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `actor_type` enum('admin','teacher','student') NOT NULL,
  `actor_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(100) NOT NULL,
  `target_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `audit_logs` (`id`, `actor_type`, `actor_id`, `action`, `target_type`, `target_id`, `description`, `created_at`) VALUES ('1', 'student', '1', 'logout', 'student', '1', 'User logged out | IP: ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-11 08:00:31');
INSERT INTO `audit_logs` (`id`, `actor_type`, `actor_id`, `action`, `target_type`, `target_id`, `description`, `created_at`) VALUES ('2', 'teacher', '1', 'logout', 'teacher', '1', 'User logged out | IP: ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-11 08:04:34');
INSERT INTO `audit_logs` (`id`, `actor_type`, `actor_id`, `action`, `target_type`, `target_id`, `description`, `created_at`) VALUES ('3', 'student', '1', 'logout', 'student', '1', 'User logged out | IP: ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-11 08:05:45');
INSERT INTO `audit_logs` (`id`, `actor_type`, `actor_id`, `action`, `target_type`, `target_id`, `description`, `created_at`) VALUES ('4', 'student', '1', 'logout', 'student', '1', 'User logged out | IP: ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-11 16:24:37');
INSERT INTO `audit_logs` (`id`, `actor_type`, `actor_id`, `action`, `target_type`, `target_id`, `description`, `created_at`) VALUES ('5', 'teacher', '1', 'logout', 'teacher', '1', 'User logged out | IP: ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-11 16:46:50');
INSERT INTO `audit_logs` (`id`, `actor_type`, `actor_id`, `action`, `target_type`, `target_id`, `description`, `created_at`) VALUES ('6', 'student', '1', 'logout', 'student', '1', 'User logged out | IP: ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-11 16:59:50');
INSERT INTO `audit_logs` (`id`, `actor_type`, `actor_id`, `action`, `target_type`, `target_id`, `description`, `created_at`) VALUES ('7', 'student', '1', 'logout', 'student', '1', 'User logged out | IP: ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-11 17:56:21');
INSERT INTO `audit_logs` (`id`, `actor_type`, `actor_id`, `action`, `target_type`, `target_id`, `description`, `created_at`) VALUES ('8', 'teacher', '1', 'logout', 'teacher', '1', 'User logged out | IP: ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-11 18:09:17');
INSERT INTO `audit_logs` (`id`, `actor_type`, `actor_id`, `action`, `target_type`, `target_id`, `description`, `created_at`) VALUES ('9', 'student', '1', 'logout', 'student', '1', 'User logged out | IP: ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-11 19:05:30');
INSERT INTO `audit_logs` (`id`, `actor_type`, `actor_id`, `action`, `target_type`, `target_id`, `description`, `created_at`) VALUES ('10', 'teacher', '1', 'logout', 'teacher', '1', 'User logged out | IP: ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-11 22:15:29');

DROP TABLE IF EXISTS `backup_runs`;
CREATE TABLE `backup_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_key` varchar(120) NOT NULL,
  `zip_path` varchar(255) NOT NULL,
  `manifest_path` varchar(255) DEFAULT NULL,
  `uploaded_to_drive` tinyint(1) NOT NULL DEFAULT 0,
  `drive_file_id` varchar(255) DEFAULT NULL,
  `status` enum('success','failed') NOT NULL DEFAULT 'success',
  `notes` text DEFAULT NULL,
  `created_by_admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `run_key` (`run_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_type` enum('admin','teacher','student') NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning') NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_type`,`user_id`),
  KEY `idx_notifications_read` (`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `notifications` (`id`, `user_type`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES ('1', 'student', '1', 'Welcome', 'Your account has been created successfully.', 'success', '0', '2026-04-11 07:56:17');
INSERT INTO `notifications` (`id`, `user_type`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES ('2', 'student', '3', 'View-only account', 'Your account is currently view-only. Request reactivation if you need submission access again.', 'warning', '0', '2026-04-11 07:56:17');
INSERT INTO `notifications` (`id`, `user_type`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES ('3', 'teacher', '1', 'Subject Ready', 'Information Management is ready for section assignment and review.', 'info', '0', '2026-04-11 07:56:17');
INSERT INTO `notifications` (`id`, `user_type`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES ('4', 'admin', '1', 'System Ready', 'Initial academic structure has been seeded successfully.', 'success', '0', '2026-04-11 07:56:17');
INSERT INTO `notifications` (`id`, `user_type`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES ('5', 'student', '1', 'Submission reviewed', 'Your submission for Information Management has been updated to graded with grade 95.', 'success', '0', '2026-04-11 08:04:12');
INSERT INTO `notifications` (`id`, `user_type`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES ('6', 'student', '2', 'Submission reviewed', 'Your submission for Information Management has been updated to graded with grade 95.', 'success', '0', '2026-04-11 08:04:12');

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_type` enum('teacher','student') NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `reactivation_requests`;
CREATE TABLE `reactivation_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `current_section_id` int(11) NOT NULL,
  `requested_section_id` int(11) DEFAULT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','denied') NOT NULL DEFAULT 'pending',
  `admin_note` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_reactivation_student` (`student_id`),
  KEY `fk_reactivation_current_section` (`current_section_id`),
  KEY `fk_reactivation_requested_section` (`requested_section_id`),
  KEY `fk_reactivation_reviewed_by` (`reviewed_by`),
  CONSTRAINT `fk_reactivation_current_section` FOREIGN KEY (`current_section_id`) REFERENCES `sections` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_reactivation_requested_section` FOREIGN KEY (`requested_section_id`) REFERENCES `sections` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_reactivation_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_reactivation_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `school_years`;
CREATE TABLE `school_years` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(20) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `label` (`label`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `school_years` (`id`, `label`, `is_active`, `created_at`, `updated_at`) VALUES ('1', '2025-2026', '1', '2026-04-11 07:56:17', '2026-04-11 07:56:17');

DROP TABLE IF EXISTS `section_subjects`;
CREATE TABLE `section_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `submission_deadline` datetime DEFAULT NULL,
  `deadline_warning_hours` int(11) NOT NULL DEFAULT 72,
  `deadline_warning_sent_at` datetime DEFAULT NULL,
  `deadline_locked_notice_sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_section_subject` (`section_id`,`subject_id`),
  KEY `fk_section_subjects_subject` (`subject_id`),
  CONSTRAINT `fk_section_subjects_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_section_subjects_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `section_subjects` (`id`, `section_id`, `subject_id`, `submission_deadline`, `deadline_warning_hours`, `deadline_warning_sent_at`, `deadline_locked_notice_sent_at`, `created_at`) VALUES ('1', '1', '1', NULL, '72', NULL, NULL, '2026-04-11 07:56:17');
INSERT INTO `section_subjects` (`id`, `section_id`, `subject_id`, `submission_deadline`, `deadline_warning_hours`, `deadline_warning_sent_at`, `deadline_locked_notice_sent_at`, `created_at`) VALUES ('2', '2', '1', NULL, '72', NULL, NULL, '2026-04-11 07:56:17');
INSERT INTO `section_subjects` (`id`, `section_id`, `subject_id`, `submission_deadline`, `deadline_warning_hours`, `deadline_warning_sent_at`, `deadline_locked_notice_sent_at`, `created_at`) VALUES ('3', '3', '2', NULL, '72', NULL, NULL, '2026-04-11 07:56:17');

DROP TABLE IF EXISTS `sections`;
CREATE TABLE `sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_name` varchar(100) NOT NULL,
  `school_year_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `status` enum('active','inactive','archived') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_section_year_sem` (`section_name`,`school_year_id`,`semester_id`),
  KEY `fk_sections_school_year` (`school_year_id`),
  KEY `fk_sections_semester` (`semester_id`),
  CONSTRAINT `fk_sections_school_year` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sections_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sections` (`id`, `section_name`, `school_year_id`, `semester_id`, `status`, `notes`, `created_at`, `updated_at`) VALUES ('1', 'BSIT 22006', '1', '1', 'active', 'Regular section', '2026-04-11 07:56:17', '2026-04-11 07:56:17');
INSERT INTO `sections` (`id`, `section_name`, `school_year_id`, `semester_id`, `status`, `notes`, `created_at`, `updated_at`) VALUES ('2', 'BSIT 22008', '1', '1', 'active', 'Regular section', '2026-04-11 07:56:17', '2026-04-11 07:56:17');
INSERT INTO `sections` (`id`, `section_name`, `school_year_id`, `semester_id`, `status`, `notes`, `created_at`, `updated_at`) VALUES ('3', 'BSIT 22010', '1', '1', 'active', 'Regular section', '2026-04-11 07:56:17', '2026-04-11 07:56:17');

DROP TABLE IF EXISTS `semesters`;
CREATE TABLE `semesters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_year_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_semester` (`school_year_id`,`name`),
  CONSTRAINT `fk_semesters_school_year` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `semesters` (`id`, `school_year_id`, `name`, `is_active`, `created_at`, `updated_at`) VALUES ('1', '1', '1st Semester', '1', '2026-04-11 07:56:17', '2026-04-11 07:56:17');
INSERT INTO `semesters` (`id`, `school_year_id`, `name`, `is_active`, `created_at`, `updated_at`) VALUES ('2', '1', '2nd Semester', '0', '2026-04-11 07:56:17', '2026-04-11 07:56:17');

DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(50) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `section_id` int(11) NOT NULL,
  `account_status` enum('pending','active','view_only','inactive','archived') NOT NULL DEFAULT 'pending',
  `can_submit` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_id` (`student_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_students_section` (`section_id`),
  CONSTRAINT `fk_students_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `students` (`id`, `student_id`, `full_name`, `email`, `username`, `password_hash`, `avatar_path`, `section_id`, `account_status`, `can_submit`, `created_at`, `updated_at`) VALUES ('1', '2025-0001', 'Juan Dela Cruz', 'juan@example.com', 'juan2025', '$2y$12$DdPE2fkqQDLDfwhC/Jq6t.x4PreQgB72MECuYbs1bH8NtjKdcRO4i', NULL, '1', 'active', '1', '2026-04-11 07:56:17', '2026-04-11 07:56:17');
INSERT INTO `students` (`id`, `student_id`, `full_name`, `email`, `username`, `password_hash`, `avatar_path`, `section_id`, `account_status`, `can_submit`, `created_at`, `updated_at`) VALUES ('2', '2025-0002', 'Maria Santos', 'maria@example.com', 'maria2025', '$2y$12$DdPE2fkqQDLDfwhC/Jq6t.x4PreQgB72MECuYbs1bH8NtjKdcRO4i', NULL, '1', 'active', '1', '2026-04-11 07:56:17', '2026-04-11 07:56:17');
INSERT INTO `students` (`id`, `student_id`, `full_name`, `email`, `username`, `password_hash`, `avatar_path`, `section_id`, `account_status`, `can_submit`, `created_at`, `updated_at`) VALUES ('3', '2025-0003', 'Pedro Reyes', 'pedro@example.com', 'pedro2025', '$2y$12$DdPE2fkqQDLDfwhC/Jq6t.x4PreQgB72MECuYbs1bH8NtjKdcRO4i', NULL, '2', 'view_only', '0', '2026-04-11 07:56:17', '2026-04-11 07:56:17');
INSERT INTO `students` (`id`, `student_id`, `full_name`, `email`, `username`, `password_hash`, `avatar_path`, `section_id`, `account_status`, `can_submit`, `created_at`, `updated_at`) VALUES ('4', '2025-0004', 'Ana Cruz', 'ana@example.com', '2025-0004', '$2y$12$DdPE2fkqQDLDfwhC/Jq6t.x4PreQgB72MECuYbs1bH8NtjKdcRO4i', NULL, '2', 'pending', '0', '2026-04-11 07:56:17', '2026-04-11 07:56:17');

DROP TABLE IF EXISTS `subject_resources`;
CREATE TABLE `subject_resources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `is_visible_to_students` tinyint(1) NOT NULL DEFAULT 1,
  `created_by_teacher_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_subject_resources_subject` (`subject_id`),
  KEY `fk_subject_resources_teacher` (`created_by_teacher_id`),
  CONSTRAINT `fk_subject_resources_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_subject_resources_teacher` FOREIGN KEY (`created_by_teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_code` varchar(50) NOT NULL,
  `subject_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `teacher_id` int(11) NOT NULL,
  `school_year_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `status` enum('active','inactive','archived') NOT NULL DEFAULT 'active',
  `submission_deadline` datetime DEFAULT NULL,
  `deadline_warning_hours` int(11) NOT NULL DEFAULT 72,
  `deadline_warning_sent_at` datetime DEFAULT NULL,
  `deadline_locked_notice_sent_at` datetime DEFAULT NULL,
  `allow_late_submissions` tinyint(1) NOT NULL DEFAULT 0,
  `late_submission_until` datetime DEFAULT NULL,
  `teacher_submission_locked` tinyint(1) NOT NULL DEFAULT 0,
  `teacher_submission_lock_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_subject_term_teacher` (`subject_code`,`teacher_id`,`school_year_id`,`semester_id`),
  KEY `fk_subjects_teacher` (`teacher_id`),
  KEY `fk_subjects_school_year` (`school_year_id`),
  KEY `fk_subjects_semester` (`semester_id`),
  CONSTRAINT `fk_subjects_school_year` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_subjects_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_subjects_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `description`, `teacher_id`, `school_year_id`, `semester_id`, `status`, `submission_deadline`, `deadline_warning_hours`, `deadline_warning_sent_at`, `deadline_locked_notice_sent_at`, `allow_late_submissions`, `late_submission_until`, `teacher_submission_locked`, `teacher_submission_lock_note`, `created_at`, `updated_at`) VALUES ('1', 'IM101', 'Information Management', 'Project submissions for Information Management.', '1', '1', '1', 'active', NULL, '72', NULL, NULL, '0', NULL, '0', NULL, '2026-04-11 07:56:17', '2026-04-11 07:56:17');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `description`, `teacher_id`, `school_year_id`, `semester_id`, `status`, `submission_deadline`, `deadline_warning_hours`, `deadline_warning_sent_at`, `deadline_locked_notice_sent_at`, `allow_late_submissions`, `late_submission_until`, `teacher_submission_locked`, `teacher_submission_lock_note`, `created_at`, `updated_at`) VALUES ('2', 'WS201', 'Web Systems', 'Project submissions for Web Systems.', '2', '1', '1', 'active', NULL, '72', NULL, NULL, '0', NULL, '0', NULL, '2026-04-11 07:56:17', '2026-04-11 07:56:17');

DROP TABLE IF EXISTS `submission_activities`;
CREATE TABLE `submission_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_id` int(11) NOT NULL,
  `title` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','published','closed','archived') NOT NULL DEFAULT 'draft',
  `opens_at` datetime DEFAULT NULL,
  `deadline_at` datetime DEFAULT NULL,
  `allow_late` tinyint(1) NOT NULL DEFAULT 0,
  `late_until` datetime DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `lock_note` text DEFAULT NULL,
  `submission_mode` enum('individual','team') NOT NULL DEFAULT 'team',
  `min_members` int(11) NOT NULL DEFAULT 1,
  `max_members` int(11) NOT NULL DEFAULT 5,
  `allow_resubmission` tinyint(1) NOT NULL DEFAULT 0,
  `max_resubmissions` int(11) NOT NULL DEFAULT 1,
  `require_file` tinyint(1) NOT NULL DEFAULT 0,
  `require_repository` tinyint(1) NOT NULL DEFAULT 1,
  `require_live_url` tinyint(1) NOT NULL DEFAULT 1,
  `require_demo_access` tinyint(1) NOT NULL DEFAULT 0,
  `require_notes` tinyint(1) NOT NULL DEFAULT 0,
  `created_by_teacher_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_submission_activities_subject` (`subject_id`,`status`,`deadline_at`),
  KEY `idx_submission_activities_teacher` (`created_by_teacher_id`,`status`),
  CONSTRAINT `fk_submission_activities_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_submission_activities_teacher` FOREIGN KEY (`created_by_teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `submission_activity_sections`;
CREATE TABLE `submission_activity_sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `activity_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_activity_section` (`activity_id`,`section_id`),
  KEY `idx_activity_section` (`section_id`),
  CONSTRAINT `fk_submission_activity_sections_activity` FOREIGN KEY (`activity_id`) REFERENCES `submission_activities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_submission_activity_sections_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `submission_history`;
CREATE TABLE `submission_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `submission_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `version_no` int(11) NOT NULL,
  `action_type` enum('created','edited','reviewed','graded','deleted','restored') NOT NULL DEFAULT 'created',
  `actor_user_id` int(11) DEFAULT NULL,
  `actor_role` enum('student','teacher','admin','system') NOT NULL DEFAULT 'system',
  `actor_name` varchar(150) DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `grade` varchar(50) DEFAULT NULL,
  `assigned_system` varchar(255) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `project_url` text DEFAULT NULL,
  `video_url` text DEFAULT NULL,
  `contact_email` varchar(150) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `teacher_feedback` text DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `snapshot_payload` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_submission_history_section` (`section_id`),
  KEY `idx_submission_history_submission` (`submission_id`,`version_no`),
  KEY `idx_submission_history_team` (`team_id`),
  KEY `idx_submission_history_subject` (`subject_id`),
  CONSTRAINT `fk_submission_history_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_submission_history_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_submission_history_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `submission_history` (`id`, `submission_id`, `team_id`, `subject_id`, `section_id`, `version_no`, `action_type`, `actor_user_id`, `actor_role`, `actor_name`, `status`, `grade`, `assigned_system`, `company_name`, `project_url`, `video_url`, `contact_email`, `attachment_path`, `teacher_feedback`, `review_notes`, `snapshot_payload`, `created_at`) VALUES ('1', '1', '1', '1', '1', '1', 'created', NULL, 'system', 'System backfill', 'reviewed', NULL, 'Student Project Portal', 'Campus Group', 'https://example.com/project', 'https://example.com/video', 'group@example.com', NULL, 'Initial seeded team submission visible to both leader and members.', 'Seed data', '{\"submission\":{\"id\":1,\"team_id\":1,\"subject_id\":1,\"section_id\":1,\"assigned_system\":\"Student Project Portal\",\"company_name\":\"Campus Group\",\"project_url\":\"https://example.com/project\",\"video_url\":\"https://example.com/video\",\"contact_email\":\"group@example.com\",\"status\":\"reviewed\",\"grade\":null,\"teacher_feedback\":\"Initial seeded team submission visible to both leader and members.\",\"review_notes\":\"Seed data\",\"attachment_path\":null,\"submitted_at\":\"2026-04-11 07:56:17\",\"updated_at\":\"2026-04-11 07:56:17\"},\"members\":[{\"member_name\":\"Juan Dela Cruz\"},{\"member_name\":\"Maria Santos\"}]}', '2026-04-11 07:56:29');
INSERT INTO `submission_history` (`id`, `submission_id`, `team_id`, `subject_id`, `section_id`, `version_no`, `action_type`, `actor_user_id`, `actor_role`, `actor_name`, `status`, `grade`, `assigned_system`, `company_name`, `project_url`, `video_url`, `contact_email`, `attachment_path`, `teacher_feedback`, `review_notes`, `snapshot_payload`, `created_at`) VALUES ('2', '1', '1', '1', '1', '2', 'graded', '1', 'teacher', 'Teacher One', 'graded', '95', 'Student Project Portal', 'Campus Group', 'https://example.com/project', 'https://example.com/video', 'group@example.com', NULL, 'Initial seeded team submission visible to both leader and members.', 'Seed data', '{\"submission\":{\"id\":1,\"team_id\":1,\"subject_id\":1,\"section_id\":1,\"assigned_system\":\"Student Project Portal\",\"company_name\":\"Campus Group\",\"project_url\":\"https://example.com/project\",\"video_url\":\"https://example.com/video\",\"contact_email\":\"group@example.com\",\"status\":\"graded\",\"grade\":\"95\",\"teacher_feedback\":\"Initial seeded team submission visible to both leader and members.\",\"review_notes\":\"Seed data\",\"attachment_path\":null,\"submitted_at\":\"2026-04-11 07:56:17\",\"updated_at\":\"2026-04-11 08:04:12\"},\"members\":[{\"member_name\":\"Juan Dela Cruz\"},{\"member_name\":\"Maria Santos\"}]}', '2026-04-11 08:04:12');

DROP TABLE IF EXISTS `submission_members`;
CREATE TABLE `submission_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `submission_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `member_name` varchar(150) NOT NULL,
  `student_id_snapshot` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_submission_members_submission` (`submission_id`),
  KEY `idx_submission_members_student` (`student_id`),
  CONSTRAINT `fk_submission_members_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_submission_members_submission` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `submission_members` (`id`, `submission_id`, `student_id`, `member_name`, `student_id_snapshot`, `created_at`) VALUES ('1', '1', NULL, 'Juan Dela Cruz', NULL, '2026-04-11 07:56:17');
INSERT INTO `submission_members` (`id`, `submission_id`, `student_id`, `member_name`, `student_id_snapshot`, `created_at`) VALUES ('2', '1', NULL, 'Maria Santos', NULL, '2026-04-11 07:56:17');

DROP TABLE IF EXISTS `submissions`;
CREATE TABLE `submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `submitted_by_student_id` int(11) DEFAULT NULL,
  `section_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `activity_id` int(11) DEFAULT NULL,
  `assigned_system` varchar(255) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `project_url` text NOT NULL,
  `video_url` text NOT NULL,
  `admin_username` varchar(100) DEFAULT NULL,
  `admin_password` varchar(255) DEFAULT NULL,
  `user_username` varchar(100) DEFAULT NULL,
  `user_password` varchar(255) DEFAULT NULL,
  `contact_email` varchar(150) NOT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','reviewed','graded','archived') NOT NULL DEFAULT 'pending',
  `grade` varchar(50) DEFAULT NULL,
  `teacher_feedback` text DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_team_activity` (`team_id`,`activity_id`),
  KEY `fk_submissions_student` (`student_id`),
  KEY `fk_submissions_submitted_by` (`submitted_by_student_id`),
  KEY `fk_submissions_section` (`section_id`),
  KEY `fk_submissions_subject` (`subject_id`),
  KEY `fk_submissions_activity` (`activity_id`),
  CONSTRAINT `fk_submissions_activity` FOREIGN KEY (`activity_id`) REFERENCES `submission_activities` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_submissions_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_submissions_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_submissions_submitted_by` FOREIGN KEY (`submitted_by_student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_submissions_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `submissions` (`id`, `team_id`, `student_id`, `submitted_by_student_id`, `section_id`, `subject_id`, `activity_id`, `assigned_system`, `company_name`, `project_url`, `video_url`, `admin_username`, `admin_password`, `user_username`, `user_password`, `contact_email`, `attachment_path`, `status`, `grade`, `teacher_feedback`, `review_notes`, `submitted_at`, `updated_at`) VALUES ('1', '1', '1', '1', '1', '1', NULL, 'Student Project Portal', 'Campus Group', 'https://example.com/project', 'https://example.com/video', 'admin_demo', 'demo123', 'user_demo', 'demo123', 'group@example.com', NULL, 'graded', '95', 'Initial seeded team submission visible to both leader and members.', 'Seed data', '2026-04-11 07:56:17', '2026-04-11 08:04:12');

DROP TABLE IF EXISTS `teachers`;
CREATE TABLE `teachers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` varchar(50) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','archived') NOT NULL DEFAULT 'active',
  `submission_deadline` datetime DEFAULT NULL,
  `deadline_warning_hours` int(11) NOT NULL DEFAULT 72,
  `deadline_warning_sent_at` datetime DEFAULT NULL,
  `deadline_locked_notice_sent_at` datetime DEFAULT NULL,
  `allow_late_submissions` tinyint(1) NOT NULL DEFAULT 0,
  `late_submission_until` datetime DEFAULT NULL,
  `teacher_submission_locked` tinyint(1) NOT NULL DEFAULT 0,
  `teacher_submission_lock_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `teacher_id` (`teacher_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `teachers` (`id`, `teacher_id`, `full_name`, `email`, `username`, `password_hash`, `avatar_path`, `status`, `submission_deadline`, `deadline_warning_hours`, `deadline_warning_sent_at`, `deadline_locked_notice_sent_at`, `allow_late_submissions`, `late_submission_until`, `teacher_submission_locked`, `teacher_submission_lock_note`, `created_at`, `updated_at`) VALUES ('1', 'T-001', 'Teacher One', 'teacher1@example.com', 'teacher1', '$2y$12$XPo/qSaTKIxVIejTHc1Yn.XacZYkzAZ3Ju5k3quDoXYTLD0hTusSi', NULL, 'active', NULL, '72', NULL, NULL, '0', NULL, '0', NULL, '2026-04-11 07:56:17', '2026-04-11 07:56:17');
INSERT INTO `teachers` (`id`, `teacher_id`, `full_name`, `email`, `username`, `password_hash`, `avatar_path`, `status`, `submission_deadline`, `deadline_warning_hours`, `deadline_warning_sent_at`, `deadline_locked_notice_sent_at`, `allow_late_submissions`, `late_submission_until`, `teacher_submission_locked`, `teacher_submission_lock_note`, `created_at`, `updated_at`) VALUES ('2', 'T-002', 'Teacher Two', 'teacher2@example.com', 'teacher2', '$2y$12$XPo/qSaTKIxVIejTHc1Yn.XacZYkzAZ3Ju5k3quDoXYTLD0hTusSi', NULL, 'active', NULL, '72', NULL, NULL, '0', NULL, '0', NULL, '2026-04-11 07:56:17', '2026-04-11 07:56:17');

DROP TABLE IF EXISTS `team_members`;
CREATE TABLE `team_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `role` enum('leader','member') NOT NULL DEFAULT 'member',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_team_member` (`team_id`,`student_id`),
  KEY `fk_team_members_student` (`student_id`),
  CONSTRAINT `fk_team_members_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_team_members_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `team_members` (`id`, `team_id`, `student_id`, `role`, `created_at`) VALUES ('1', '1', '1', 'leader', '2026-04-11 07:56:17');
INSERT INTO `team_members` (`id`, `team_id`, `student_id`, `role`, `created_at`) VALUES ('2', '1', '2', 'member', '2026-04-11 07:56:17');

DROP TABLE IF EXISTS `teams`;
CREATE TABLE `teams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_id` int(11) NOT NULL,
  `activity_id` int(11) DEFAULT NULL,
  `section_id` int(11) NOT NULL,
  `leader_student_id` int(11) NOT NULL,
  `team_name` varchar(150) NOT NULL,
  `status` enum('active','inactive','archived') NOT NULL DEFAULT 'active',
  `submission_deadline` datetime DEFAULT NULL,
  `deadline_warning_hours` int(11) NOT NULL DEFAULT 72,
  `deadline_warning_sent_at` datetime DEFAULT NULL,
  `deadline_locked_notice_sent_at` datetime DEFAULT NULL,
  `allow_late_submissions` tinyint(1) NOT NULL DEFAULT 0,
  `late_submission_until` datetime DEFAULT NULL,
  `teacher_submission_locked` tinyint(1) NOT NULL DEFAULT 0,
  `teacher_submission_lock_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_leader_activity_team` (`leader_student_id`,`activity_id`),
  KEY `fk_teams_subject` (`subject_id`),
  KEY `fk_teams_section` (`section_id`),
  KEY `fk_teams_activity` (`activity_id`),
  CONSTRAINT `fk_teams_activity` FOREIGN KEY (`activity_id`) REFERENCES `submission_activities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_teams_leader` FOREIGN KEY (`leader_student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_teams_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_teams_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `teams` (`id`, `subject_id`, `activity_id`, `section_id`, `leader_student_id`, `team_name`, `status`, `submission_deadline`, `deadline_warning_hours`, `deadline_warning_sent_at`, `deadline_locked_notice_sent_at`, `allow_late_submissions`, `late_submission_until`, `teacher_submission_locked`, `teacher_submission_lock_note`, `created_at`, `updated_at`) VALUES ('1', '1', NULL, '1', '1', 'IM101 Team Juan', 'active', NULL, '72', NULL, NULL, '0', NULL, '0', NULL, '2026-04-11 07:56:17', '2026-04-11 07:56:17');

SET FOREIGN_KEY_CHECKS=1;
