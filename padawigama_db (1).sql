-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 21, 2026 at 04:12 PM
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
-- Database: `padawigama_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `cleaning_schedule`
--

CREATE TABLE `cleaning_schedule` (
  `id` int(11) NOT NULL,
  `event_date` date NOT NULL,
  `canal_section` varchar(100) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `members_req` int(11) NOT NULL DEFAULT 1,
  `status` enum('Upcoming','Completed','Cancelled') NOT NULL DEFAULT 'Upcoming',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `govi_committee`
--

CREATE TABLE `govi_committee` (
  `id` int(11) NOT NULL,
  `position` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `nic` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `govi_members`
--

CREATE TABLE `govi_members` (
  `id` int(11) NOT NULL,
  `member_no` varchar(10) NOT NULL,
  `name` varchar(150) NOT NULL,
  `nic` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `original_owner` varchar(150) NOT NULL,
  `cultivator` varchar(150) NOT NULL,
  `hectares` decimal(6,2) NOT NULL,
  `canal` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mara_bearers`
--

CREATE TABLE `mara_bearers` (
  `id` int(11) NOT NULL,
  `position` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `nic` varchar(20) NOT NULL DEFAULT '—',
  `address` text DEFAULT NULL,
  `ethnicity` varchar(50) NOT NULL DEFAULT 'Sinhala',
  `religion` varchar(50) NOT NULL DEFAULT 'Buddhist',
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mara_funeral_duties`
--

CREATE TABLE `mara_funeral_duties` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL DEFAULT 1,
  `day_no` int(11) NOT NULL DEFAULT 1,
  `time_slot` varchar(20) NOT NULL,
  `duty_type` varchar(100) NOT NULL,
  `member_name` varchar(150) NOT NULL,
  `nic` varchar(20) NOT NULL,
  `shift` enum('Morning','Afternoon','Evening') NOT NULL DEFAULT 'Morning',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mara_members`
--

CREATE TABLE `mara_members` (
  `id` int(11) NOT NULL,
  `member_no` varchar(10) NOT NULL,
  `name` varchar(150) NOT NULL,
  `nic` varchar(20) NOT NULL,
  `father_name` varchar(150) DEFAULT NULL,
  `address` text NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `religion` varchar(50) NOT NULL DEFAULT 'Buddhist',
  `ethnicity` varchar(50) NOT NULL DEFAULT 'Sinhala',
  `years_in_village` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `joined_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mara_payments`
--

CREATE TABLE `mara_payments` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `month_key` varchar(10) NOT NULL,
  `month_label` varchar(20) NOT NULL,
  `status` enum('paid','unpaid') NOT NULL DEFAULT 'unpaid',
  `recorded_by` int(11) DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `member_registrations`
--

CREATE TABLE `member_registrations` (
  `id` int(11) NOT NULL,
  `join_mara` tinyint(1) NOT NULL DEFAULT 1,
  `join_govi` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(150) NOT NULL,
  `nic` varchar(20) NOT NULL,
  `father_name` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `ethnicity` varchar(50) NOT NULL DEFAULT 'Sinhala',
  `religion` varchar(50) NOT NULL DEFAULT 'Buddhist',
  `address` text NOT NULL,
  `years_in_village` int(11) NOT NULL DEFAULT 0,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(10) NOT NULL DEFAULT 'Male',
  `original_owner` varchar(150) DEFAULT NULL,
  `cultivator` varchar(150) DEFAULT NULL,
  `hectares` decimal(6,2) DEFAULT NULL,
  `canal` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `member_registrations`
--

INSERT INTO `member_registrations` (`id`, `join_mara`, `join_govi`, `name`, `nic`, `father_name`, `phone`, `ethnicity`, `religion`, `address`, `years_in_village`, `date_of_birth`, `gender`, `original_owner`, `cultivator`, `hectares`, `canal`, `password_hash`, `status`, `submitted_at`) VALUES
(1, 1, 0, 'wwwwwww', '1111', 'wwww', '000', 'Sinhala', 'Buddhist', 'jwdggd', 12, '2026-03-21', 'Male', NULL, NULL, NULL, NULL, '$2y$10$Pyx1q.DTsnOgS9U.NMVMXuPOc4pFNnEeJcVl7QAhjtIZbBs8jPCoS', 'pending', '2026-03-21 15:04:39'),
(2, 1, 0, 'Shahan vimukthi', '12121212', 'Father', '011245789', 'Sinhala', 'Buddhist', 'RJT', 12, '2026-03-21', 'Male', NULL, NULL, NULL, NULL, '$2y$10$W0sUSuGAGIt9jbev6Bv.d.GQXdfjvkaJOoEn7cZHwRs8.JlyziOI.', 'pending', '2026-03-21 15:07:26');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nic` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('member','president-mara','president-govi') NOT NULL DEFAULT 'member',
  `mara_member_id` int(11) DEFAULT NULL,
  `govi_member_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nic`, `password_hash`, `role`, `mara_member_id`, `govi_member_id`, `created_at`) VALUES
(1, '650234521V', '$2y$10$Mn/jDZesIj94e13AVelxteyiu2UvojIFkvbY3OJ/dM.Zruji84w0u', 'president-mara', NULL, NULL, '2026-03-21 14:54:48'),
(2, '620678901V', '$2y$10$DanQutGEoZrPjZhgV9ZOX.KB9c9/DVc1d3aE.MTqasO3RyTsJFk7u', 'president-govi', NULL, NULL, '2026-03-21 14:54:48'),
(5, '12345678V', '$2y$10$OQ667k0MKHOaokCRxhRD/eUJ6q.whFlNEsoVdNenCSMCm5U8v20me', 'member', NULL, NULL, '2026-03-21 15:02:13'),
(6, '11110000V', '$2y$10$7qCZxhINevO0MbrKi69TYuTyyDnO46H5Hn74qk5vgPpB.bxQx27/q', 'member', NULL, NULL, '2026-03-21 15:02:13'),
(7, '67892345V', '$2y$10$6NCc.yRKb8hYXkJyXqkPe.3iO42xC7gRoC/XUGBCLYDkSXT7p65R2', 'member', NULL, NULL, '2026-03-21 15:02:13'),
(8, '80234567V', '$2y$10$6z/SpTBfOejsi04mIx0xNuQehmYp7r7pIqamt/aXp8NWSHNQnjyi.', 'member', NULL, NULL, '2026-03-21 15:02:14'),
(9, '88345678V', '$2y$10$L1gpTn1hsNjclcMSrNvXNu4N18U2uMzj9IVvI733E6k4elswV5WoW', 'member', NULL, NULL, '2026-03-21 15:02:14'),
(10, '1111', '$2y$10$Pyx1q.DTsnOgS9U.NMVMXuPOc4pFNnEeJcVl7QAhjtIZbBs8jPCoS', 'member', NULL, NULL, '2026-03-21 15:04:39'),
(11, '12121212', '$2y$10$W0sUSuGAGIt9jbev6Bv.d.GQXdfjvkaJOoEn7cZHwRs8.JlyziOI.', 'member', NULL, NULL, '2026-03-21 15:07:26');

-- --------------------------------------------------------

--
-- Table structure for table `water_schedule`
--

CREATE TABLE `water_schedule` (
  `id` int(11) NOT NULL,
  `member_name` varchar(150) NOT NULL,
  `nic` varchar(20) NOT NULL,
  `canal` varchar(20) NOT NULL,
  `hectares` decimal(6,2) NOT NULL,
  `schedule_date` date NOT NULL,
  `time_window` varchar(50) NOT NULL,
  `duration` varchar(50) NOT NULL,
  `month_label` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cleaning_schedule`
--
ALTER TABLE `cleaning_schedule`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `govi_committee`
--
ALTER TABLE `govi_committee`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `govi_members`
--
ALTER TABLE `govi_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_no` (`member_no`),
  ADD UNIQUE KEY `nic` (`nic`);

--
-- Indexes for table `mara_bearers`
--
ALTER TABLE `mara_bearers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mara_funeral_duties`
--
ALTER TABLE `mara_funeral_duties`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mara_members`
--
ALTER TABLE `mara_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_no` (`member_no`),
  ADD UNIQUE KEY `nic` (`nic`);

--
-- Indexes for table `mara_payments`
--
ALTER TABLE `mara_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_member_month` (`member_id`,`month_key`);

--
-- Indexes for table `member_registrations`
--
ALTER TABLE `member_registrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nic` (`nic`),
  ADD KEY `fk_users_mara` (`mara_member_id`),
  ADD KEY `fk_users_govi` (`govi_member_id`);

--
-- Indexes for table `water_schedule`
--
ALTER TABLE `water_schedule`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cleaning_schedule`
--
ALTER TABLE `cleaning_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `govi_committee`
--
ALTER TABLE `govi_committee`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `govi_members`
--
ALTER TABLE `govi_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mara_bearers`
--
ALTER TABLE `mara_bearers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mara_funeral_duties`
--
ALTER TABLE `mara_funeral_duties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mara_members`
--
ALTER TABLE `mara_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mara_payments`
--
ALTER TABLE `mara_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `member_registrations`
--
ALTER TABLE `member_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `water_schedule`
--
ALTER TABLE `water_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `mara_payments`
--
ALTER TABLE `mara_payments`
  ADD CONSTRAINT `mara_payments_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `mara_members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_govi` FOREIGN KEY (`govi_member_id`) REFERENCES `govi_members` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_mara` FOREIGN KEY (`mara_member_id`) REFERENCES `mara_members` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
