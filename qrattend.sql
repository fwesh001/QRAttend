-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 10, 2026 at 07:07 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `qrattend`
--

-- --------------------------------------------------------

--
-- Table structure for table `administrators`
--

CREATE TABLE `administrators` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `username` varchar(60) NOT NULL,
  `email` varchar(160) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'bcrypt hash (PASSWORD_BCRYPT)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `administrators`
--

INSERT INTO `administrators` (`id`, `name`, `username`, `email`, `password`, `created_at`) VALUES
(1, 'System Administrator', 'admin', 'admin@qrattend.edu', '$2b$10$.UmlCxlsfO5RXDsq75VmWeG1KwjB0dOCVmNGKTHJLKBKpiPmLK1da', '2026-07-10 03:28:22');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `session_id` int(10) UNSIGNED NOT NULL,
  `attendance_status` enum('Present','Absent') NOT NULL DEFAULT 'Present',
  `scanned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_sessions`
--

CREATE TABLE `attendance_sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `course_allocation_id` int(10) UNSIGNED NOT NULL,
  `qr_token` varchar(255) NOT NULL COMMENT 'cryptographic dynamic token',
  `session_pin` varchar(6) NOT NULL COMMENT '6-digit alphanumeric fallback pin',
  `duration_minutes` int(10) UNSIGNED NOT NULL DEFAULT 15,
  `max_students` int(10) UNSIGNED NULL DEFAULT NULL COMMENT 'lecturer-set class size for this session',
  `status` enum('Open','Closed') NOT NULL DEFAULT 'Open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL COMMENT 'created_at + duration_minutes'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_type` varchar(30) NOT NULL COMMENT 'admin | lecturer | student',
  `user_id` int(10) UNSIGNED NOT NULL,
  `action_performed` text NOT NULL,
  `ip_address` varchar(45) NOT NULL COMMENT 'IPv4 or IPv6',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_type`, `user_id`, `action_performed`, `ip_address`, `timestamp`) VALUES
(1, 'admin', 1, 'Successful login', '::1', '2026-07-10 04:03:25'),
(2, 'admin', 1, 'Logout', '::1', '2026-07-10 04:04:42'),
(3, 'student', 1, 'Successful login', '::1', '2026-07-10 04:06:11');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(10) UNSIGNED NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `course_title` varchar(160) NOT NULL,
  `credit_units` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `course_code`, `course_title`, `credit_units`) VALUES
(1, 'CSC 401', 'Database Management Systems', 3);

-- --------------------------------------------------------

--
-- Table structure for table `course_allocations`
--

CREATE TABLE `course_allocations` (
  `id` int(10) UNSIGNED NOT NULL,
  `lecturer_id` int(10) UNSIGNED NOT NULL,
  `course_id` int(10) UNSIGNED NOT NULL,
  `allocated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_allocations`
--

INSERT INTO `course_allocations` (`id`, `lecturer_id`, `course_id`, `allocated_at`) VALUES
(1, 1, 1, '2026-07-10 03:28:22');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `faculty` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `faculty`) VALUES
(1, 'Computer Science', 'School of Technology');

-- --------------------------------------------------------

--
-- Table structure for table `lecturers`
--

CREATE TABLE `lecturers` (
  `id` int(10) UNSIGNED NOT NULL,
  `staff_no` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'bcrypt hash',
  `department_id` int(10) UNSIGNED NOT NULL,
  `default_duration_minutes` int(10) UNSIGNED NOT NULL DEFAULT 15 COMMENT 'lecturer preferred session length',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lecturers`
--

INSERT INTO `lecturers` (`id`, `staff_no`, `name`, `email`, `password`, `department_id`, `created_at`) VALUES
(1, 'STAFF/001', 'Dr. Ada Okafor', 'ada.okafor@qrattend.edu', '$2b$10$.UmlCxlsfO5RXDsq75VmWeG1KwjB0dOCVmNGKTHJLKBKpiPmLK1da', 1, '2026-07-10 03:28:22');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(10) UNSIGNED NOT NULL,
  `matric_no` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL,
  `level` varchar(20) NOT NULL COMMENT 'e.g. HND II, ND I',
  `email` varchar(160) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'bcrypt hash',
  `department_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `matric_no`, `name`, `level`, `email`, `password`, `department_id`, `created_at`) VALUES
(1, 'CSC/HND/001', 'Okpihumu Peter James', 'HND II', 'okpihumu.peter@qrattend.edu', '$2b$10$.UmlCxlsfO5RXDsq75VmWeG1KwjB0dOCVmNGKTHJLKBKpiPmLK1da', 1, '2026-07-10 03:28:22'),
(2, 'CSC/HND/002', 'Mary Johnson', 'HND II', 'mary.johnson@qrattend.edu', '$2b$10$.UmlCxlsfO5RXDsq75VmWeG1KwjB0dOCVmNGKTHJLKBKpiPmLK1da', 1, '2026-07-10 03:28:22'),
(3, 'CSC/HND/003', 'Musa Ibrahim', 'HND II', 'musa.ibrahim@qrattend.edu', '$2b$10$.UmlCxlsfO5RXDsq75VmWeG1KwjB0dOCVmNGKTHJLKBKpiPmLK1da', 1, '2026-07-10 03:28:22');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `administrators`
--
ALTER TABLE `administrators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_administrators_username` (`username`),
  ADD UNIQUE KEY `uq_administrators_email` (`email`),
  ADD KEY `idx_administrators_username` (`username`);

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_records_student_session` (`student_id`,`session_id`),
  ADD KEY `idx_records_student` (`student_id`),
  ADD KEY `idx_records_session` (`session_id`),
  ADD KEY `idx_records_status` (`attendance_status`);

--
-- Indexes for table `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sessions_qr_token` (`qr_token`),
  ADD KEY `idx_sessions_allocation` (`course_allocation_id`),
  ADD KEY `idx_sessions_status` (`status`),
  ADD KEY `idx_sessions_expires` (`expires_at`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user` (`user_type`,`user_id`),
  ADD KEY `idx_audit_timestamp` (`timestamp`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_courses_course_code` (`course_code`);

--
-- Indexes for table `course_allocations`
--
ALTER TABLE `course_allocations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_alloc_lecturer_course` (`lecturer_id`,`course_id`),
  ADD KEY `idx_allocations_lecturer` (`lecturer_id`),
  ADD KEY `idx_allocations_course` (`course_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_departments_name` (`name`);

--
-- Indexes for table `lecturers`
--
ALTER TABLE `lecturers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_lecturers_staff_no` (`staff_no`),
  ADD UNIQUE KEY `uq_lecturers_email` (`email`),
  ADD KEY `idx_lecturers_department` (`department_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_students_matric_no` (`matric_no`),
  ADD UNIQUE KEY `uq_students_email` (`email`),
  ADD KEY `idx_students_department` (`department_id`),
  ADD KEY `idx_students_level` (`level`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `administrators`
--
ALTER TABLE `administrators`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `course_allocations`
--
ALTER TABLE `course_allocations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `lecturers`
--
ALTER TABLE `lecturers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD CONSTRAINT `fk_records_session` FOREIGN KEY (`session_id`) REFERENCES `attendance_sessions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_records_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  ADD CONSTRAINT `fk_sessions_allocation` FOREIGN KEY (`course_allocation_id`) REFERENCES `course_allocations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `course_allocations`
--
ALTER TABLE `course_allocations`
  ADD CONSTRAINT `fk_allocations_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_allocations_lecturer` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `lecturers`
--
ALTER TABLE `lecturers`
  ADD CONSTRAINT `fk_lecturers_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
