-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 17, 2026 at 01:38 PM
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
-- Database: `optitask`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `details`, `timestamp`) VALUES
(1, 'AD001', 'LOGOUT', 'User logged out', '2026-05-18 19:00:03'),
(2, 'EM001', 'LOGIN', 'User logged in successfully', '2026-05-18 19:00:08'),
(3, 'EM001', 'LOGOUT', 'User logged out', '2026-05-18 19:00:11'),
(4, 'AD001', 'LOGIN', 'User logged in successfully', '2026-05-18 19:00:17'),
(5, 'AD001', 'DELETE_USER', 'Deleted user EM002', '2026-05-18 19:01:12'),
(6, 'AD001', 'LOGOUT', 'User logged out', '2026-05-18 19:06:34'),
(7, 'EM001', 'LOGIN', 'User logged in successfully', '2026-05-18 19:07:09'),
(8, 'EM001', 'LOGOUT', 'User logged out', '2026-05-18 19:07:29'),
(9, 'MG001', 'LOGIN', 'User logged in successfully', '2026-05-18 19:07:35'),
(10, 'MG001', 'LOGOUT', 'User logged out', '2026-05-18 19:08:08'),
(11, 'AD001', 'LOGIN', 'User logged in successfully', '2026-05-18 19:08:13'),
(12, 'AD001', 'LOGOUT', 'User logged out', '2026-05-18 19:08:40'),
(13, 'AD001', 'LOGIN', 'User logged in successfully', '2026-05-20 22:37:57'),
(14, 'AD001', 'LOGOUT', 'User logged out', '2026-05-20 22:53:40'),
(15, 'EM001', 'LOGIN', 'User logged in successfully', '2026-05-20 22:53:47'),
(16, 'EM001', 'LOGIN', 'User logged in successfully', '2026-06-10 00:44:25'),
(17, 'EM001', 'LOGOUT', 'User logged out', '2026-06-10 00:44:50'),
(18, 'MG001', 'LOGIN', 'User logged in successfully', '2026-06-10 00:44:57'),
(19, 'MG001', 'LOGOUT', 'User logged out', '2026-06-10 00:45:46'),
(20, 'AD001', 'LOGIN', 'User logged in successfully', '2026-06-10 00:45:53'),
(21, 'EM011', 'REGISTER', 'Registered new account as employee', '2026-06-10 02:50:14'),
(22, 'EM011', 'LOGIN', 'User logged in successfully', '2026-06-10 02:50:25'),
(23, 'EM011', 'LOGIN', 'User logged in successfully', '2026-06-10 02:50:35'),
(24, 'EM001', 'LOGIN', 'User logged in successfully', '2026-06-10 02:50:43'),
(25, 'EM001', 'LOGOUT', 'User logged out', '2026-06-10 02:51:18'),
(26, 'AD999', 'LOGIN', 'User logged in successfully', '2026-06-10 02:51:25'),
(27, 'EM011', 'REGISTER', 'Registered new account as Employee', '2026-06-10 02:54:19'),
(28, 'EM011', 'LOGIN', 'User logged in successfully', '2026-06-10 02:54:34');

-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `audit_id` varchar(25) NOT NULL,
  `user_id` varchar(25) DEFAULT NULL,
  `activity` varchar(250) NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `dept_id` int(10) UNSIGNED NOT NULL,
  `dept_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`dept_id`, `dept_name`, `description`) VALUES
(1, 'IT Development', 'Software and web engineering'),
(2, 'UI/UX Design', 'Interface design and user experience'),
(3, 'Project Management', 'Planning and quality control');

-- --------------------------------------------------------

--
-- Table structure for table `employee_skills`
--

CREATE TABLE `employee_skills` (
  `user_id` varchar(25) NOT NULL,
  `skill_id` int(10) UNSIGNED NOT NULL,
  `proficiency_level` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_skills`
--

INSERT INTO `employee_skills` (`user_id`, `skill_id`, `proficiency_level`) VALUES
('EM001', 1, 5),
('EM001', 2, 4),
('EM001', 8, 5),
('EM001', 9, 2),
('EM001', 14, 4),
('EM003', 1, 4),
('EM003', 2, 4),
('EM003', 3, 4),
('EM003', 13, 3),
('EM004', 2, 3),
('EM004', 3, 5),
('EM004', 9, 4),
('EM004', 10, 3),
('EM004', 13, 4),
('EM004', 15, 3),
('EM005', 3, 3),
('EM005', 4, 5),
('EM005', 8, 3),
('EM005', 12, 2),
('EM006', 1, 3),
('EM006', 3, 3),
('EM006', 4, 4),
('EM006', 8, 2),
('EM006', 11, 4),
('EM007', 3, 5),
('EM007', 4, 2),
('EM007', 11, 5),
('EM007', 15, 2),
('EM008', 3, 3),
('EM008', 5, 5),
('EM008', 6, 3),
('EM008', 10, 2),
('EM008', 14, 3),
('EM009', 5, 4),
('EM009', 13, 3),
('EM009', 14, 4),
('EM010', 4, 3),
('EM010', 6, 5),
('EM010', 11, 5),
('EM010', 15, 3);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(25) NOT NULL,
  `user_id` varchar(25) DEFAULT NULL,
  `notification_type` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'Unread'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `notification_type`, `message`, `timestamp`, `status`) VALUES
(1, 'EM001', 'Approval', 'Success! Your task \'Makan\' (#TK-A0466A) has been verified. ????', '2026-04-23 09:53:54', 'read'),
(2, 'EM001', 'Rejection', 'Task \'Reseach\' (#TK-23877D) was rejected. Please review and resubmit. ❌', '2026-04-23 10:02:38', 'read'),
(3, 'EM001', 'Approval', 'Success! Your task \'Design\' (#TK-2ADAFD) has been verified. ????', '2026-05-12 23:57:08', 'read'),
(4, 'EM001', 'Approval', 'Success! Your task \'Reseach\' (#TK-23877D) has been verified. ????', '2026-05-12 23:57:09', 'read'),
(5, 'EM001', 'Approval', 'Success! Your task \'Shopping\' (#TK-37D78D) has been verified. ????', '2026-05-12 23:57:09', 'read'),
(6, 'EM001', 'Approval', 'Success! Your task \'Kucing mandikan\' (#TK-03CEA1) has been verified. ????', '2026-05-12 23:57:13', 'read');

-- --------------------------------------------------------

--
-- Table structure for table `performance`
--

CREATE TABLE `performance` (
  `performance_id` varchar(25) NOT NULL,
  `user_id` varchar(25) DEFAULT NULL,
  `total_tasks` int(11) DEFAULT 0,
  `completed_tasks` int(11) DEFAULT 0,
  `performance_percentage` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `skills`
--

CREATE TABLE `skills` (
  `skill_id` int(10) UNSIGNED NOT NULL,
  `skill_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `skills`
--

INSERT INTO `skills` (`skill_id`, `skill_name`) VALUES
(13, 'Customer Support'),
(11, 'Data Analysis'),
(15, 'Database Administration'),
(4, 'Figma'),
(10, 'Marketing Strategy'),
(2, 'MySQL'),
(1, 'PHP'),
(7, 'PHP Development'),
(12, 'Project Management'),
(5, 'Project Planning'),
(8, 'React Frontend'),
(14, 'SEO Optimization'),
(6, 'System Auditing'),
(3, 'Tailwind CSS'),
(9, 'UI/UX Design');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `task_id` varchar(25) NOT NULL,
  `task_title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `due_date` date NOT NULL,
  `task_status` enum('To-Do','In Progress','Done','Verified') DEFAULT 'To-Do',
  `priority` varchar(20) DEFAULT NULL,
  `employee_id` varchar(25) DEFAULT NULL,
  `manager_notes` text DEFAULT NULL,
  `manager_id` varchar(10) DEFAULT NULL,
  `task_type` enum('Personal','Group') DEFAULT 'Personal',
  `task_file` varchar(255) DEFAULT NULL,
  `submission_file` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`task_id`, `task_title`, `description`, `start_date`, `due_date`, `task_status`, `priority`, `employee_id`, `manager_notes`, `manager_id`, `task_type`, `task_file`, `submission_file`) VALUES
('TK-03CEA1', 'Kucing mandikan', '', '2026-04-23', '2026-06-26', 'Verified', 'High', 'EM001', 'hello world', NULL, 'Group', NULL, '../uploads/submissions/1778601401_Untitled Diagram.pdf'),
('TK-23877D', 'Reseach', 'Buat research', '2026-04-23', '2026-04-30', 'Verified', 'High', 'EM001', 'hello world', NULL, 'Personal', NULL, '../uploads/submissions/1778601394_Untitled Diagram.pdf'),
('TK-2ADAFD', 'Design', 'Design la', '2026-04-23', '2026-04-28', 'Verified', 'Low', 'EM001', 'hello world', NULL, 'Personal', NULL, '../uploads/submissions/1776906687_red-hand-drawn-cherry-seamless-pattern-pink-social-template_53876-116104.avif'),
('TK-37D78D', 'Shopping', '', '2026-04-23', '2026-04-30', 'Verified', 'Medium', 'EM001', 'hello world', NULL, 'Personal', '../uploads/tasks/1776908403_CH05instructorPPT (1).ppt', '../uploads/submissions/1778601411_Untitled Diagram.pdf'),
('TK-A0466A', 'Makan', '', '2026-04-23', '2026-05-21', 'Verified', 'Medium', 'EM001', 'hello world', NULL, 'Personal', '../uploads/tasks/1776906730_Moral Theories.pdf', '../uploads/submissions/1776909223_Computer Ethics.pdf'),
('TK-F94980', 'Gomol michael', 'wak kijo molek laaa keroooo nate kussssss', '2026-04-14', '2026-04-15', 'Done', 'High', NULL, 'hello\r\nalisya busuk\r\ndbvwdvwdkh\r\n-', NULL, 'Personal', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` varchar(25) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL,
  `account_status` varchar(20) DEFAULT 'Active',
  `dept_id` int(10) UNSIGNED DEFAULT NULL,
  `suspension_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `role`, `account_status`, `dept_id`, `suspension_reason`) VALUES
('AD001', 'Aleeya Azman', 'aleeya@optitask.com', '$2y$10$ZEPJvvmzV7TnDBTRzfQ.Z.C71rGuHAnZyV3BWo/ElFaeEroObWhEm', 'Admin', 'Active', 2, NULL),
('EM001', 'Muhammad Afiq', 'afiq@optitask.com', '$2y$10$ymMDBX5TAZmREOkZPJjcrOWTJh9Y78fJUcqUXT8KIoQ5RchrzHnnW', 'Employee', 'Active', 2, NULL),
('EM003', 'Farid Kamil', 'farid@optitask.com', '$2y$10$xI54CFbBQ4ZqJGVR2dkTHOfGpv4nmcDdBp3qaiwhvOBvIK2Aedy4.', 'Employee', 'Active', 3, NULL),
('EM004', 'Nurul Izzah', 'izzah@optitask.com', '$2y$10$/JQKwUx5GkPXEnFvnesp.ex.e7G4TikeEnHxq6dQ57aZoLATNm2OK', 'Employee', 'Active', 3, NULL),
('EM005', 'Amira Maisarah', 'amira@optitask.com', '$2y$10$uYCV7d1jc5v4zfh5JgUnUupxK3DcYLocUuWDJ42sjPGHRWXbS7TC6', 'Employee', 'Active', 1, NULL),
('EM006', 'Hafiz Ramli', 'hafiz@optitask.com', '$2y$10$I38GoelJertyy5y6Bhk/Xejo/itnieNWEhRL7DVvamK8qE4houxf.', 'Employee', 'Active', 1, NULL),
('EM007', 'Syazwan Atan', 'syazwan@optitask.com', '$2y$10$sNUEMHxhoAwmICb70m4Ssel00xBMFkY0KWnlTBlamikZyuE0UUwmK', 'Employee', 'Active', 1, NULL),
('EM008', 'Nabilah Huda', 'nabilah@optitask.com', '$2y$10$/OAoNsJLBAkrpOnvr5XB9OM14dFkVO4xuZhvP9VnFW3wg/9cbBjD.', 'Employee', 'Active', 2, NULL),
('EM009', 'Aiman Hakim', 'aiman@optitask.com', '$2y$10$jaZi7sQen7GVFh4YCTOS3OprsxzjiSEIi3O921AD5eu9bdk45326a', 'Employee', 'Active', 1, NULL),
('EM010', 'Fazura Sharif', 'fazura@optitask.com', '$2y$10$NBwptcj2DZQUnljbwuLeveKHZ1vlTghRo0PVA6qZnDrG6izx2xCVW', 'Employee', 'Active', 3, NULL),
('EM011', 'Adleen Ezzate', 'ezzateadleen@gmail.com', '$2y$10$YPgm156rLLSpLFrC5YH9pO6Bpo1mpbZhSsbPwxAWb3Q/jqZOE3TDu', 'Employee', 'Active', NULL, NULL),
('MG001', 'Khairul Azman', 'khairul@optitask.com', '$2y$10$G00TFQF/Oosgwz6DTV44SOGPwsink2r0qpO7TZFbpr689UHvecpMW', 'Manager', 'Active', NULL, NULL),
('MG002', 'Siti Aminah', 'aminah@optitask.com', '$2y$10$TFRSMDApcx2g10JUDpdole7bhCnVVLJOeaIhP3yoZmLIPdaWMGRqW', 'Manager', 'Active', NULL, NULL),
('MG003', 'Taeyong Lee', 'lee@optitask.com', '$2y$10$2lPtq5bmaTuavZnQYm2TZ.95Gx/W7iNl09N2GSRtxuBCBzXNl6qZa', 'manager', 'Active', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`dept_id`);

--
-- Indexes for table `employee_skills`
--
ALTER TABLE `employee_skills`
  ADD PRIMARY KEY (`user_id`,`skill_id`),
  ADD KEY `fk_link_skill` (`skill_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `performance`
--
ALTER TABLE `performance`
  ADD PRIMARY KEY (`performance_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `skills`
--
ALTER TABLE `skills`
  ADD PRIMARY KEY (`skill_id`),
  ADD UNIQUE KEY `skill_name` (`skill_name`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_user_dept` (`dept_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `dept_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(25) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `skills`
--
ALTER TABLE `skills`
  MODIFY `skill_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD CONSTRAINT `audit_trail_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `employee_skills`
--
ALTER TABLE `employee_skills`
  ADD CONSTRAINT `fk_link_skill` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`skill_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_link_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `performance`
--
ALTER TABLE `performance`
  ADD CONSTRAINT `performance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_dept` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
