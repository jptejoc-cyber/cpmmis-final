-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 15, 2026 at 11:44 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cpmmis_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `user_role` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `details` text COLLATE utf8mb4_general_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `user_role`, `username`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'admin', 'admin', 'Logged out', '', '127.0.0.1', '2026-06-10 05:22:45'),
(2, 1, 'group', 'capsoon', 'Registered group account: capsoon', '', '127.0.0.1', '2026-06-10 05:24:53'),
(3, 1, 'admin', 'admin', 'Logged in', '', '127.0.0.1', '2026-06-10 05:25:01'),
(4, 1, 'admin', 'admin', 'Approved group: capsoon', '', '127.0.0.1', '2026-06-10 05:25:45'),
(5, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-10 13:44:50'),
(6, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-10 13:44:59'),
(7, 1, 'admin', 'admin', 'Added adviser', 'oliver pavillar (adviser1)', '127.0.0.1', '2026-06-10 13:45:32'),
(8, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-10 13:45:43'),
(9, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-10 13:46:04'),
(10, 1, 'adviser', 'adviser1', 'Created task', '\'Chapter 2 Submission\'', '127.0.0.1', '2026-06-10 14:08:57'),
(11, 1, 'adviser', 'adviser1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-10 14:09:05'),
(12, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-12 07:23:04'),
(13, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-12 07:23:23'),
(14, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-12 07:23:32'),
(15, 1, 'adviser', 'adviser1', 'Extended deadline', 'task \'Chapter 2 Submission\'', '127.0.0.1', '2026-06-12 09:26:59'),
(16, 1, 'adviser', 'adviser1', 'Extended deadline', 'task \'Chapter 2 Submission\'', '127.0.0.1', '2026-06-12 09:58:32'),
(17, 1, 'adviser', 'adviser1', 'Extended deadline', 'task \'Chapter 2 Submission\'', '127.0.0.1', '2026-06-12 10:03:35'),
(18, 1, 'adviser', 'adviser1', 'Extended deadline', 'task \'Chapter 2 Submission\'', '127.0.0.1', '2026-06-12 10:03:37'),
(19, 1, 'adviser', 'adviser1', 'Extended deadline', 'task \'Chapter 2 Submission\'', '127.0.0.1', '2026-06-12 10:04:07'),
(20, 1, 'adviser', 'adviser1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-12 10:52:38'),
(21, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-12 10:52:51'),
(22, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-12 10:58:05'),
(23, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-12 10:58:14'),
(24, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-14 03:57:52'),
(25, 1, 'adviser', 'adviser1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-14 05:02:26'),
(26, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-14 05:02:38'),
(27, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-14 05:13:37'),
(28, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-14 05:13:45'),
(29, 1, 'adviser', 'adviser1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-14 05:18:29'),
(30, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-14 05:18:36'),
(31, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-14 05:18:56'),
(32, 1, 'group', 'capsoon', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-14 05:19:09'),
(33, 1, 'group', 'capsoon', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-14 05:29:09'),
(34, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-14 05:30:20'),
(35, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-14 05:57:52'),
(36, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-14 05:58:17'),
(37, 1, 'admin', 'admin', 'Added panelist', 'manuel uy (panel1)', '127.0.0.1', '2026-06-15 02:11:49'),
(38, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-15 02:11:53'),
(39, 1, 'panelist', 'panel1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-15 02:15:48'),
(40, 1, 'panelist', 'panel1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-15 02:31:16'),
(41, 1, 'panelist', 'panel1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-15 02:31:24'),
(42, 1, 'panelist', 'panel1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-18 03:24:45'),
(43, 1, 'panelist', 'panel1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-18 04:30:16'),
(44, 1, 'group', 'capsoon', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-06-18 04:30:28'),
(45, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-07-22 04:24:51'),
(46, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-07-22 04:28:25'),
(47, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-07-22 04:29:13'),
(48, 1, 'adviser', 'adviser1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-07-22 04:32:57'),
(49, 1, 'group', 'capsoon', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-07-22 04:33:14'),
(50, 1, 'group', 'capsoon', 'Requested adviser', 'ID 1', '127.0.0.1', '2026-07-22 04:43:42'),
(51, 1, 'group', 'capsoon', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-07-22 05:00:55'),
(52, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-07-22 05:01:00'),
(53, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-07-22 05:01:24'),
(54, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-14 04:42:34'),
(55, 1, 'adviser', 'adviser1', 'Accepted group', 'capsoon as advisee', '127.0.0.1', '2026-08-14 04:43:01'),
(56, 1, 'adviser', 'adviser1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-14 04:43:12'),
(57, 1, 'group', 'capsoon', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-14 04:43:21'),
(58, 1, 'group', 'capsoon', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-14 04:46:17'),
(59, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-14 04:46:28'),
(60, 1, 'group', 'capsoon', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-14 04:46:43'),
(61, 1, 'adviser', 'adviser1', 'Declined consultation', 'group ID 1', '127.0.0.1', '2026-08-14 04:48:51'),
(62, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-19 03:48:15'),
(63, 1, 'group', 'capsoon', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-19 03:49:54'),
(64, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-23 16:12:29'),
(65, 1, 'adviser', 'adviser1', 'Created task', '\'Chapter 1 Submission\'', '127.0.0.1', '2026-08-23 16:30:46'),
(66, 1, 'adviser', 'adviser1', 'Created task', '\'Chapter 1 Submission\'', '127.0.0.1', '2026-08-23 18:07:54'),
(67, 1, 'adviser', 'adviser1', 'Created task', '\'Chapter 2 Submission\'', '127.0.0.1', '2026-08-23 18:14:45'),
(68, 1, 'adviser', 'adviser1', 'Created task', '\'Chapter 3 Submission\'', '127.0.0.1', '2026-08-23 18:14:59'),
(69, 1, 'adviser', 'adviser1', 'Created task', '\'Chapter 1 Submission\'', '127.0.0.1', '2026-08-23 18:15:21'),
(70, 1, 'adviser', 'adviser1', 'Deleted task', '\'Chapter 2 Submission\'', '127.0.0.1', '2026-08-23 18:19:53'),
(71, 1, 'adviser', 'adviser1', 'Deleted task', '\'Chapter 1 Submission\'', '127.0.0.1', '2026-08-23 18:19:57'),
(72, 1, 'adviser', 'adviser1', 'Deleted task', '\'Chapter 1 Submission\'', '127.0.0.1', '2026-08-23 18:20:01'),
(73, 1, 'adviser', 'adviser1', 'Created task', '\'Chapter 4 Submission\'', '127.0.0.1', '2026-08-23 18:20:21'),
(74, 1, 'adviser', 'adviser1', 'Created task', '\'Chapter 5 Submission\'', '127.0.0.1', '2026-08-23 18:20:46'),
(75, 1, 'adviser', 'adviser1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-23 18:47:11'),
(76, 1, 'group', 'capsoon', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-23 18:51:31'),
(77, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-23 18:52:15'),
(78, 1, 'adviser', 'adviser1', 'Deleted task', '\'Chapter 5 Submission\'', '127.0.0.1', '2026-08-23 18:52:36'),
(79, 1, 'adviser', 'adviser1', 'Created task', '\'Chapter 5 Submission\'', '127.0.0.1', '2026-08-23 18:53:58'),
(80, 1, 'group', 'capsoon', 'Submitted task', 'Chapter 1 Submission', '127.0.0.1', '2026-08-23 19:02:57'),
(81, 1, 'group', 'capsoon', 'Submitted task', 'Chapter 2 Submission', '127.0.0.1', '2026-08-23 19:03:12'),
(82, 1, 'group', 'capsoon', 'Submitted task', 'Chapter 3 Submission', '127.0.0.1', '2026-08-23 19:03:21'),
(83, 1, 'group', 'capsoon', 'Submitted task', 'Chapter 4 Submission', '127.0.0.1', '2026-08-23 19:03:29'),
(84, 1, 'group', 'capsoon', 'Submitted task', 'Chapter 5 Submission', '127.0.0.1', '2026-08-23 19:03:38'),
(85, 1, 'adviser', 'adviser1', 'Approved submission', 'Chapter 5 from group capsoon', '127.0.0.1', '2026-08-23 19:04:14'),
(86, 1, 'adviser', 'adviser1', 'Approved submission', 'Chapter 4 from group capsoon', '127.0.0.1', '2026-08-23 19:04:19'),
(87, 1, 'adviser', 'adviser1', 'Approved submission', 'Chapter 3 from group capsoon', '127.0.0.1', '2026-08-23 19:04:24'),
(88, 1, 'adviser', 'adviser1', 'Approved submission', 'Chapter 2 from group capsoon', '127.0.0.1', '2026-08-23 19:05:43'),
(89, 1, 'adviser', 'adviser1', 'Approved submission', 'Chapter 1 from group capsoon', '127.0.0.1', '2026-08-23 19:06:25'),
(90, 1, 'adviser', 'adviser1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-23 19:13:21'),
(91, 1, 'group', 'capsoon', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-24 16:31:09'),
(92, 2, 'group', 'group2', 'Registered group account', 'group2', '127.0.0.1', '2026-08-24 16:31:51'),
(93, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-24 16:32:01'),
(94, 1, 'admin', 'admin', 'Approved group', 'group2', '127.0.0.1', '2026-08-24 16:32:22'),
(95, 1, 'admin', 'admin', 'Broadcast letter', 'request for m', '127.0.0.1', '2026-08-24 16:33:30'),
(96, 1, 'admin', 'admin', 'Broadcast letter', 'adaw', '127.0.0.1', '2026-08-24 16:51:49'),
(97, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-24 16:59:53'),
(98, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-24 20:55:29'),
(99, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-24 20:57:39'),
(100, 2, 'group', 'group2', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-24 20:57:46'),
(101, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-25 18:03:34'),
(102, 2, 'group', 'group2', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-25 18:40:01'),
(103, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-25 18:40:11'),
(104, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-25 19:08:19'),
(105, 2, 'group', 'group2', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-25 19:19:56'),
(106, 1, 'adviser', 'adviser1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-25 19:21:04'),
(107, 2, 'group', 'group2', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-25 19:21:12'),
(108, 2, 'group', 'group2', 'Requested adviser', 'ID 1', '127.0.0.1', '2026-08-25 22:05:06'),
(109, 2, 'group', 'group2', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-25 22:05:56'),
(110, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-25 22:06:06'),
(111, 1, 'adviser', 'adviser1', 'Accepted group', 'group2 as advisee', '127.0.0.1', '2026-08-25 22:06:19'),
(112, 1, 'adviser', 'adviser1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-25 22:06:24'),
(113, 2, 'group', 'group2', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-25 22:06:32'),
(114, 2, 'group', 'group2', 'Submitted task', 'Chapter 5 Submission', '127.0.0.1', '2026-08-25 22:06:50'),
(115, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-25 22:07:13'),
(116, 1, 'adviser', 'adviser1', 'Created task', '\'Chapter 1 Submission\'', '127.0.0.1', '2026-08-25 22:07:37'),
(117, 1, 'adviser', 'adviser1', 'Created task', '\'Chapter 3 Submission\'', '127.0.0.1', '2026-08-25 22:07:50'),
(118, 2, 'group', 'group2', 'Submitted task', 'Chapter 3 Submission', '127.0.0.1', '2026-08-25 22:08:07'),
(119, 1, 'adviser', 'adviser1', 'Created task', '\'Chapter 1 Submission\'', '127.0.0.1', '2026-08-25 22:08:31'),
(120, 1, 'adviser', 'adviser1', 'Created task', '\'Chapter 2 Submission\'', '127.0.0.1', '2026-08-25 22:09:14'),
(121, 1, 'adviser', 'adviser1', 'Created task', '\'Chapter 4 Submission\'', '127.0.0.1', '2026-08-25 22:09:36'),
(122, 2, 'group', 'group2', 'Submitted task', 'Chapter 2 Submission', '127.0.0.1', '2026-08-25 22:09:48'),
(123, 2, 'group', 'group2', 'Submitted task', 'Chapter 4 Submission', '127.0.0.1', '2026-08-25 22:09:55'),
(124, 2, 'group', 'group2', 'Submitted task', 'Chapter 1 Submission', '127.0.0.1', '2026-08-25 22:10:04'),
(125, 1, 'adviser', 'adviser1', 'Approved submission', 'Chapter 1 from group group2', '127.0.0.1', '2026-08-25 22:10:18'),
(126, 1, 'adviser', 'adviser1', 'Approved submission', 'Chapter 4 from group group2', '127.0.0.1', '2026-08-25 22:10:23'),
(127, 1, 'adviser', 'adviser1', 'Approved submission', 'Chapter 5 from group group2', '127.0.0.1', '2026-08-25 22:10:31'),
(128, 1, 'adviser', 'adviser1', 'Approved submission', 'Chapter 3 from group group2', '127.0.0.1', '2026-08-25 22:10:37'),
(129, 1, 'adviser', 'adviser1', 'Approved submission', 'Chapter 2 from group group2', '127.0.0.1', '2026-08-25 22:10:42'),
(130, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-26 17:01:24'),
(131, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-30 17:33:01'),
(132, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-30 17:59:09'),
(133, 2, 'group', 'group2', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-30 18:00:33'),
(134, 2, 'group', 'group2', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-30 18:43:26'),
(135, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-30 18:43:36'),
(136, 1, 'adviser', 'adviser1', 'Accepted consultation', 'group ID 2', '127.0.0.1', '2026-08-30 18:43:51'),
(137, 1, 'adviser', 'adviser1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-30 18:52:27'),
(138, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-30 18:52:51'),
(139, 1, 'adviser', 'adviser1', 'Approved submission', 'Chapter 6 from group capsoon', '127.0.0.1', '2026-08-30 19:14:13'),
(140, 1, 'adviser', 'adviser1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-30 19:20:51'),
(141, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-30 19:20:57'),
(142, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-30 19:25:11'),
(143, 2, 'group', 'group2', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-30 19:25:20'),
(144, 2, 'group', 'group2', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-30 19:26:33'),
(145, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-30 19:26:49'),
(146, 1, 'adviser', 'adviser1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-30 19:49:47'),
(147, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-08-30 19:49:53'),
(148, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-07 14:58:11'),
(149, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-08 05:01:18'),
(150, 1, 'admin', 'admin', 'Cleared system cache', '0 files', '127.0.0.1', '2026-09-08 05:04:38'),
(151, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-08 05:24:15'),
(152, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-10 18:01:18'),
(153, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-10 18:01:32'),
(154, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-10 18:01:42'),
(155, 1, 'adviser', 'adviser1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-10 18:08:33'),
(156, 2, 'group', 'group2', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-10 18:09:00'),
(157, 2, 'group', 'group2', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-10 18:09:14'),
(158, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-10 18:11:02'),
(159, 1, 'adviser', 'adviser1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-10 19:00:21'),
(160, 2, 'group', 'group2', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-10 19:00:45'),
(161, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-11 06:23:00'),
(162, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-11 12:09:19'),
(163, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-11 12:24:22'),
(164, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-11 16:25:09'),
(165, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-11 16:27:26'),
(166, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-11 16:29:00'),
(167, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-11 16:36:05'),
(168, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-11 16:45:55'),
(169, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-11 16:49:41'),
(170, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-11 16:53:13'),
(172, 4, 'group', 'group3', 'Registered group account', 'group3', '127.0.0.1', '2026-09-11 17:13:56'),
(173, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-11 19:32:22'),
(174, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-11 20:53:18'),
(175, 5, 'group', 'eqeqeq', 'Registered group account', 'eqeqeq', '127.0.0.1', '2026-09-11 20:53:47'),
(176, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-11 20:54:01'),
(177, 1, 'admin', 'admin', 'Deleted group', 'group3', '127.0.0.1', '2026-09-11 20:54:37'),
(178, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-11 21:00:02'),
(179, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-12 06:08:04'),
(180, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-12 06:21:35'),
(181, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-12 06:22:46'),
(182, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-12 06:49:01'),
(183, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-12 17:01:23'),
(184, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-18 11:29:19'),
(185, 1, 'admin', 'admin', 'Cleared system cache', '0 files', '127.0.0.1', '2026-09-18 11:36:37'),
(186, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-18 11:37:34'),
(187, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-18 11:38:06'),
(188, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-18 11:38:19'),
(189, 1, 'group', 'capsoon', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-09-22 15:39:04'),
(190, 6, 'group', 'group3', 'Registered group account', 'group3', '127.0.0.1', '2026-10-05 17:07:30'),
(191, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-10-05 17:08:23'),
(192, 1, 'admin', 'admin', 'Approved group', 'group3', '127.0.0.1', '2026-10-05 17:08:42'),
(193, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-10-05 17:08:47'),
(194, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-10-05 17:09:19'),
(195, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-10-05 17:09:36'),
(196, 6, 'group', 'group3', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-10-05 17:09:46'),
(197, 6, 'group', 'group3', 'Requested adviser', 'ID 1', '127.0.0.1', '2026-10-05 17:11:22'),
(198, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-10-05 17:11:52'),
(199, 1, 'adviser', 'adviser1', 'Accepted group', 'group3 as advisee', '127.0.0.1', '2026-10-05 17:12:14'),
(200, 1, 'adviser', 'adviser1', 'Created task', '\'Chapter 1 Submission\'', '127.0.0.1', '2026-10-05 17:34:25'),
(201, 1, 'adviser', 'adviser1', 'Declined consultation', 'group ID 6', '127.0.0.1', '2026-10-05 17:37:47'),
(202, 1, 'adviser', 'adviser1', 'Accepted consultation', 'group ID 6', '127.0.0.1', '2026-10-05 17:38:54'),
(203, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-10-22 12:51:27'),
(204, 1, 'admin', 'admin', 'Cleared system cache', '0 files', '127.0.0.1', '2026-10-22 19:32:11'),
(205, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-02 09:42:12'),
(206, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-06 08:07:11'),
(207, 1, 'admin', 'admin', 'Uploaded archive', '\'eqweqqeq\' (2026-2027, regular)', '127.0.0.1', '2026-11-06 10:28:13'),
(208, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-10 08:32:32'),
(209, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-10 08:34:18'),
(210, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-10 08:34:25'),
(211, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-10 08:36:06'),
(212, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-10 08:37:46'),
(213, 1, 'group', 'capsoon', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-10 08:37:55'),
(214, 1, 'adviser', 'adviser1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-10 08:38:52'),
(215, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-10 08:38:57'),
(216, 1, 'group', 'capsoon', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-10 08:57:06'),
(217, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-10 08:57:15'),
(218, 1, 'adviser', 'adviser1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-10 08:57:46'),
(219, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-11 02:35:44'),
(220, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-14 03:28:14'),
(221, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-14 03:28:24'),
(222, 7, 'group', 'password', 'Registered group account', 'password', '127.0.0.1', '2026-11-14 03:29:15'),
(223, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-14 03:29:22'),
(224, 1, 'admin', 'admin', 'Approved group', 'password', '127.0.0.1', '2026-11-14 03:29:44'),
(225, 1, 'admin', 'admin', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-14 03:29:47'),
(226, 7, 'group', 'password', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-14 03:29:57'),
(227, 7, 'group', 'password', 'Requested adviser', 'ID 1', '127.0.0.1', '2026-11-14 03:30:21'),
(228, 7, 'group', 'password', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-14 03:30:24'),
(229, 1, 'adviser', 'adviser1', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-14 03:30:54'),
(230, 1, 'adviser', 'adviser1', 'Accepted group', 'password as advisee', '127.0.0.1', '2026-11-14 03:31:04'),
(231, 1, 'adviser', 'adviser1', 'Created task', '\'Chapter 1 Submission\'', '127.0.0.1', '2026-11-14 03:31:36'),
(232, 1, 'adviser', 'adviser1', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-14 03:31:39'),
(233, 7, 'group', 'password', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-14 03:31:51'),
(234, 7, 'group', 'password', 'Logged out', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-14 03:32:08'),
(235, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-14 03:32:19'),
(236, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-14 04:46:06'),
(237, 1, 'admin', 'admin', 'Logged in', 'IP: 127.0.0.1', '127.0.0.1', '2026-11-15 11:37:41');

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity` timestamp NULL DEFAULT NULL,
  `session_version` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `full_name`, `email`, `created_at`, `last_activity`, `session_version`) VALUES
(1, 'admin', '$2y$10$FwXiqJbw6IvxQwIzjG5Ip.B93FdiPevZMngIwimQQQV5cXWVLpwxO', 'System Administrator', 'admin@cpmmis.test', '2026-06-09 06:00:09', '2026-11-15 11:44:05', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `admin_letters`
--

CREATE TABLE `admin_letters` (
  `id` int NOT NULL,
  `group_id` int NOT NULL,
  `admin_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `file_size` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_letters`
--

INSERT INTO `admin_letters` (`id`, `group_id`, `admin_id`, `title`, `description`, `file_path`, `file_name`, `file_size`, `created_at`) VALUES
(1, 1, 1, 'request for m', '', 'letter_all_1787589210_6a8c725aa2930.pdf', 'Laundry System.pdf', 1259356, '2026-08-24 16:33:30'),
(2, 2, 1, 'request for m', '', 'letter_all_1787589210_6a8c725aa2930.pdf', 'Laundry System.pdf', 1259356, '2026-08-24 16:33:30'),
(3, 1, 1, 'adaw', '', 'letter_all_1787590309_6a8c76a551a63.pdf', 'ma_apply_si_ungas (1).pdf', 577683, '2026-08-24 16:51:49'),
(4, 2, 1, 'adaw', '', 'letter_all_1787590309_6a8c76a551a63.pdf', 'ma_apply_si_ungas (1).pdf', 577683, '2026-08-24 16:51:49');

-- --------------------------------------------------------

--
-- Table structure for table `adviser`
--

CREATE TABLE `adviser` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` enum('adviser','admin') COLLATE utf8mb4_general_ci DEFAULT 'adviser',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity` timestamp NULL DEFAULT NULL,
  `session_version` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `adviser`
--

INSERT INTO `adviser` (`id`, `username`, `password`, `full_name`, `email`, `role`, `created_at`, `last_activity`, `session_version`) VALUES
(1, 'adviser1', '$2y$10$fTa4RpJ/1COTyZnLWmRIOeV7FqKxSFI0RAo4hKgldnVYTESV4L9w6', 'oliver pavillar', 'oliver@gmail.com', 'adviser', '2026-06-10 13:45:32', '2026-11-14 03:31:36', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `adviser_requests`
--

CREATE TABLE `adviser_requests` (
  `id` int NOT NULL,
  `group_id` int DEFAULT NULL,
  `adviser_id` int DEFAULT NULL,
  `project_title` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_general_ci,
  `status` enum('pending','accepted','declined') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `decline_reason` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `adviser_requests`
--

INSERT INTO `adviser_requests` (`id`, `group_id`, `adviser_id`, `project_title`, `message`, `status`, `decline_reason`, `created_at`) VALUES
(1, 1, 1, 'cpmmis', 'uploads/letters/letter_6a604a7edf507.pdf', 'accepted', NULL, '2026-07-22 04:43:42'),
(2, 2, 1, 'capstone', 'uploads/letters/letter_6a8e11922579e.pdf', 'accepted', NULL, '2026-08-25 22:05:06'),
(3, 6, 1, 'cpmms', 'uploads/letters/letter_6ac3da3a82b65.pdf', 'accepted', NULL, '2026-10-05 17:11:22'),
(4, 7, 1, 'dwqeq', 'uploads/letters/letter_6af7d5cd1432f.pdf', 'accepted', NULL, '2026-11-14 03:30:21');

-- --------------------------------------------------------

--
-- Table structure for table `adviser_tasks`
--

CREATE TABLE `adviser_tasks` (
  `id` int NOT NULL,
  `adviser_id` int NOT NULL,
  `task_type` enum('chapter','letter','other') COLLATE utf8mb4_general_ci NOT NULL,
  `chapter_number` int DEFAULT NULL,
  `caption` text COLLATE utf8mb4_general_ci NOT NULL,
  `deadline` datetime NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deadline_notified` tinyint(1) NOT NULL DEFAULT '0',
  `overdue_notified` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `adviser_tasks`
--

INSERT INTO `adviser_tasks` (`id`, `adviser_id`, `task_type`, `chapter_number`, `caption`, `deadline`, `file_path`, `original_filename`, `created_at`, `deadline_notified`, `overdue_notified`) VALUES
(3, 1, 'chapter', 1, 'Chapter 1 Submission', '2026-08-24 14:10:00', 'task_6a8b36fa95f50.pdf', 'PROSPECTUS-DRIVEN-CADEMIC-SELF-ASSESSMENT-AND-CURRICULUM-ROADMAP.pdf', '2026-08-23 18:07:54', 1, 1),
(4, 1, 'chapter', 2, 'Chapter 2 Submission', '2026-08-24 14:18:00', 'task_6a8b3895d1099.pdf', 'Laundry System.pdf', '2026-08-23 18:14:45', 1, 1),
(5, 1, 'chapter', 3, 'Chapter 3 Submission', '2026-08-24 14:20:00', NULL, NULL, '2026-08-23 18:14:59', 1, 1),
(7, 1, 'chapter', 4, 'Chapter 4 Submission', '2026-08-24 02:26:00', 'task_6a8b39e56c7fc.pdf', 'Sample_Size_and_Research_Instruments_Guide.pdf', '2026-08-23 18:20:21', 0, 1),
(9, 1, 'chapter', 5, 'Chapter 5 Submission', '2026-08-28 02:59:00', 'task_6a8b41c6e1e23.pdf', 'CHAPTER-II_CAPSTONE-PROJECT-MONITORING-MANAGEMENT-INFORMATION-SYSTEM (1).pdf', '2026-08-23 18:53:58', 1, 1),
(10, 1, 'chapter', 1, 'Chapter 1 Submission', '2026-07-28 06:07:00', NULL, NULL, '2026-08-25 22:07:37', 0, 1),
(11, 1, 'chapter', 3, 'Chapter 3 Submission', '2026-08-26 06:10:00', NULL, NULL, '2026-08-25 22:07:50', 0, 1),
(12, 1, 'chapter', 1, 'Chapter 1 Submission', '2026-08-26 12:14:00', 'task_6a8e125fbea43.pdf', 'ma_apply_si_ungas.pdf', '2026-08-25 22:08:31', 0, 1),
(13, 1, 'chapter', 2, 'Chapter 2 Submission', '2026-08-26 23:08:00', 'task_6a8e128aaa7dc.pdf', 'Sample_Size_and_Research_Instruments_Guide.pdf', '2026-08-25 22:09:14', 0, 1),
(14, 1, 'chapter', 4, 'Chapter 4 Submission', '2026-08-26 18:13:00', NULL, NULL, '2026-08-25 22:09:36', 0, 1),
(15, 1, 'chapter', 1, 'Chapter 1 Submission', '2026-10-08 01:34:00', 'task_6ac3dfa11a1da.pdf', 'sub_6a8e12bc7475c_2.pdf', '2026-10-05 17:34:25', 0, 1),
(16, 1, 'chapter', 1, 'Chapter 1 Submission', '2026-11-16 13:31:00', 'task_6af7d61814f7b.pdf', 'Laundry System.pdf', '2026-11-14 03:31:36', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `capstone_archive`
--

CREATE TABLE `capstone_archive` (
  `id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `adviser` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `year` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Standard',
  `file_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `capstone_archive`
--

INSERT INTO `capstone_archive` (`id`, `title`, `adviser`, `year`, `category`, `file_path`, `uploaded_at`) VALUES
(1, 'qwer', 'eqweqwe', '2026-2027', 'regular', 'qwer_1781066823.pdf', '2026-06-10 04:47:03'),
(2, 'qwheqweq', 'qweqe', '2026-2027', 'best', 'qwheqweq_1781070039.pdf', '2026-06-10 05:40:39'),
(5, 'cpmmis', 'oliver', '2026-2027', 'regular', 'cpmmis_1788793129.zip', '2026-09-07 14:58:49'),
(6, 'wow', 'oliver', '2026-2027', 'best', 'wow_1788793192.zip', '2026-09-07 14:59:52'),
(7, 'sasq', 'dwqeq', '2026-2027', 'regular', '03e7c7aa0203eceb7794eb595d7ecfd2.zip', '2026-10-22 19:05:54'),
(8, 'ogag', 'wqeqwe', '2026-2027', 'regular', '31f8710f22d1d9452dfee7c14d919dd2.zip', '2026-10-22 19:07:01'),
(9, 'eqweqqeq', 'weqwe', '2026-2027', 'regular', '30e2906f410280c6effdf3f07f22fe99.zip', '2026-11-06 10:28:13');

-- --------------------------------------------------------

--
-- Table structure for table `group`
--

CREATE TABLE `group` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `leader_email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `project_title` varchar(255) COLLATE utf8mb4_general_ci DEFAULT 'Untitled Project',
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_activity` timestamp NULL DEFAULT NULL,
  `adviser_id` int DEFAULT NULL,
  `role` varchar(10) COLLATE utf8mb4_general_ci DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(20) COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `banned_until` datetime DEFAULT NULL,
  `session_version` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group`
--

INSERT INTO `group` (`id`, `username`, `full_name`, `leader_email`, `password`, `project_title`, `email`, `last_activity`, `adviser_id`, `role`, `created_at`, `status`, `banned_until`, `session_version`) VALUES
(1, 'capsoon', 'jp', 'jptejoc@gmail.com', '$2y$10$cm0v3kdZzKQk34/vFNTS.O867l5TpdItJy/u67xnJyVfLSA8/GsMa', 'cpmmis', NULL, '2026-11-10 08:56:50', 1, 'student', '2026-06-10 05:24:53', 'active', NULL, NULL),
(2, 'group2', 'jp tejoc', 'jptejoc1@gmail.com', '$2y$10$b2k8jEw6pB7S6hq1ALK8Y.DEj2XZV52OIHfK/Ji4uTZa3ihCL/iKS', 'capstone', NULL, '2026-10-22 17:17:39', 1, 'student', '2026-08-24 16:31:51', 'active', NULL, NULL),
(5, 'eqeqeq', 'qeqe', 'qeqe@gmail.com', '$2y$10$RvShdclYlXIRI7txr.zClOGDT8xStTuhe33lfbppRW2AeOzT/lwRS', 'eqqe', NULL, NULL, NULL, 'student', '2026-09-11 20:53:47', 'pending', NULL, NULL),
(6, 'group3', 'dqeq', 'adawd@gmail.com', '$2y$10$EaK2iBvXIWZgm6X4YPuMtuB9QlhCcpVFaWMnTNniIfSmR5tu7IuvC', 'cpmms', NULL, '2026-10-05 17:38:47', 1, 'student', '2026-10-05 17:07:30', 'active', NULL, NULL),
(7, 'password', 'jpppp', 'jpppp@gmail.com', '$2y$10$OgpbTz0uhLAGT6wTeWi6teFt3VyihVQYgyHYpKKF6yEFKegI1u.x6', 'dwqeq', NULL, '2026-11-14 03:32:05', 1, 'student', '2026-11-14 03:29:15', 'active', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `group_submissions`
--

CREATE TABLE `group_submissions` (
  `id` int NOT NULL,
  `group_id` int NOT NULL,
  `task_id` int DEFAULT NULL,
  `chapter_number` int NOT NULL,
  `document_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `submission_type` enum('adviser','panel') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'adviser',
  `review_notes` text COLLATE utf8mb4_general_ci,
  `feedback` text COLLATE utf8mb4_general_ci,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` datetime DEFAULT NULL,
  `version` int NOT NULL DEFAULT '1',
  `is_final` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_submissions`
--

INSERT INTO `group_submissions` (`id`, `group_id`, `task_id`, `chapter_number`, `document_name`, `file_path`, `status`, `submission_type`, `review_notes`, `feedback`, `submitted_at`, `reviewed_at`, `version`, `is_final`) VALUES
(1, 1, 3, 1, 'Laundry System.pdf', 'sub_6a8b43e118ef3_1.pdf', 'approved', 'adviser', NULL, NULL, '2026-08-23 19:02:57', NULL, 1, 0),
(2, 1, 4, 2, 'Laundry System.pdf', 'sub_6a8b43eff420f_1.pdf', 'approved', 'adviser', NULL, NULL, '2026-08-23 19:03:12', NULL, 1, 0),
(3, 1, 5, 3, 'Laundry System.pdf', 'sub_6a8b43f901bce_1.pdf', 'approved', 'adviser', NULL, NULL, '2026-08-23 19:03:21', NULL, 1, 0),
(4, 1, 7, 4, 'Laundry System.pdf', 'sub_6a8b4401c6ca6_1.pdf', 'approved', 'adviser', NULL, NULL, '2026-08-23 19:03:29', NULL, 1, 0),
(5, 1, 9, 5, 'Laundry System.pdf', 'sub_6a8b440ae383d_1.pdf', 'approved', 'adviser', NULL, NULL, '2026-08-23 19:03:38', NULL, 1, 0),
(6, 1, NULL, 6, 'final_project', 'auto_combined', 'approved', 'adviser', NULL, NULL, '2026-08-23 19:06:27', NULL, 1, 0),
(7, 2, 9, 5, 'ma_apply_si_ungas (1).pdf', 'sub_6a8e11fa5a74c_2.pdf', 'approved', 'adviser', NULL, NULL, '2026-08-25 22:06:50', NULL, 1, 0),
(8, 2, 11, 3, 'Smart sales and reservation system with pickup and delivery for chai rose BBQ.pdf', 'sub_6a8e12476e5b0_2.pdf', 'approved', 'adviser', NULL, NULL, '2026-08-25 22:08:07', NULL, 1, 0),
(9, 2, 13, 2, 'ma_apply_si_ungas (1).pdf', 'sub_6a8e12ac83ff9_2.pdf', 'approved', 'adviser', NULL, NULL, '2026-08-25 22:09:48', NULL, 1, 0),
(10, 2, 14, 4, 'Smart sales and reservation system with pickup and delivery for chai rose BBQ.pdf', 'sub_6a8e12b3d2e8a_2.pdf', 'approved', 'adviser', NULL, NULL, '2026-08-25 22:09:55', NULL, 1, 0),
(11, 2, 12, 1, 'Interview-Guide-Head-of-CSS-Department.pdf', 'sub_6a8e12bc7475c_2.pdf', 'approved', 'adviser', NULL, NULL, '2026-08-25 22:10:04', NULL, 1, 0),
(12, 2, NULL, 6, 'final_project', 'auto_combined', 'pending', 'adviser', NULL, NULL, '2026-08-26 15:24:34', NULL, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `device_info` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` (`id`, `user_id`, `role`, `ip_address`, `device_info`, `login_time`) VALUES
(1, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-06-09 06:09:17'),
(2, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-06-10 05:25:01'),
(3, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-06-10 13:44:59'),
(4, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-06-10 13:46:04'),
(5, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-06-12 07:23:04'),
(6, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-06-12 07:23:32'),
(7, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-06-12 10:52:51'),
(8, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-06-12 10:58:14'),
(9, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-06-14 03:57:52'),
(10, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-06-14 05:02:38'),
(11, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-06-14 05:13:45'),
(12, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-06-14 05:18:36'),
(13, 1, 'group', '127.0.0.1', 'Windows PC', '2026-06-14 05:19:09'),
(14, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-06-14 05:30:20'),
(15, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-06-14 05:58:17'),
(16, 1, 'panelist', '127.0.0.1', 'Windows PC', '2026-06-15 02:15:48'),
(17, 1, 'panelist', '127.0.0.1', 'Windows PC', '2026-06-15 02:31:24'),
(18, 1, 'panelist', '127.0.0.1', 'Windows PC', '2026-06-18 03:24:45'),
(19, 1, 'group', '127.0.0.1', 'Windows PC', '2026-06-18 04:30:28'),
(20, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-07-22 04:24:51'),
(21, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-07-22 04:29:13'),
(22, 1, 'group', '127.0.0.1', 'Windows PC', '2026-07-22 04:33:14'),
(23, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-07-22 05:01:00'),
(24, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-08-14 04:42:34'),
(25, 1, 'group', '127.0.0.1', 'Windows PC', '2026-08-14 04:43:21'),
(26, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-08-14 04:46:28'),
(27, 1, 'group', '127.0.0.1', 'Windows PC', '2026-08-14 04:46:43'),
(28, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-08-19 03:48:15'),
(29, 1, 'group', '127.0.0.1', 'Windows PC', '2026-08-19 03:49:54'),
(30, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-08-23 16:12:29'),
(31, 1, 'group', '127.0.0.1', 'Windows PC', '2026-08-23 18:51:31'),
(32, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-08-23 18:52:15'),
(33, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-08-24 16:32:01'),
(34, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-08-24 20:55:29'),
(35, 2, 'group', '127.0.0.1', 'Windows PC', '2026-08-24 20:57:46'),
(36, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-08-25 18:03:34'),
(37, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-08-25 18:40:11'),
(38, 2, 'group', '127.0.0.1', 'Windows PC', '2026-08-25 19:19:56'),
(39, 2, 'group', '127.0.0.1', 'Windows PC', '2026-08-25 19:21:12'),
(40, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-08-25 22:06:06'),
(41, 2, 'group', '127.0.0.1', 'Windows PC', '2026-08-25 22:06:32'),
(42, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-08-25 22:07:13'),
(43, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-08-26 17:01:24'),
(44, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-08-30 17:33:01'),
(45, 2, 'group', '127.0.0.1', 'Windows PC', '2026-08-30 18:00:33'),
(46, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-08-30 18:43:36'),
(47, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-08-30 18:52:51'),
(48, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-08-30 19:20:57'),
(49, 2, 'group', '127.0.0.1', 'Windows PC', '2026-08-30 19:25:20'),
(50, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-08-30 19:26:49'),
(51, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-08-30 19:49:53'),
(52, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-09-07 14:58:11'),
(53, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-09-08 05:01:18'),
(54, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-09-08 05:24:15'),
(55, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-09-10 18:01:18'),
(56, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-09-10 18:01:42'),
(57, 2, 'group', '127.0.0.1', 'Windows PC', '2026-09-10 18:09:00'),
(58, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-09-10 18:11:02'),
(59, 2, 'group', '127.0.0.1', 'Windows PC', '2026-09-10 19:00:45'),
(60, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-09-11 06:23:00'),
(61, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-09-11 12:09:19'),
(62, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-09-11 16:25:09'),
(63, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-09-11 16:29:00'),
(64, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-09-11 16:36:05'),
(65, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-09-11 16:49:41'),
(66, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-09-11 19:32:22'),
(67, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-09-11 20:54:01'),
(68, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-09-12 06:08:04'),
(69, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-09-12 06:21:35'),
(70, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-09-12 06:49:01'),
(71, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-09-12 17:01:23'),
(72, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-09-18 11:29:19'),
(73, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-09-18 11:38:06'),
(74, 1, 'group', '127.0.0.1', 'Windows PC', '2026-09-22 15:39:04'),
(75, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-10-05 17:08:23'),
(76, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-10-05 17:09:19'),
(77, 6, 'group', '127.0.0.1', 'Windows PC', '2026-10-05 17:09:46'),
(78, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-10-05 17:11:52'),
(79, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-10-22 12:51:27'),
(80, 1, 'admin', '127.0.0.1', 'Mobile Device', '2026-11-02 09:42:12'),
(81, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-11-06 08:07:11'),
(82, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-11-10 08:32:32'),
(83, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-11-10 08:34:25'),
(84, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-11-10 08:36:06'),
(85, 1, 'group', '127.0.0.1', 'Windows PC', '2026-11-10 08:37:55'),
(86, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-11-10 08:38:57'),
(87, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-11-10 08:57:15'),
(88, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-11-11 02:35:44'),
(89, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-11-14 03:28:14'),
(90, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-11-14 03:29:22'),
(91, 7, 'group', '127.0.0.1', 'Windows PC', '2026-11-14 03:29:57'),
(92, 1, 'adviser', '127.0.0.1', 'Windows PC', '2026-11-14 03:30:54'),
(93, 7, 'group', '127.0.0.1', 'Windows PC', '2026-11-14 03:31:51'),
(94, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-11-14 03:32:19'),
(95, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-11-14 04:46:06'),
(96, 1, 'admin', '127.0.0.1', 'Windows PC', '2026-11-15 11:37:41');

-- --------------------------------------------------------

--
-- Table structure for table `login_rate_limits`
--

CREATE TABLE `login_rate_limits` (
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `attempts` int NOT NULL DEFAULT '1',
  `last_attempt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meeting_requests`
--

CREATE TABLE `meeting_requests` (
  `id` int NOT NULL,
  `group_id` int NOT NULL,
  `adviser_id` int NOT NULL,
  `meeting_time` datetime NOT NULL,
  `agenda` text COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('pending','accepted','declined') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `admin_remarks` text COLLATE utf8mb4_general_ci,
  `suggested_reschedule` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reminder_sent` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meeting_requests`
--

INSERT INTO `meeting_requests` (`id`, `group_id`, `adviser_id`, `meeting_time`, `agenda`, `status`, `admin_remarks`, `suggested_reschedule`, `created_at`, `reminder_sent`) VALUES
(1, 1, 1, '2026-08-14 13:00:00', 'chapter 1\r\n', 'declined', 'sorry i have meeting\r\n', NULL, '2026-08-14 04:48:31', 0),
(2, 2, 1, '2026-08-31 03:00:00', 'qweqw', 'accepted', '', NULL, '2026-08-30 18:43:23', 1),
(3, 6, 1, '2026-10-06 02:00:00', 'qeqeq', 'declined', 'eqeqeq', NULL, '2026-10-05 17:37:31', 0),
(4, 6, 1, '2026-10-06 04:30:00', 'qweqeq', 'accepted', '', NULL, '2026-10-05 17:38:46', 0);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int NOT NULL,
  `sender_id` int NOT NULL,
  `sender_role` enum('admin','adviser','group') NOT NULL,
  `receiver_id` int NOT NULL,
  `receiver_role` enum('admin','adviser','group') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `sender_role`, `receiver_id`, `receiver_role`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 'adviser', 1, 'admin', 'qeqe', 1, '2026-06-12 10:04:58'),
(2, 1, 'adviser', 1, 'admin', 'qweqweq', 1, '2026-06-12 10:05:01'),
(3, 1, 'adviser', 1, 'admin', 'qweqw', 1, '2026-06-12 10:05:02'),
(4, 1, 'adviser', 1, 'admin', 'qewqweqkweqkweq', 1, '2026-06-12 10:05:04'),
(5, 1, 'adviser', 1, 'admin', 'wiqheqhwe', 1, '2026-06-12 10:06:40'),
(6, 1, 'adviser', 1, 'admin', 'dmqwkend', 1, '2026-06-12 10:06:42'),
(7, 1, 'adviser', 1, 'admin', 'ksjdfisfs', 1, '2026-06-12 10:06:48'),
(8, 1, 'adviser', 1, 'admin', 'djawjdwak', 1, '2026-06-12 10:06:49'),
(9, 1, 'adviser', 1, 'admin', 'dw', 1, '2026-06-12 10:06:52'),
(10, 1, 'adviser', 1, 'admin', 'adwa', 1, '2026-06-12 10:22:13'),
(11, 1, 'adviser', 1, 'admin', 'dwadwa', 1, '2026-06-12 10:22:14'),
(12, 1, 'adviser', 1, 'admin', 'qweqw', 1, '2026-06-12 10:22:19'),
(13, 1, 'adviser', 1, 'admin', 'qweqw', 1, '2026-06-12 10:22:20'),
(14, 1, 'adviser', 1, 'admin', 'qweqwheq', 1, '2026-06-12 10:22:27'),
(15, 1, 'adviser', 1, 'admin', 'dwadwa', 1, '2026-06-12 10:24:00'),
(16, 1, 'adviser', 1, 'admin', 'lejwqjeq', 1, '2026-06-12 10:24:05'),
(17, 1, 'adviser', 1, 'admin', 'wkneqweqw', 1, '2026-06-12 10:24:06'),
(18, 1, 'adviser', 1, 'admin', 'adjajdw', 1, '2026-06-12 10:32:38'),
(19, 1, 'adviser', 1, 'admin', 'awdawdwa', 1, '2026-06-12 10:32:39'),
(20, 1, 'adviser', 1, 'admin', 'cawjcaw[', 1, '2026-06-12 10:33:20'),
(21, 1, 'adviser', 1, 'admin', 'iwhduwahudhwa', 1, '2026-06-12 10:33:26'),
(22, 1, 'adviser', 1, 'admin', 'lqjejqweq', 1, '2026-06-12 10:34:33'),
(23, 1, 'adviser', 1, 'admin', 'jabdabdw', 1, '2026-06-12 10:34:34'),
(24, 1, 'adviser', 1, 'admin', 'wdas', 1, '2026-06-12 10:49:09'),
(25, 1, 'adviser', 1, 'admin', 'akjdakjda', 1, '2026-06-12 10:49:15'),
(26, 1, 'adviser', 1, 'admin', 'nwdkw', 1, '2026-06-12 10:49:17'),
(27, 1, 'adviser', 1, 'admin', 'dwadwa', 1, '2026-06-12 10:49:18'),
(28, 1, 'adviser', 1, 'admin', 'wa', 1, '2026-06-12 10:49:18'),
(29, 1, 'adviser', 1, 'admin', 'dwa', 1, '2026-06-12 10:49:18'),
(30, 1, 'adviser', 1, 'admin', 'd', 1, '2026-06-12 10:49:19'),
(31, 1, 'adviser', 1, 'admin', 'aw', 1, '2026-06-12 10:49:19'),
(32, 1, 'adviser', 1, 'admin', 'dwa', 1, '2026-06-12 10:49:19'),
(33, 1, 'adviser', 1, 'admin', 'd', 1, '2026-06-12 10:49:19'),
(34, 1, 'adviser', 1, 'admin', 'qekqkweqw', 1, '2026-06-12 10:49:26'),
(35, 1, 'adviser', 1, 'admin', 'wqeqweqweq', 1, '2026-06-12 10:49:28'),
(36, 1, 'adviser', 1, 'admin', 'dpkqdkqwodkq', 1, '2026-06-12 10:49:42'),
(37, 1, 'adviser', 1, 'admin', 'wqeqweq', 1, '2026-06-12 10:52:05'),
(38, 1, 'adviser', 1, 'admin', 'qweqw', 1, '2026-06-12 10:52:06'),
(39, 1, 'adviser', 1, 'admin', 'eqwe', 1, '2026-06-12 10:52:07'),
(40, 1, 'adviser', 1, 'admin', 'qweqweqw,,asasf', 1, '2026-06-12 10:52:08'),
(41, 1, 'adviser', 1, 'admin', 'wadlawdjaw', 1, '2026-06-12 10:52:31'),
(42, 1, 'admin', 1, 'adviser', 'eweqweq', 1, '2026-06-14 05:10:44'),
(43, 2, 'group', 1, 'adviser', 'eqweq', 1, '2026-08-26 17:01:01'),
(44, 1, 'adviser', 2, 'group', 'qweqw', 1, '2026-08-26 17:01:32'),
(45, 2, 'group', 1, 'adviser', 'daw', 1, '2026-08-26 17:12:30'),
(46, 2, 'group', 1, 'adviser', 'weq', 1, '2026-08-26 17:12:31'),
(47, 2, 'group', 1, 'adviser', 'eqeq', 1, '2026-08-26 17:12:32'),
(48, 2, 'group', 1, 'adviser', 'eqe', 1, '2026-08-26 17:12:33'),
(49, 2, 'group', 1, 'adviser', 'qeqeq', 1, '2026-08-26 17:12:35'),
(50, 2, 'group', 1, 'adviser', 'eeqe', 1, '2026-08-26 17:12:38'),
(51, 2, 'group', 1, 'adviser', 'eqew', 1, '2026-08-30 18:00:40'),
(52, 2, 'group', 1, 'adviser', 'eqweq', 1, '2026-08-30 18:00:42'),
(53, 2, 'group', 1, 'adviser', 'eqwewq4', 1, '2026-08-30 18:43:13'),
(54, 1, 'adviser', 2, 'group', 'ulo mo', 1, '2026-08-30 19:13:44'),
(55, 1, 'admin', 1, 'adviser', 'eqweq', 1, '2026-09-08 05:04:03'),
(56, 1, 'admin', 1, 'adviser', 'eqweq', 1, '2026-09-11 16:27:16'),
(57, 1, 'admin', 1, 'adviser', 'qeq', 1, '2026-09-11 16:27:17'),
(58, 1, 'admin', 1, 'adviser', 'eq', 1, '2026-09-11 16:27:17'),
(59, 1, 'admin', 1, 'adviser', 'we', 1, '2026-09-18 11:29:32'),
(60, 1, 'admin', 1, 'adviser', 'eqewq44', 1, '2026-10-22 16:09:40'),
(61, 1, 'admin', 1, 'adviser', 'ss', 1, '2026-10-22 17:25:00'),
(62, 1, 'admin', 1, 'adviser', 'dada', 1, '2026-10-22 17:25:03');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `user_role` enum('admin','adviser','group') COLLATE utf8mb4_general_ci NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `notification_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `reference_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `user_role`, `message`, `notification_type`, `is_read`, `reference_id`, `created_at`) VALUES
(1, 1, 'admin', 'Capstone Archive Added: qwer', NULL, 0, 1, '2026-06-10 04:47:03'),
(3, 1, 'group', 'Your group registration has been approved! You now have full access.', NULL, 1, NULL, '2026-06-10 05:25:45'),
(4, 1, 'admin', 'Capstone Archive Added: qwheqweq', NULL, 0, 2, '2026-06-10 05:40:39'),
(5, 1, 'group', 'Capstone Archive Added: qwheqweq', NULL, 1, 2, '2026-06-10 05:40:39'),
(8, 1, 'group', 'New faculty adviser account has been registered: oliver pavillar', NULL, 1, NULL, '2026-06-10 13:45:32'),
(9, 1, 'group', 'New panel member has been registered: manuel uy', NULL, 1, NULL, '2026-06-15 02:11:49'),
(12, 1, 'group', 'Consultation Declined: Your meeting on Aug 14 1:00 PM has been declined by Your Adviser.', NULL, 1, NULL, '2026-08-14 04:48:51'),
(13, 1, 'group', 'New Task Posted for Chapter 1: Chapter 1 Submission (Deadline: Aug 24, 2026)', NULL, 1, NULL, '2026-08-23 16:30:46'),
(14, 1, 'group', 'New Task Posted for Chapter 1: Chapter 1 Submission (Deadline: Aug 24, 2026)', NULL, 1, NULL, '2026-08-23 18:07:54'),
(15, 1, 'group', 'New Task Posted for Chapter 2: Chapter 2 Submission (Deadline: Aug 24, 2026)', NULL, 1, NULL, '2026-08-23 18:14:45'),
(16, 1, 'group', 'New Task Posted for Chapter 3: Chapter 3 Submission (Deadline: Aug 24, 2026)', NULL, 1, NULL, '2026-08-23 18:14:59'),
(17, 1, 'group', 'New Task Posted for Chapter 1: Chapter 1 Submission (Deadline: Aug 24, 2026)', NULL, 1, NULL, '2026-08-23 18:15:21'),
(18, 1, 'group', 'New Task Posted for Chapter 4: Chapter 4 Submission (Deadline: Aug 24, 2026)', NULL, 1, NULL, '2026-08-23 18:20:21'),
(19, 1, 'group', 'New Task Posted for Chapter 5: Chapter 5 Submission (Deadline: Jul 29, 2026)', NULL, 1, NULL, '2026-08-23 18:20:46'),
(25, 1, 'group', 'Urgent: 3 days remaining to submit Chapter 1', NULL, 1, NULL, '2026-08-23 18:51:33'),
(26, 1, 'group', 'Urgent: 3 days remaining to submit Chapter 2', NULL, 1, NULL, '2026-08-23 18:51:33'),
(27, 1, 'group', 'Urgent: 3 days remaining to submit Chapter 3', NULL, 1, NULL, '2026-08-23 18:51:33'),
(28, 1, 'group', 'Urgent: 3 days remaining to submit Chapter 4', NULL, 1, NULL, '2026-08-23 18:51:33'),
(29, 1, 'group', 'New Task Posted for Chapter 5: Chapter 5 Submission (Deadline: Aug 28, 2026)', NULL, 1, NULL, '2026-08-23 18:53:58'),
(35, 1, 'group', 'Great news! Your submission for Chapter 5 has been APPROVED by your adviser.', NULL, 1, 5, '2026-08-23 19:04:14'),
(36, 1, 'group', 'Great news! Your submission for Chapter 4 has been APPROVED by your adviser.', NULL, 1, 4, '2026-08-23 19:04:19'),
(37, 1, 'group', 'Great news! Your submission for Chapter 3 has been APPROVED by your adviser.', NULL, 1, 3, '2026-08-23 19:04:24'),
(38, 1, 'group', 'Great news! Your submission for Chapter 2 has been APPROVED by your adviser.', NULL, 1, 2, '2026-08-23 19:05:43'),
(39, 1, 'group', 'Great news! Your submission for Chapter 1 has been APPROVED by your adviser.', NULL, 1, 1, '2026-08-23 19:06:25'),
(40, 1, 'group', 'Final Project auto-compiled from approved Chapters 1-5', NULL, 1, NULL, '2026-08-23 19:06:27'),
(41, 1, 'admin', 'Group account registered: group2', NULL, 1, NULL, '2026-08-24 16:31:51'),
(42, 2, 'group', 'Your group registration has been approved! You now have full access.', NULL, 1, NULL, '2026-08-24 16:32:22'),
(43, 1, 'group', 'New letter \'request for m\' has been uploaded for your group', NULL, 1, 1, '2026-08-24 16:33:30'),
(44, 1, 'adviser', 'Letter \'request for m\' has been uploaded for your group by admin', NULL, 1, 1, '2026-08-24 16:33:30'),
(45, 2, 'group', 'New letter \'request for m\' has been uploaded for your group', NULL, 1, 2, '2026-08-24 16:33:30'),
(46, 1, 'group', 'New letter \'adaw\' has been uploaded for your group', NULL, 1, 3, '2026-08-24 16:51:49'),
(47, 1, 'adviser', 'Letter \'adaw\' has been uploaded for your group by admin', NULL, 1, 3, '2026-08-24 16:51:49'),
(48, 2, 'group', 'New letter \'adaw\' has been uploaded for your group', NULL, 1, 4, '2026-08-24 16:51:49'),
(49, 1, 'group', 'Urgent: The deadline for \'Chapter 5 Submission\' is approaching on Aug 28, 2026 2:59 AM.', NULL, 1, NULL, '2026-08-24 20:57:39'),
(50, 1, 'adviser', 'Group Advisership Request: group2', NULL, 1, 2, '2026-08-25 22:05:06'),
(51, 2, 'group', 'Urgent: 3 days remaining to submit Chapter 5', NULL, 1, NULL, '2026-08-25 22:06:33'),
(52, 1, 'adviser', 'Submission uploaded by: group2', NULL, 1, 114, '2026-08-25 22:06:50'),
(53, 1, 'group', 'New Task Posted for Chapter 1: Chapter 1 Submission (Deadline: Jul 28, 2026)', NULL, 1, NULL, '2026-08-25 22:07:37'),
(54, 2, 'group', 'New Task Posted for Chapter 1: Chapter 1 Submission (Deadline: Jul 28, 2026)', NULL, 1, NULL, '2026-08-25 22:07:37'),
(55, 1, 'group', 'New Task Posted for Chapter 3: Chapter 3 Submission (Deadline: Aug 26, 2026)', NULL, 1, NULL, '2026-08-25 22:07:50'),
(56, 2, 'group', 'New Task Posted for Chapter 3: Chapter 3 Submission (Deadline: Aug 26, 2026)', NULL, 1, NULL, '2026-08-25 22:07:50'),
(57, 2, 'group', 'Urgent: 3 days remaining to submit Chapter 3', NULL, 1, NULL, '2026-08-25 22:07:52'),
(58, 1, 'adviser', 'Submission uploaded by: group2', NULL, 1, 118, '2026-08-25 22:08:07'),
(59, 1, 'group', 'New Task Posted for Chapter 1: Chapter 1 Submission (Deadline: Aug 26, 2026)', NULL, 1, NULL, '2026-08-25 22:08:31'),
(60, 2, 'group', 'New Task Posted for Chapter 1: Chapter 1 Submission (Deadline: Aug 26, 2026)', NULL, 1, NULL, '2026-08-25 22:08:31'),
(61, 2, 'group', 'Urgent: 3 days remaining to submit Chapter 1', NULL, 1, NULL, '2026-08-25 22:09:00'),
(62, 1, 'group', 'New Task Posted for Chapter 2: Chapter 2 Submission (Deadline: Aug 26, 2026)', NULL, 1, NULL, '2026-08-25 22:09:14'),
(63, 2, 'group', 'New Task Posted for Chapter 2: Chapter 2 Submission (Deadline: Aug 26, 2026)', NULL, 1, NULL, '2026-08-25 22:09:14'),
(64, 2, 'group', 'Urgent: 3 days remaining to submit Chapter 2', NULL, 1, NULL, '2026-08-25 22:09:18'),
(65, 1, 'group', 'New Task Posted for Chapter 4: Chapter 4 Submission (Deadline: Aug 26, 2026)', NULL, 1, NULL, '2026-08-25 22:09:36'),
(66, 2, 'group', 'New Task Posted for Chapter 4: Chapter 4 Submission (Deadline: Aug 26, 2026)', NULL, 1, NULL, '2026-08-25 22:09:36'),
(67, 2, 'group', 'Urgent: 3 days remaining to submit Chapter 4', NULL, 1, NULL, '2026-08-25 22:09:40'),
(68, 1, 'adviser', 'Submission uploaded by: group2', NULL, 1, 122, '2026-08-25 22:09:48'),
(69, 1, 'adviser', 'Submission uploaded by: group2', NULL, 1, 123, '2026-08-25 22:09:55'),
(70, 1, 'adviser', 'Submission uploaded by: group2', NULL, 1, 124, '2026-08-25 22:10:04'),
(71, 2, 'group', 'Great news! Your submission for Chapter 1 has been APPROVED by your adviser.', NULL, 1, 11, '2026-08-25 22:10:18'),
(72, 2, 'group', 'Great news! Your submission for Chapter 4 has been APPROVED by your adviser.', NULL, 1, 10, '2026-08-25 22:10:23'),
(73, 2, 'group', 'Great news! Your submission for Chapter 5 has been APPROVED by your adviser.', NULL, 1, 7, '2026-08-25 22:10:31'),
(74, 2, 'group', 'Great news! Your submission for Chapter 3 has been APPROVED by your adviser.', NULL, 1, 8, '2026-08-25 22:10:37'),
(75, 2, 'group', 'Great news! Your submission for Chapter 2 has been APPROVED by your adviser.', NULL, 1, 9, '2026-08-25 22:10:42'),
(76, 2, 'group', 'Final Project auto-compiled from approved Chapters 1-5', NULL, 1, NULL, '2026-08-26 15:24:34'),
(77, 1, 'group', 'At Risk: You missed the deadline for \'Chapter 1 Submission\'. Please submit immediately.', NULL, 1, NULL, '2026-08-26 16:43:13'),
(78, 2, 'group', 'At Risk: You missed the deadline for \'Chapter 1 Submission\'. Please submit immediately.', NULL, 1, NULL, '2026-08-26 16:43:13'),
(79, 1, 'group', 'At Risk: You missed the deadline for \'Chapter 3 Submission\'. Please submit immediately.', NULL, 1, NULL, '2026-08-26 16:43:13'),
(80, 1, 'group', 'At Risk: You missed the deadline for \'Chapter 1 Submission\'. Please submit immediately.', NULL, 1, NULL, '2026-08-26 16:43:13'),
(81, 1, 'group', 'At Risk: You missed the deadline for \'Chapter 2 Submission\'. Please submit immediately.', NULL, 1, NULL, '2026-08-26 16:43:13'),
(82, 1, 'group', 'At Risk: You missed the deadline for \'Chapter 4 Submission\'. Please submit immediately.', NULL, 1, NULL, '2026-08-26 16:43:13'),
(83, 1, 'adviser', 'Meeting Request from: group2', NULL, 1, 2, '2026-08-30 18:43:23'),
(84, 2, 'group', 'Consultation Accepted: Your meeting on Aug 31 3:00 AM has been accepted by Your Adviser.', NULL, 1, NULL, '2026-08-30 18:43:51'),
(85, 1, 'adviser', 'Reminder: You have an upcoming consultation with group2 scheduled on Aug 31, 2026 3:00 AM.', NULL, 1, NULL, '2026-08-30 18:52:28'),
(86, 1, 'group', 'Great news! Your submission for Chapter 6 has been APPROVED by your adviser.', NULL, 1, 6, '2026-08-30 19:14:13'),
(91, 1, 'admin', 'Capstone Archive Added: cpmmis', NULL, 0, 5, '2026-09-07 14:58:49'),
(92, 1, 'adviser', 'Capstone Archive Added: cpmmis', NULL, 1, 5, '2026-09-07 14:58:49'),
(93, 1, 'group', 'Capstone Archive Added: cpmmis', NULL, 1, 5, '2026-09-07 14:58:49'),
(94, 2, 'group', 'Capstone Archive Added: cpmmis', NULL, 1, 5, '2026-09-07 14:58:49'),
(95, 1, 'admin', 'Capstone Archive Added: wow', NULL, 0, 6, '2026-09-07 14:59:52'),
(96, 1, 'adviser', 'Capstone Archive Added: wow', NULL, 1, 6, '2026-09-07 14:59:52'),
(97, 1, 'group', 'Capstone Archive Added: wow', NULL, 1, 6, '2026-09-07 14:59:52'),
(98, 2, 'group', 'Capstone Archive Added: wow', NULL, 1, 6, '2026-09-07 14:59:52'),
(99, 1, 'admin', 'Group account registered: TestFixGroup', NULL, 1, NULL, '2026-09-11 17:02:50'),
(100, 1, 'admin', 'Group account registered: group3', NULL, 1, NULL, '2026-09-11 17:13:56'),
(101, 1, 'admin', 'Group account registered: eqeqeq', NULL, 1, NULL, '2026-09-11 20:53:47'),
(102, 1, 'admin', 'Group account registered: group3', NULL, 1, NULL, '2026-10-05 17:07:30'),
(103, 6, 'group', 'Your group registration has been approved! You now have full access.', NULL, 1, NULL, '2026-10-05 17:08:42'),
(104, 1, 'adviser', 'Group Advisership Request: group3', NULL, 1, 6, '2026-10-05 17:11:22'),
(105, 1, 'group', 'New Task Posted for Chapter 1: Chapter 1 Submission (Deadline: Oct 08, 2026)', NULL, 0, NULL, '2026-10-05 17:34:25'),
(106, 2, 'group', 'New Task Posted for Chapter 1: Chapter 1 Submission (Deadline: Oct 08, 2026)', NULL, 0, NULL, '2026-10-05 17:34:25'),
(107, 6, 'group', 'New Task Posted for Chapter 1: Chapter 1 Submission (Deadline: Oct 08, 2026)', NULL, 1, NULL, '2026-10-05 17:34:25'),
(108, 6, 'group', 'Urgent: 3 days remaining to submit Chapter 1', NULL, 1, NULL, '2026-10-05 17:36:40'),
(109, 1, 'adviser', 'Meeting Request from: group3', NULL, 1, 3, '2026-10-05 17:37:31'),
(110, 6, 'group', 'Consultation Declined: Your meeting on Oct 06 2:00 AM has been declined by Your Adviser.', NULL, 0, NULL, '2026-10-05 17:37:47'),
(111, 1, 'adviser', 'Meeting Request from: group3', NULL, 1, 4, '2026-10-05 17:38:46'),
(112, 6, 'group', 'Consultation Accepted: Your meeting on Oct 06 4:30 AM has been accepted by Your Adviser.', NULL, 0, NULL, '2026-10-05 17:38:54'),
(113, 1, 'group', 'At Risk: You missed the deadline for \'Chapter 1 Submission\'. Please submit immediately.', NULL, 0, NULL, '2026-10-20 14:39:41'),
(114, 2, 'group', 'At Risk: You missed the deadline for \'Chapter 1 Submission\'. Please submit immediately.', NULL, 0, NULL, '2026-10-20 14:39:41'),
(115, 6, 'group', 'At Risk: You missed the deadline for \'Chapter 1 Submission\'. Please submit immediately.', NULL, 0, NULL, '2026-10-20 14:39:41'),
(116, 1, 'admin', 'Capstone Archive Added: sasq', NULL, 0, 7, '2026-10-22 19:05:54'),
(117, 1, 'adviser', 'Capstone Archive Added: sasq', NULL, 1, 7, '2026-10-22 19:05:54'),
(118, 5, 'group', 'Capstone Archive Added: sasq', NULL, 0, 7, '2026-10-22 19:05:54'),
(119, 1, 'group', 'Capstone Archive Added: sasq', NULL, 0, 7, '2026-10-22 19:05:54'),
(120, 2, 'group', 'Capstone Archive Added: sasq', NULL, 0, 7, '2026-10-22 19:05:54'),
(121, 6, 'group', 'Capstone Archive Added: sasq', NULL, 0, 7, '2026-10-22 19:05:54'),
(122, 1, 'admin', 'Capstone Archive Added: ogag', NULL, 0, 8, '2026-10-22 19:07:01'),
(123, 1, 'adviser', 'Capstone Archive Added: ogag', NULL, 1, 8, '2026-10-22 19:07:01'),
(124, 5, 'group', 'Capstone Archive Added: ogag', NULL, 0, 8, '2026-10-22 19:07:01'),
(125, 1, 'group', 'Capstone Archive Added: ogag', NULL, 0, 8, '2026-10-22 19:07:01'),
(126, 2, 'group', 'Capstone Archive Added: ogag', NULL, 0, 8, '2026-10-22 19:07:01'),
(127, 6, 'group', 'Capstone Archive Added: ogag', NULL, 0, 8, '2026-10-22 19:07:01'),
(128, 1, 'admin', 'Capstone Archive Added: eqweqqeq', NULL, 0, 9, '2026-11-06 10:28:13'),
(129, 1, 'adviser', 'Capstone Archive Added: eqweqqeq', NULL, 1, 9, '2026-11-06 10:28:13'),
(130, 5, 'group', 'Capstone Archive Added: eqweqqeq', NULL, 0, 9, '2026-11-06 10:28:13'),
(131, 1, 'group', 'Capstone Archive Added: eqweqqeq', NULL, 0, 9, '2026-11-06 10:28:13'),
(132, 2, 'group', 'Capstone Archive Added: eqweqqeq', NULL, 0, 9, '2026-11-06 10:28:13'),
(133, 6, 'group', 'Capstone Archive Added: eqweqqeq', NULL, 0, 9, '2026-11-06 10:28:13'),
(134, 1, 'admin', 'Group account registered: password', NULL, 1, NULL, '2026-11-14 03:29:15'),
(135, 7, 'group', 'Your group registration has been approved! You now have full access.', NULL, 1, NULL, '2026-11-14 03:29:44'),
(136, 1, 'adviser', 'Group Advisership Request: password', NULL, 0, 7, '2026-11-14 03:30:21'),
(137, 1, 'group', 'New Task Posted for Chapter 1: Chapter 1 Submission (Deadline: Nov 16, 2026)', NULL, 0, NULL, '2026-11-14 03:31:36'),
(138, 2, 'group', 'New Task Posted for Chapter 1: Chapter 1 Submission (Deadline: Nov 16, 2026)', NULL, 0, NULL, '2026-11-14 03:31:36'),
(139, 6, 'group', 'New Task Posted for Chapter 1: Chapter 1 Submission (Deadline: Nov 16, 2026)', NULL, 0, NULL, '2026-11-14 03:31:36'),
(140, 7, 'group', 'New Task Posted for Chapter 1: Chapter 1 Submission (Deadline: Nov 16, 2026)', NULL, 1, NULL, '2026-11-14 03:31:36'),
(141, 1, 'group', 'Urgent: The deadline for \'Chapter 1 Submission\' is approaching on Nov 16, 2026 1:31 PM.', NULL, 0, NULL, '2026-11-14 03:31:40'),
(142, 2, 'group', 'Urgent: The deadline for \'Chapter 1 Submission\' is approaching on Nov 16, 2026 1:31 PM.', NULL, 0, NULL, '2026-11-14 03:31:40'),
(143, 6, 'group', 'Urgent: The deadline for \'Chapter 1 Submission\' is approaching on Nov 16, 2026 1:31 PM.', NULL, 0, NULL, '2026-11-14 03:31:40'),
(144, 7, 'group', 'Urgent: The deadline for \'Chapter 1 Submission\' is approaching on Nov 16, 2026 1:31 PM.', NULL, 1, NULL, '2026-11-14 03:31:40'),
(145, 7, 'group', 'Urgent: 3 days remaining to submit Chapter 1', NULL, 1, NULL, '2026-11-14 03:31:52');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp_code` varchar(255) NOT NULL,
  `attempts` int NOT NULL DEFAULT '0',
  `locked_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `otp_code`, `attempts`, `locked_at`, `created_at`) VALUES
(14, 'jptejoc@gmail.com', '$2y$10$QGKgzS/D6pkQOwrrlXZ9YuV99.jf9FJIGJ2tvWnOTsUUR0A7yGkia', 0, NULL, '2026-11-13 03:50:25');

-- --------------------------------------------------------

--
-- Table structure for table `schema_migrations`
--

CREATE TABLE `schema_migrations` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `applied_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schema_migrations`
--

INSERT INTO `schema_migrations` (`id`, `name`, `applied_at`) VALUES
(1, 'messages_sender_role_group', '2026-09-10 17:44:55'),
(2, 'messages_receiver_role_group', '2026-09-10 17:44:55'),
(3, 'notifications_role_group', '2026-09-10 17:44:55'),
(4, 'group_last_activity', '2026-09-10 17:44:55'),
(5, 'adviser_last_activity', '2026-09-10 17:44:55'),
(6, 'admin_last_activity', '2026-09-10 17:44:55'),
(7, 'activity_logs_columns', '2026-09-10 17:44:55'),
(8, 'task_edit_history_table', '2026-09-10 17:44:55'),
(9, 'admin_letters_table', '2026-09-10 17:44:55'),
(10, 'group_submissions_submission_type', '2026-09-10 17:44:55'),
(11, 'group_submissions_feedback', '2026-09-10 17:44:55'),
(12, 'group_submissions_version', '2026-09-10 17:44:55'),
(13, 'group_submissions_is_final', '2026-09-10 17:44:55'),
(14, 'group_banned_until', '2026-09-10 17:44:55'),
(15, 'group_session_version', '2026-09-10 17:44:55'),
(16, 'adviser_session_version', '2026-09-10 17:44:55'),
(17, 'admin_session_version', '2026-09-10 17:44:56'),
(18, 'group_submissions_auto_combine_unique', '2026-09-10 17:44:56'),
(19, 'capstone_archive_indexes', '2026-09-10 17:44:56'),
(20, 'meeting_requests_indexes', '2026-09-10 17:44:56'),
(21, 'notifications_type_column', '2026-09-10 17:44:56'),
(22, 'notifications_index_type', '2026-09-10 17:44:56'),
(23, 'drop_panelist_tables', '2026-09-10 17:44:56'),
(24, 'group_submissions_task_fk', '2026-09-10 17:44:56'),
(25, 'meeting_requests_fks', '2026-09-10 17:44:56'),
(26, 'group_adviser_fk', '2026-09-10 17:44:56'),
(27, 'adviser_tasks_adviser_fk', '2026-09-10 17:44:57'),
(28, 'group_milestones_group_fk', '2026-09-10 17:44:57'),
(29, 'admin_letters_group_fk', '2026-09-10 17:44:57'),
(30, 'task_edit_history_editor_fk', '2026-09-10 17:44:57');

-- --------------------------------------------------------

--
-- Table structure for table `task_edit_history`
--

CREATE TABLE `task_edit_history` (
  `id` int NOT NULL,
  `task_id` int NOT NULL,
  `old_deadline` datetime DEFAULT NULL,
  `new_deadline` datetime NOT NULL,
  `edited_by` int NOT NULL,
  `edited_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logs_role` (`user_role`),
  ADD KEY `idx_logs_time` (`created_at`),
  ADD KEY `idx_logs_user` (`user_id`);

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `admin_letters`
--
ALTER TABLE `admin_letters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `idx_letters_group` (`group_id`),
  ADD KEY `idx_letters_time` (`created_at`);

--
-- Indexes for table `adviser`
--
ALTER TABLE `adviser`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `adviser_requests`
--
ALTER TABLE `adviser_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `adviser_id` (`adviser_id`);

--
-- Indexes for table `adviser_tasks`
--
ALTER TABLE `adviser_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tasks_adviser` (`adviser_id`);

--
-- Indexes for table `capstone_archive`
--
ALTER TABLE `capstone_archive`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_archive_title` (`title`),
  ADD KEY `idx_archive_year` (`year`),
  ADD KEY `idx_archive_category` (`category`);

--
-- Indexes for table `group`
--
ALTER TABLE `group`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `adviser_id` (`adviser_id`),
  ADD KEY `idx_group_status` (`status`),
  ADD KEY `idx_group_adviser` (`adviser_id`);

--
-- Indexes for table `group_submissions`
--
ALTER TABLE `group_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_submissions_group_chapter_doc` (`group_id`,`chapter_number`,`document_name`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `idx_submissions_group_task` (`group_id`,`task_id`),
  ADD KEY `idx_submissions_status` (`status`),
  ADD KEY `fk_submissions_task` (`task_id`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_user` (`user_id`,`role`),
  ADD KEY `idx_login_time` (`login_time`);

--
-- Indexes for table `login_rate_limits`
--
ALTER TABLE `login_rate_limits`
  ADD PRIMARY KEY (`ip_address`,`username`);

--
-- Indexes for table `meeting_requests`
--
ALTER TABLE `meeting_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_meetings_adviser_status` (`adviser_id`,`status`),
  ADD KEY `idx_meetings_group` (`group_id`),
  ADD KEY `idx_meetings_status_time` (`status`,`meeting_time`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_lookup` (`user_id`,`user_role`,`is_read`),
  ADD KEY `idx_notif_time` (`id`),
  ADD KEY `idx_notif_user_read_type` (`user_id`,`user_role`,`is_read`,`notification_type`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`);

--
-- Indexes for table `schema_migrations`
--
ALTER TABLE `schema_migrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `task_edit_history`
--
ALTER TABLE `task_edit_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `fk_teh_editor` (`edited_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=238;

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_letters`
--
ALTER TABLE `admin_letters`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `adviser`
--
ALTER TABLE `adviser`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `adviser_requests`
--
ALTER TABLE `adviser_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `adviser_tasks`
--
ALTER TABLE `adviser_tasks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `capstone_archive`
--
ALTER TABLE `capstone_archive`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `group`
--
ALTER TABLE `group`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `group_submissions`
--
ALTER TABLE `group_submissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `meeting_requests`
--
ALTER TABLE `meeting_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=146;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `schema_migrations`
--
ALTER TABLE `schema_migrations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `task_edit_history`
--
ALTER TABLE `task_edit_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_letters`
--
ALTER TABLE `admin_letters`
  ADD CONSTRAINT `fk_letters_group` FOREIGN KEY (`group_id`) REFERENCES `group` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `adviser_requests`
--
ALTER TABLE `adviser_requests`
  ADD CONSTRAINT `adviser_requests_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `group` (`id`),
  ADD CONSTRAINT `adviser_requests_ibfk_2` FOREIGN KEY (`adviser_id`) REFERENCES `adviser` (`id`);

--
-- Constraints for table `adviser_tasks`
--
ALTER TABLE `adviser_tasks`
  ADD CONSTRAINT `fk_advtasks_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `adviser` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `group`
--
ALTER TABLE `group`
  ADD CONSTRAINT `fk_group_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `adviser` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `group_submissions`
--
ALTER TABLE `group_submissions`
  ADD CONSTRAINT `fk_submissions_task` FOREIGN KEY (`task_id`) REFERENCES `adviser_tasks` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `group_submissions_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `group` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `meeting_requests`
--
ALTER TABLE `meeting_requests`
  ADD CONSTRAINT `fk_meetings_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `adviser` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_meetings_group` FOREIGN KEY (`group_id`) REFERENCES `group` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_edit_history`
--
ALTER TABLE `task_edit_history`
  ADD CONSTRAINT `fk_teh_editor` FOREIGN KEY (`edited_by`) REFERENCES `adviser` (`id`),
  ADD CONSTRAINT `task_edit_history_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `adviser_tasks` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
