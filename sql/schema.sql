-- =============================================================================
-- QRAttend :: Database Schema (MySQL 8.x)
-- Web-Based Attendance Monitoring System Using QR Code Technology
-- -----------------------------------------------------------------------------
-- Target DB : qrattend
-- Charset   : utf8mb4 / utf8mb4_unicode_ci  (full Unicode incl. emoji & 4-byte)
-- Engine    : InnoDB (transactional + FK support)
-- Normalized: 3NF (no repeating groups, full FD on PK, no transitive deps)
-- -----------------------------------------------------------------------------
-- Notes for the developer:
--   * All passwords are bcrypt hashes (PASSWORD_BCRYPT) -> VARCHAR(255).
--   * FKs use ON DELETE RESTRICT where an orphan would corrupt meaning, and
--     ON DELETE CASCADE where child rows are meaningless without the parent
--     (e.g. attendance_records without a session).
--   * Composite UNIQUE keys block the duplicate-scan / duplicate-allocation
--     anomalies called out in the PRD (§2.2 Unique Restrictions).
--   * Indexes are added on every FK + frequently filtered column to keep the
--     <500ms check-in target realistic under 60-100 concurrent writes.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 0. Create / select database
-- -----------------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `qrattend`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `qrattend`;

-- -----------------------------------------------------------------------------
-- 1. administrators  (Core User :: system management access)
-- -----------------------------------------------------------------------------
CREATE TABLE `administrators` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `username`   VARCHAR(60)  NOT NULL,
  `email`      VARCHAR(160) NOT NULL,
  `password`   VARCHAR(255) NOT NULL COMMENT 'bcrypt hash (PASSWORD_BCRYPT)',
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_administrators_username` (`username`),
  UNIQUE KEY `uq_administrators_email`    (`email`),
  KEY `idx_administrators_username` (`username`)   -- login lookup
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. departments  (Academic Structure :: faculty pairings)
-- -----------------------------------------------------------------------------
CREATE TABLE `departments` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`     VARCHAR(120) NOT NULL,
  `faculty`  VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_departments_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. lecturers  (Core User :: staff + department link)
-- -----------------------------------------------------------------------------
CREATE TABLE `lecturers` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_no`       VARCHAR(40)  NOT NULL,
  `name`           VARCHAR(120) NOT NULL,
  `email`          VARCHAR(160) NOT NULL,
  `password`       VARCHAR(255) NOT NULL COMMENT 'bcrypt hash',
  `department_id`  INT UNSIGNED NOT NULL,
  `default_duration_minutes` INT UNSIGNED NOT NULL DEFAULT 15 COMMENT 'lecturer preferred session length',
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lecturers_staff_no` (`staff_no`),
  UNIQUE KEY `uq_lecturers_email`    (`email`),
  KEY `idx_lecturers_department` (`department_id`),
  CONSTRAINT `fk_lecturers_department`
    FOREIGN KEY (`department_id`)
    REFERENCES `departments` (`id`)
    ON DELETE RESTRICT          -- never drop a dept that still has staff
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. students  (Core User :: matric, level, profile)
-- -----------------------------------------------------------------------------
CREATE TABLE `students` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `matric_no`      VARCHAR(40)  NOT NULL,
  `name`           VARCHAR(120) NOT NULL,
  `level`          VARCHAR(20)  NOT NULL COMMENT 'e.g. HND II, ND I',
  `email`          VARCHAR(160) NOT NULL,
  `password`       VARCHAR(255) NOT NULL COMMENT 'bcrypt hash',
  `department_id`  INT UNSIGNED NOT NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_students_matric_no` (`matric_no`),
  UNIQUE KEY `uq_students_email`     (`email`),
  KEY `idx_students_department` (`department_id`),
  KEY `idx_students_level`      (`level`),     -- at-risk rollups by level
  CONSTRAINT `fk_students_department`
    FOREIGN KEY (`department_id`)
    REFERENCES `departments` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. courses  (Academic Structure :: code, title, units)
-- -----------------------------------------------------------------------------
CREATE TABLE `courses` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_code`   VARCHAR(20)  NOT NULL,
  `course_title`  VARCHAR(160) NOT NULL,
  `credit_units`  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_courses_course_code` (`course_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. course_allocations  (Mapping Ledger :: lecturer <-> course)
--    Composite UNIQUE blocks assigning the same lecturer a course twice.
-- -----------------------------------------------------------------------------
CREATE TABLE `course_allocations` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lecturer_id`    INT UNSIGNED NOT NULL,
  `course_id`      INT UNSIGNED NOT NULL,
  `allocated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alloc_lecturer_course` (`lecturer_id`, `course_id`),
  KEY `idx_allocations_lecturer` (`lecturer_id`),
  KEY `idx_allocations_course`   (`course_id`),
  CONSTRAINT `fk_allocations_lecturer`
    FOREIGN KEY (`lecturer_id`)
    REFERENCES `lecturers` (`id`)
    ON DELETE CASCADE          -- if lecturer removed, their allocations go too
    ON UPDATE CASCADE,
  CONSTRAINT `fk_allocations_course`
    FOREIGN KEY (`course_id`)
    REFERENCES `courses` (`id`)
    ON DELETE RESTRICT         -- don't delete a course still allocated
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. attendance_sessions  (Engine Control :: token, pin, window)
-- -----------------------------------------------------------------------------
CREATE TABLE `attendance_sessions` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_allocation_id` INT UNSIGNED NOT NULL,
  `qr_token`          VARCHAR(255) NOT NULL COMMENT 'cryptographic dynamic token',
  `session_pin`       VARCHAR(6)   NOT NULL COMMENT '6-digit alphanumeric fallback pin',
  `duration_minutes`  INT UNSIGNED NOT NULL DEFAULT 15,
  `max_students`      INT UNSIGNED NULL     DEFAULT NULL COMMENT 'lecturer-set class size for this session',
  `status`            ENUM('Open','Closed') NOT NULL DEFAULT 'Open',
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`        DATETIME     NOT NULL COMMENT 'created_at + duration_minutes',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sessions_qr_token` (`qr_token`),
  KEY `idx_sessions_allocation` (`course_allocation_id`),
  KEY `idx_sessions_status`     (`status`),       -- "find open sessions" fast
  KEY `idx_sessions_expires`    (`expires_at`),   -- purge job
  CONSTRAINT `fk_sessions_allocation`
    FOREIGN KEY (`course_allocation_id`)
    REFERENCES `course_allocations` (`id`)
    ON DELETE CASCADE          -- a session is meaningless without its allocation
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. attendance_records  (Transaction Ledger :: student presence)
--    Composite UNIQUE on (student_id, session_id) blocks duplicate scans.
-- -----------------------------------------------------------------------------
CREATE TABLE `attendance_records` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`       INT UNSIGNED NOT NULL,
  `session_id`       INT UNSIGNED NOT NULL,
  `attendance_status` ENUM('Present','Absent') NOT NULL DEFAULT 'Present',
  `scanned_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_records_student_session` (`student_id`, `session_id`),
  KEY `idx_records_student`  (`student_id`),
  KEY `idx_records_session`  (`session_id`),
  KEY `idx_records_status`   (`attendance_status`),
  CONSTRAINT `fk_records_student`
    FOREIGN KEY (`student_id`)
    REFERENCES `students` (`id`)
    ON DELETE RESTRICT         -- keep the audit trail; never orphan a record
    ON UPDATE CASCADE,
  CONSTRAINT `fk_records_session`
    FOREIGN KEY (`session_id`)
    REFERENCES `attendance_sessions` (`id`)
    ON DELETE CASCADE          -- drop logs if the session is purged
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. audit_logs  (Security Ledger :: immutable event archive)
-- -----------------------------------------------------------------------------
CREATE TABLE `audit_logs` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_type`        VARCHAR(30)  NOT NULL COMMENT 'admin | lecturer | student',
  `user_id`          INT UNSIGNED NOT NULL,
  `action_performed` TEXT         NOT NULL,
  `ip_address`       VARCHAR(45)  NOT NULL COMMENT 'IPv4 or IPv6',
  `timestamp`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user`      (`user_type`, `user_id`),
  KEY `idx_audit_timestamp` (`timestamp`)        -- time-range viewer
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 10. SAMPLE SEEDING DATA  (for local testing / demo)
--     Default password for every seeded account is:  password123
--     (bcrypt hash below generated with PASSWORD_BCRYPT, cost 10)
-- =============================================================================
SET @seed_pw = '$2b$10$.UmlCxlsfO5RXDsq75VmWeG1KwjB0dOCVmNGKTHJLKBKpiPmLK1da';

-- 10.1 Department
INSERT INTO `departments` (`name`, `faculty`) VALUES
  ('Computer Science', 'School of Technology');

-- 10.2 Administrator (1)
INSERT INTO `administrators` (`name`, `username`, `email`, `password`) VALUES
  ('System Administrator', 'admin', 'admin@qrattend.edu', @seed_pw);

-- 10.3 Lecturer (1)  -> linked to the Computer Science department (id = 1)
INSERT INTO `lecturers` (`staff_no`, `name`, `email`, `password`, `department_id`) VALUES
  ('STAFF/001', 'Dr. Ada Okafor', 'ada.okafor@qrattend.edu', @seed_pw, 1);

-- 10.4 Students (3)  -> HND II, Computer Science (id = 1)
INSERT INTO `students` (`matric_no`, `name`, `level`, `email`, `password`, `department_id`) VALUES
  ('CSC/HND/001', 'Okpihumu Peter James', 'HND II', 'okpihumu.peter@qrattend.edu', @seed_pw, 1),
  ('CSC/HND/002', 'Mary Johnson',         'HND II', 'mary.johnson@qrattend.edu',   @seed_pw, 1),
  ('CSC/HND/003', 'Musa Ibrahim',         'HND II', 'musa.ibrahim@qrattend.edu',   @seed_pw, 1);

-- 10.5 Course (1)  -> CSC 401
INSERT INTO `courses` (`course_code`, `course_title`, `credit_units`) VALUES
  ('CSC 401', 'Database Management Systems', 3);

-- 10.6 Allocation  -> lecturer (id = 1) to course (id = 1)
INSERT INTO `course_allocations` (`lecturer_id`, `course_id`) VALUES
  (1, 1);

-- =============================================================================
-- End of schema.sql
-- =============================================================================
