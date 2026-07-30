-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 08, 2026 at 11:54 AM
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
-- Database: `hms_database`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `log_id` bigint(20) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(10) UNSIGNED DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`log_id`, `staff_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'Login', NULL, NULL, 'User admin logged in | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '::1', '2026-07-07 17:51:50'),
(2, 1, 'Login', NULL, NULL, 'User admin logged in successfully | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-07 17:53:41'),
(3, 1, 'Logout', NULL, NULL, 'User admin logged out | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-07 17:59:35'),
(4, NULL, 'Failed Login', NULL, NULL, 'Failed login attempt for username: admin | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-07 17:59:48'),
(5, NULL, 'Failed Login', NULL, NULL, 'Failed login attempt for username: admin | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-07 18:00:02'),
(6, NULL, 'Failed Login', NULL, NULL, 'Failed login attempt for username: admin | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-07 18:00:19'),
(7, NULL, 'Failed Login', NULL, NULL, 'Failed login attempt for username: Admin | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-07 18:00:42'),
(8, NULL, 'Failed Login', NULL, NULL, 'Failed login attempt for username: admin | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-07 18:01:17'),
(9, NULL, 'Failed Login', NULL, NULL, 'Failed login attempt for username: admin | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-07 18:01:34'),
(10, NULL, 'Failed Login', NULL, NULL, 'Failed login attempt for username: admin | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-07 18:01:42'),
(11, NULL, 'Failed Login', NULL, NULL, 'Failed login attempt for username: admin | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-07 18:01:59'),
(12, NULL, 'Failed Login', NULL, NULL, 'Failed login attempt for username: Doctor | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-07 18:02:27'),
(13, NULL, 'Failed Login', NULL, NULL, 'Failed login attempt for username: drjohnson | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-07 18:02:38'),
(14, NULL, 'Failed Login', NULL, NULL, 'Failed login attempt for username: drjohnson | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-07 18:04:43'),
(15, NULL, 'Failed Login', NULL, NULL, 'Failed login attempt for username: admin - Incorrect password | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-07 18:07:34'),
(16, NULL, 'Failed Login', NULL, NULL, 'Failed login attempt for username: admin - Incorrect password | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-07 18:08:52'),
(17, NULL, 'Failed Login', NULL, NULL, 'Failed login attempt for username: admin - Incorrect password | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-07 18:09:08'),
(18, 1, 'Login', NULL, NULL, 'User admin logged in successfully | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-07 18:10:11'),
(19, 1, 'Logout', NULL, NULL, 'User admin logged out | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-07 18:10:36'),
(20, 1, 'Login', NULL, NULL, 'User admin logged in successfully | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-07 18:10:59'),
(21, NULL, 'Failed Login', NULL, NULL, 'Failed login attempt for username: admin - Incorrect password | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-08 11:21:57'),
(22, 1, 'Login', NULL, NULL, 'User admin logged in successfully | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-08 11:22:07'),
(23, 1, 'Logout', NULL, NULL, 'User admin logged out | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-08 11:42:28'),
(24, NULL, 'Failed Login', NULL, NULL, 'Failed login attempt for username: drjohnson - Incorrect password | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-08 11:42:45'),
(25, 6, 'Login', NULL, NULL, 'User labtech logged in successfully | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-08 11:44:48'),
(26, 6, 'Logout', NULL, NULL, 'User labtech logged out | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-08 11:47:12'),
(27, 8, 'Login', NULL, NULL, 'User billing logged in successfully | User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '::1', '2026-07-08 11:48:04');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Scheduled',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `beds`
--

CREATE TABLE `beds` (
  `bed_id` int(10) UNSIGNED NOT NULL,
  `ward_id` int(10) UNSIGNED NOT NULL,
  `bed_type_id` int(10) UNSIGNED DEFAULT NULL,
  `price_per_day` decimal(10,2) DEFAULT NULL,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features`)),
  `bed_number` varchar(20) NOT NULL,
  `status` enum('Available','Occupied','Maintenance','Reserved') DEFAULT 'Available',
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `beds`
--

INSERT INTO `beds` (`bed_id`, `ward_id`, `bed_type_id`, `price_per_day`, `features`, `bed_number`, `status`, `is_active`) VALUES
(1, 1, 1, NULL, NULL, 'E-101', 'Available', 1),
(2, 1, 1, NULL, NULL, 'E-102', 'Available', 1),
(3, 1, 1, NULL, NULL, 'E-103', 'Occupied', 1),
(4, 2, 1, NULL, NULL, 'W-101', 'Available', 1),
(5, 2, 1, NULL, NULL, 'W-102', 'Available', 1),
(6, 2, 1, NULL, NULL, 'W-103', 'Available', 1),
(7, 3, 1, NULL, NULL, 'M-101', 'Occupied', 1),
(8, 3, 1, NULL, NULL, 'M-102', 'Available', 1),
(9, 3, 1, NULL, NULL, 'M-103', 'Available', 1),
(10, 4, 4, NULL, NULL, 'ICU-01', 'Occupied', 1),
(11, 4, 4, NULL, NULL, 'ICU-02', 'Available', 1);

-- --------------------------------------------------------

--
-- Table structure for table `bed_assignments`
--

CREATE TABLE `bed_assignments` (
  `assignment_id` int(10) UNSIGNED NOT NULL,
  `bed_id` int(10) UNSIGNED NOT NULL,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `discharged_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctor_schedules`
--

CREATE TABLE `doctor_schedules` (
  `schedule_id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `day_of_week` tinyint(3) UNSIGNED NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `max_patients` int(10) UNSIGNED DEFAULT 20,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `insurance_providers`
--

CREATE TABLE `insurance_providers` (
  `provider_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(20) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `insurance_providers`
--

INSERT INTO `insurance_providers` (`provider_id`, `name`, `code`, `phone`, `email`, `address`, `is_active`) VALUES
(1, 'National Health Insurance', 'NHI-001', NULL, NULL, NULL, 1),
(2, 'Private Health Care', 'PHC-001', NULL, NULL, NULL, 1),
(3, 'Workers Compensation', 'WC-001', NULL, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `invoice_code` varchar(20) NOT NULL,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `invoice_status_id` int(10) UNSIGNED NOT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `invoice_item_id` int(10) UNSIGNED NOT NULL,
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `description` varchar(200) NOT NULL,
  `item_type` varchar(30) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_orders`
--

CREATE TABLE `lab_orders` (
  `order_id` int(10) UNSIGNED NOT NULL,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `test_type_id` int(10) UNSIGNED NOT NULL,
  `ordered_by` int(10) UNSIGNED NOT NULL,
  `order_status_id` int(10) UNSIGNED NOT NULL,
  `ordered_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_results`
--

CREATE TABLE `lab_results` (
  `result_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `entered_by` int(10) UNSIGNED NOT NULL,
  `result_value` text NOT NULL,
  `result_notes` text DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `entered_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lookup_bed_types`
--

CREATE TABLE `lookup_bed_types` (
  `bed_type_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lookup_bed_types`
--

INSERT INTO `lookup_bed_types` (`bed_type_id`, `name`, `description`, `base_price`) VALUES
(1, 'General', 'Standard general ward bed', 50.00),
(2, 'Semi-Private', 'Semi-private room with 2-3 beds', 75.00),
(3, 'Private', 'Private room with single bed', 120.00),
(4, 'ICU', 'Intensive Care Unit bed', 200.00),
(5, 'Maternity', 'Maternity ward bed', 80.00),
(6, 'Pediatric', 'Children\'s ward bed', 60.00);

-- --------------------------------------------------------

--
-- Table structure for table `lookup_departments`
--

CREATE TABLE `lookup_departments` (
  `department_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `code` varchar(10) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lookup_departments`
--

INSERT INTO `lookup_departments` (`department_id`, `name`, `code`, `description`, `is_active`) VALUES
(1, 'Reception / Front Desk', 'REC', 'Registration and visit initiation', 1),
(2, 'Outpatient Department', 'OPD', 'Scheduled consultations', 1),
(3, 'Inpatient Department', 'IPD', 'Ward-based admitted care', 1),
(4, 'Emergency / Trauma', 'ER', 'Urgent unscheduled care', 1),
(5, 'Operation Theater', 'OT', 'Surgical procedures', 1),
(6, 'Radiology & Laboratory', 'LAB', 'Diagnostic imaging and pathology', 1),
(7, 'Pharmacy', 'PHM', 'Medication dispensing', 1),
(8, 'Billing & Finance', 'BIL', 'Invoicing and payment collection', 1);

-- --------------------------------------------------------

--
-- Table structure for table `lookup_genders`
--

CREATE TABLE `lookup_genders` (
  `gender_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lookup_genders`
--

INSERT INTO `lookup_genders` (`gender_id`, `name`) VALUES
(2, 'Female'),
(1, 'Male'),
(3, 'Other');

-- --------------------------------------------------------

--
-- Table structure for table `lookup_invoice_statuses`
--

CREATE TABLE `lookup_invoice_statuses` (
  `invoice_status_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lookup_invoice_statuses`
--

INSERT INTO `lookup_invoice_statuses` (`invoice_status_id`, `name`) VALUES
(3, 'Paid'),
(2, 'Partially Paid'),
(1, 'Unpaid'),
(4, 'Void');

-- --------------------------------------------------------

--
-- Table structure for table `lookup_order_statuses`
--

CREATE TABLE `lookup_order_statuses` (
  `order_status_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lookup_order_statuses`
--

INSERT INTO `lookup_order_statuses` (`order_status_id`, `name`) VALUES
(5, 'Cancelled'),
(1, 'Ordered'),
(3, 'Result Ready'),
(4, 'Reviewed'),
(2, 'Sample Collected');

-- --------------------------------------------------------

--
-- Table structure for table `lookup_payment_methods`
--

CREATE TABLE `lookup_payment_methods` (
  `payment_method_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lookup_payment_methods`
--

INSERT INTO `lookup_payment_methods` (`payment_method_id`, `name`) VALUES
(2, 'Card'),
(1, 'Cash'),
(3, 'Insurance'),
(4, 'Mobile Money');

-- --------------------------------------------------------

--
-- Table structure for table `lookup_roles`
--

CREATE TABLE `lookup_roles` (
  `role_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lookup_roles`
--

INSERT INTO `lookup_roles` (`role_id`, `name`, `description`) VALUES
(1, 'Receptionist', 'Registers patients, opens visits, schedules appointments'),
(2, 'Nurse', 'Records vitals, supports admission/discharge'),
(3, 'Doctor', 'Consultations, diagnoses, orders, prescriptions'),
(4, 'Lab Technician', 'Processes diagnostic orders and enters results'),
(5, 'Pharmacist', 'Dispenses medication, manages inventory'),
(6, 'Billing Staff', 'Generates invoices, processes payments'),
(7, 'System Administrator', 'Manages staff, roles and system configuration');

-- --------------------------------------------------------

--
-- Table structure for table `lookup_test_types`
--

CREATE TABLE `lookup_test_types` (
  `test_type_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `category` varchar(40) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lookup_test_types`
--

INSERT INTO `lookup_test_types` (`test_type_id`, `name`, `category`, `price`, `is_active`) VALUES
(1, 'Complete Blood Count (CBC)', 'Laboratory', 350.00, 1),
(2, 'Basic Metabolic Panel', 'Laboratory', 420.00, 1),
(3, 'Urinalysis', 'Laboratory', 180.00, 1),
(4, 'Chest X-Ray', 'Radiology', 650.00, 1),
(5, 'Abdominal Ultrasound', 'Radiology', 900.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `lookup_visit_statuses`
--

CREATE TABLE `lookup_visit_statuses` (
  `visit_status_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lookup_visit_statuses`
--

INSERT INTO `lookup_visit_statuses` (`visit_status_id`, `name`) VALUES
(5, 'Awaiting Billing'),
(4, 'Awaiting Results'),
(7, 'Cancelled'),
(6, 'Discharged'),
(3, 'In Consultation'),
(1, 'Registered'),
(2, 'Triage');

-- --------------------------------------------------------

--
-- Table structure for table `lookup_visit_types`
--

CREATE TABLE `lookup_visit_types` (
  `visit_type_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lookup_visit_types`
--

INSERT INTO `lookup_visit_types` (`visit_type_id`, `name`) VALUES
(3, 'Emergency'),
(2, 'IPD'),
(1, 'OPD');

-- --------------------------------------------------------

--
-- Table structure for table `medical_records`
--

CREATE TABLE `medical_records` (
  `record_id` int(10) UNSIGNED NOT NULL,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `diagnosis` varchar(255) DEFAULT NULL,
  `clinical_notes` text DEFAULT NULL,
  `needs_lab` tinyint(1) NOT NULL DEFAULT 0,
  `needs_bed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medications`
--

CREATE TABLE `medications` (
  `medication_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `strength` varchar(50) DEFAULT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'unit',
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `reorder_level` int(10) UNSIGNED NOT NULL DEFAULT 20,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medications`
--

INSERT INTO `medications` (`medication_id`, `name`, `strength`, `unit`, `unit_price`, `stock_quantity`, `reorder_level`, `is_active`) VALUES
(1, 'Paracetamol', '500mg', 'tablet', 0.50, 1000, 50, 1),
(2, 'Ibuprofen', '400mg', 'tablet', 0.75, 800, 40, 1),
(3, 'Amoxicillin', '250mg', 'capsule', 1.20, 600, 30, 1),
(4, 'Omeprazole', '20mg', 'capsule', 0.90, 500, 25, 1),
(5, 'Metformin', '500mg', 'tablet', 0.60, 700, 35, 1),
(6, 'Atorvastatin', '10mg', 'tablet', 1.50, 400, 20, 1),
(7, 'Losartan', '50mg', 'tablet', 1.10, 450, 22, 1),
(8, 'Ciprofloxacin', '500mg', 'tablet', 1.80, 350, 18, 1),
(9, 'Diclofenac', '50mg', 'tablet', 0.85, 600, 30, 1),
(10, 'Cetirizine', '10mg', 'tablet', 0.40, 900, 45, 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(30) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `link` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `patient_id` int(10) UNSIGNED NOT NULL,
  `patient_code` varchar(20) NOT NULL,
  `first_name` varchar(60) NOT NULL,
  `last_name` varchar(60) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender_id` int(10) UNSIGNED DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `national_id` varchar(40) DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `registered_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `primary_doctor_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`patient_id`, `patient_code`, `first_name`, `last_name`, `date_of_birth`, `gender_id`, `phone`, `email`, `address`, `national_id`, `blood_group`, `emergency_contact_name`, `emergency_contact_phone`, `allergies`, `registered_at`, `is_active`, `primary_doctor_id`) VALUES
(1, 'PT-2026-000001', 'John', 'Doe', '1985-03-15', 1, '555-0101', 'john.doe@email.com', '123 Main St, City', 'ID-001', 'A+', 'Jane Doe', '555-0102', NULL, '2026-07-07 17:34:04', 1, NULL),
(2, 'PT-2026-000002', 'Mary', 'Smith', '1990-07-22', 2, '555-0103', 'mary.smith@email.com', '456 Oak Ave, City', 'ID-002', 'B+', 'Robert Smith', '555-0104', NULL, '2026-07-07 17:34:04', 1, NULL),
(3, 'PT-2026-000003', 'Robert', 'Williams', '1978-11-03', 1, '555-0105', 'robert.w@email.com', '789 Pine St, City', 'ID-003', 'O+', 'Laura Williams', '555-0106', NULL, '2026-07-07 17:34:04', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `patient_insurance`
--

CREATE TABLE `patient_insurance` (
  `patient_insurance_id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `provider_id` int(10) UNSIGNED NOT NULL,
  `policy_number` varchar(50) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(10) UNSIGNED NOT NULL,
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `payment_method_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `received_by` int(10) UNSIGNED DEFAULT NULL,
  `paid_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reference_no` varchar(60) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `prescription_id` int(10) UNSIGNED NOT NULL,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `prescribed_by` int(10) UNSIGNED NOT NULL,
  `prescribed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescription_items`
--

CREATE TABLE `prescription_items` (
  `item_id` int(10) UNSIGNED NOT NULL,
  `prescription_id` int(10) UNSIGNED NOT NULL,
  `medication_id` int(10) UNSIGNED NOT NULL,
  `dosage` varchar(80) DEFAULT NULL,
  `duration_days` smallint(5) UNSIGNED DEFAULT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `note` text DEFAULT NULL,
  `dispensed_by` int(10) UNSIGNED DEFAULT NULL,
  `dispensed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `procedures`
--

CREATE TABLE `procedures` (
  `procedure_id` int(10) UNSIGNED NOT NULL,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `procedure_code` varchar(20) NOT NULL,
  `procedure_name` varchar(200) NOT NULL,
  `scheduled_date` datetime DEFAULT NULL,
  `performed_date` datetime DEFAULT NULL,
  `surgeon_id` int(10) UNSIGNED DEFAULT NULL,
  `assistant_id` int(10) UNSIGNED DEFAULT NULL,
  `anesthesiologist_id` int(10) UNSIGNED DEFAULT NULL,
  `status` varchar(30) DEFAULT 'Scheduled',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `staff_id` int(10) UNSIGNED NOT NULL,
  `staff_code` varchar(20) NOT NULL,
  `first_name` varchar(60) NOT NULL,
  `last_name` varchar(60) NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `email` varchar(120) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`staff_id`, `staff_code`, `first_name`, `last_name`, `role_id`, `department_id`, `email`, `phone`, `username`, `password_hash`, `is_active`, `created_at`) VALUES
(1, 'ADM-001', 'System', 'Administrator', 7, 1, 'admin@hospital.com', '1234567890', 'admin', '$2y$10$wtcZBnQL/HUsqLgc9eTp8eLEU9cpe53MX4SxA9tuPL48TUuJxuSiC', 1, '2026-07-07 17:34:03'),
(2, 'DOC-001', 'Dr. Sarah', 'Johnson', 3, 2, 'sarah.johnson@hospital.com', '9876543210', 'drjohnson', '$2y$10$OEPA9Vpni6klm6HsNs05QuVERsaSwAKwUjzOkS6ahYf3XvS.ysllG', 1, '2026-07-07 17:34:03'),
(3, 'DOC-002', 'Dr. Michael', 'Smith', 3, 3, 'michael.smith@hospital.com', '9876543211', 'drsmith', '$2y$10$ova7QQH/D.rWg0GPCzs.ouadvHM9ZRpKR7VLS9bCLwxDvT0fqK38K', 1, '2026-07-07 17:34:03'),
(4, 'NUR-001', 'Nurse', 'Emily', 2, 3, 'emily.nurse@hospital.com', '9876543212', 'nurseemily', '$2y$10$oA4eXpr6hlbEn/b0qEpvve8duLw.QBpEc..M185jGRf.Y7Ck8qC0a', 1, '2026-07-07 17:34:03'),
(5, 'REC-001', 'Reception', 'Staff', 1, 1, 'reception@hospital.com', '9876543213', 'reception', '$2y$10$1xHLWeWrz53zuwOIxcWltOjr.qD0War72wdY3q5gMyRuenMFH7aQO', 1, '2026-07-07 17:34:04'),
(6, 'LAB-001', 'Lab', 'Technician', 4, 6, 'lab.tech@hospital.com', '9876543214', 'labtech', '$2y$10$uHVXkIwxd8kie.DzQTLGyu5n3syNXvDrtSyEgO9MK9vT.SOt1k0ha', 1, '2026-07-07 17:34:04'),
(7, 'PHM-001', 'Pharmacy', 'Staff', 5, 7, 'pharmacy@hospital.com', '9876543215', 'pharmacist', '$2y$10$UB1cIYu/DfAtJOhhb/OtzOvUVWkC1vKh1LmRTXVtbZck9M5.wLhAe', 1, '2026-07-07 17:34:04'),
(8, 'BIL-001', 'Billing', 'Officer', 6, 8, 'billing@hospital.com', '9876543216', 'billing', '$2y$10$eJdjLdIg77nRzLGlags59eDaQZY.lmPzMo/SSv8mUelz/ChmW2TBC', 1, '2026-07-07 17:34:04');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `staff_id`, `username`, `email`, `password_hash`, `full_name`, `role_id`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, NULL, 'admin', 'admin@hospital.com', '$2y$10$g/HfbSKQzp.NhjFPEz6f4eyJl1yWZmpzgtJHtiCAtLcxaqG3yP/cC', 'System Administrator', 7, 1, '2026-07-08 11:22:07', '2026-07-07 17:38:54', '2026-07-08 11:22:07'),
(2, NULL, 'drjohnson', 'dr.johnson@hospital.com', '$2y$10$Xtg14PSocImRot8xVnzNLeX75Me4djQTWKwqlXCvDKwAA03DhIESy', 'Dr. Sarah Johnson', 3, 1, NULL, '2026-07-07 17:38:54', '2026-07-07 18:09:38'),
(3, NULL, 'drsmith', 'dr.smith@hospital.com', '$2y$10$XRLrdJYhndqs9DAFb9sWleahU8DtqzsSehrS7PUS3uS/YWeN88zE2', 'Dr. Michael Smith', 3, 1, NULL, '2026-07-07 17:38:54', '2026-07-07 18:09:38'),
(4, NULL, 'nurseemily', 'emily.nurse@hospital.com', '$2y$10$/vk.lT6Qxs0IbFo8MiqNEOFGWPldwKyrZxUy.hxC5794No1Dbz41S', 'Nurse Emily Brown', 2, 1, NULL, '2026-07-07 17:38:54', '2026-07-07 18:09:38'),
(5, NULL, 'reception', 'reception@hospital.com', '$2y$10$Ogo0dlqvnFiY2uedqlE4MO4uGMjoJ5Oobz0j9ihesUwHbFQIpcGGu', 'Reception Staff', 1, 1, NULL, '2026-07-07 17:38:54', '2026-07-07 18:09:38'),
(6, NULL, 'labtech', 'lab.tech@hospital.com', '$2y$10$enTwCvI/sIx1M5DS7WEgieLw3SCvLuYzGV36Zpoa/JDMDRmtMunK2', 'Lab Technician', 4, 1, '2026-07-08 11:44:48', '2026-07-07 17:38:54', '2026-07-08 11:44:48'),
(7, NULL, 'pharmacist', 'pharmacy@hospital.com', '$2y$10$apox.8yEPv99CR6LPmJ8O.pASc5AsPn1AWdZMOV6Tao07xgiQfM06', 'Pharmacist Staff', 5, 1, NULL, '2026-07-07 17:38:54', '2026-07-07 18:09:38'),
(8, NULL, 'billing', 'billing@hospital.com', '$2y$10$/OEvpPbWg76AdhPAe7bNSObBQQUsp5WQ8K15DW2gKK5Jp0qyAV6Ky', 'Billing Officer', 6, 1, '2026-07-08 11:48:04', '2026-07-07 17:38:54', '2026-07-08 11:48:04');

-- --------------------------------------------------------

--
-- Table structure for table `visits`
--

CREATE TABLE `visits` (
  `visit_id` int(10) UNSIGNED NOT NULL,
  `visit_code` varchar(20) NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `visit_type_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `attending_doctor_id` int(10) UNSIGNED DEFAULT NULL,
  `visit_status_id` int(10) UNSIGNED NOT NULL,
  `admitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `discharged_at` datetime DEFAULT NULL,
  `discharged_by` int(10) UNSIGNED DEFAULT NULL,
  `ward_bed` varchar(30) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vital_signs`
--

CREATE TABLE `vital_signs` (
  `vital_id` int(10) UNSIGNED NOT NULL,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `temperature_c` decimal(4,1) DEFAULT NULL,
  `pulse_bpm` smallint(5) UNSIGNED DEFAULT NULL,
  `blood_pressure` varchar(15) DEFAULT NULL,
  `respiration_rate` smallint(5) UNSIGNED DEFAULT NULL,
  `spo2_percent` tinyint(3) UNSIGNED DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_users`
-- (See below for the actual view)
--
CREATE TABLE `vw_users` (
`user_id` int(10) unsigned
,`username` varchar(50)
,`email` varchar(120)
,`full_name` varchar(120)
,`is_active` tinyint(1)
,`last_login` datetime
,`created_at` datetime
,`role_name` varchar(50)
,`role_id` int(10) unsigned
);

-- --------------------------------------------------------

--
-- Table structure for table `wards`
--

CREATE TABLE `wards` (
  `ward_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `floor` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wards`
--

INSERT INTO `wards` (`ward_id`, `name`, `department_id`, `floor`, `is_active`) VALUES
(1, 'General Ward - East', 3, '2nd Floor', 1),
(2, 'General Ward - West', 3, '2nd Floor', 1),
(3, 'Maternity Ward', 3, '3rd Floor', 1),
(4, 'ICU', 3, '1st Floor', 1);

-- --------------------------------------------------------

--
-- Structure for view `vw_users`
--
DROP TABLE IF EXISTS `vw_users`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_users`  AS SELECT `u`.`user_id` AS `user_id`, `u`.`username` AS `username`, `u`.`email` AS `email`, `u`.`full_name` AS `full_name`, `u`.`is_active` AS `is_active`, `u`.`last_login` AS `last_login`, `u`.`created_at` AS `created_at`, `r`.`name` AS `role_name`, `r`.`role_id` AS `role_id` FROM (`users` `u` join `lookup_roles` `r` on(`u`.`role_id` = `r`.`role_id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_log_staff` (`staff_id`),
  ADD KEY `idx_log_created` (`created_at`),
  ADD KEY `idx_log_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `fk_appt_patient` (`patient_id`),
  ADD KEY `fk_appt_dept` (`department_id`),
  ADD KEY `idx_appt_scheduled` (`scheduled_at`),
  ADD KEY `idx_appt_doctor` (`doctor_id`);

--
-- Indexes for table `beds`
--
ALTER TABLE `beds`
  ADD PRIMARY KEY (`bed_id`),
  ADD UNIQUE KEY `unique_bed` (`ward_id`,`bed_number`),
  ADD KEY `fk_beds_type` (`bed_type_id`);

--
-- Indexes for table `bed_assignments`
--
ALTER TABLE `bed_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `idx_bedassign_bed` (`bed_id`),
  ADD KEY `idx_bedassign_patient` (`patient_id`),
  ADD KEY `idx_bedassign_visit` (`visit_id`);

--
-- Indexes for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `fk_schedule_doctor` (`doctor_id`),
  ADD KEY `fk_schedule_dept` (`department_id`);

--
-- Indexes for table `insurance_providers`
--
ALTER TABLE `insurance_providers`
  ADD PRIMARY KEY (`provider_id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`invoice_id`),
  ADD UNIQUE KEY `invoice_code` (`invoice_code`),
  ADD KEY `fk_invoices_patient` (`patient_id`),
  ADD KEY `fk_invoices_status` (`invoice_status_id`),
  ADD KEY `idx_invoices_visit` (`visit_id`),
  ADD KEY `idx_invoices_created` (`created_at`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`invoice_item_id`),
  ADD KEY `idx_invitems_invoice` (`invoice_id`);

--
-- Indexes for table `lab_orders`
--
ALTER TABLE `lab_orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `fk_laborders_test` (`test_type_id`),
  ADD KEY `fk_laborders_doctor` (`ordered_by`),
  ADD KEY `idx_laborders_visit` (`visit_id`),
  ADD KEY `idx_laborders_status` (`order_status_id`);

--
-- Indexes for table `lab_results`
--
ALTER TABLE `lab_results`
  ADD PRIMARY KEY (`result_id`),
  ADD KEY `fk_results_technician` (`entered_by`),
  ADD KEY `fk_results_doctor` (`reviewed_by`),
  ADD KEY `idx_results_order` (`order_id`);

--
-- Indexes for table `lookup_bed_types`
--
ALTER TABLE `lookup_bed_types`
  ADD PRIMARY KEY (`bed_type_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `lookup_departments`
--
ALTER TABLE `lookup_departments`
  ADD PRIMARY KEY (`department_id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `lookup_genders`
--
ALTER TABLE `lookup_genders`
  ADD PRIMARY KEY (`gender_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `lookup_invoice_statuses`
--
ALTER TABLE `lookup_invoice_statuses`
  ADD PRIMARY KEY (`invoice_status_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `lookup_order_statuses`
--
ALTER TABLE `lookup_order_statuses`
  ADD PRIMARY KEY (`order_status_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `lookup_payment_methods`
--
ALTER TABLE `lookup_payment_methods`
  ADD PRIMARY KEY (`payment_method_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `lookup_roles`
--
ALTER TABLE `lookup_roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `lookup_test_types`
--
ALTER TABLE `lookup_test_types`
  ADD PRIMARY KEY (`test_type_id`);

--
-- Indexes for table `lookup_visit_statuses`
--
ALTER TABLE `lookup_visit_statuses`
  ADD PRIMARY KEY (`visit_status_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `lookup_visit_types`
--
ALTER TABLE `lookup_visit_types`
  ADD PRIMARY KEY (`visit_type_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `fk_records_doctor` (`doctor_id`),
  ADD KEY `idx_records_patient` (`patient_id`),
  ADD KEY `idx_records_visit` (`visit_id`);

--
-- Indexes for table `medications`
--
ALTER TABLE `medications`
  ADD PRIMARY KEY (`medication_id`),
  ADD KEY `idx_medications_name` (`name`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notif_staff_read` (`staff_id`,`is_read`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`patient_id`),
  ADD UNIQUE KEY `patient_code` (`patient_code`),
  ADD KEY `fk_patients_gender` (`gender_id`),
  ADD KEY `idx_patients_name` (`last_name`,`first_name`),
  ADD KEY `idx_patients_phone` (`phone`),
  ADD KEY `fk_patients_doctor` (`primary_doctor_id`);

--
-- Indexes for table `patient_insurance`
--
ALTER TABLE `patient_insurance`
  ADD PRIMARY KEY (`patient_insurance_id`),
  ADD KEY `fk_pi_patient` (`patient_id`),
  ADD KEY `fk_pi_provider` (`provider_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `fk_payments_method` (`payment_method_id`),
  ADD KEY `fk_payments_staff` (`received_by`),
  ADD KEY `idx_payments_invoice` (`invoice_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`prescription_id`),
  ADD KEY `fk_presc_doctor` (`prescribed_by`),
  ADD KEY `idx_presc_visit` (`visit_id`);

--
-- Indexes for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `fk_pitems_med` (`medication_id`),
  ADD KEY `fk_pitems_staff` (`dispensed_by`),
  ADD KEY `idx_pitems_prescription` (`prescription_id`);

--
-- Indexes for table `procedures`
--
ALTER TABLE `procedures`
  ADD PRIMARY KEY (`procedure_id`),
  ADD KEY `fk_proc_visit` (`visit_id`),
  ADD KEY `fk_proc_surgeon` (`surgeon_id`),
  ADD KEY `fk_proc_assistant` (`assistant_id`),
  ADD KEY `fk_proc_anesth` (`anesthesiologist_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `staff_code` (`staff_code`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_staff_department` (`department_id`),
  ADD KEY `idx_staff_role` (`role_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_role` (`role_id`),
  ADD KEY `fk_users_staff` (`staff_id`),
  ADD KEY `idx_users_username` (`username`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_active` (`is_active`);

--
-- Indexes for table `visits`
--
ALTER TABLE `visits`
  ADD PRIMARY KEY (`visit_id`),
  ADD UNIQUE KEY `visit_code` (`visit_code`),
  ADD KEY `fk_visits_type` (`visit_type_id`),
  ADD KEY `fk_visits_dept` (`department_id`),
  ADD KEY `fk_visits_doctor` (`attending_doctor_id`),
  ADD KEY `idx_visits_patient` (`patient_id`),
  ADD KEY `idx_visits_admitted` (`admitted_at`),
  ADD KEY `idx_visits_status` (`visit_status_id`);

--
-- Indexes for table `vital_signs`
--
ALTER TABLE `vital_signs`
  ADD PRIMARY KEY (`vital_id`),
  ADD KEY `fk_vitals_staff` (`recorded_by`),
  ADD KEY `idx_vitals_visit` (`visit_id`);

--
-- Indexes for table `wards`
--
ALTER TABLE `wards`
  ADD PRIMARY KEY (`ward_id`),
  ADD KEY `fk_wards_department` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `log_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `beds`
--
ALTER TABLE `beds`
  MODIFY `bed_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `bed_assignments`
--
ALTER TABLE `bed_assignments`
  MODIFY `assignment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  MODIFY `schedule_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `insurance_providers`
--
ALTER TABLE `insurance_providers`
  MODIFY `provider_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `invoice_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `invoice_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_orders`
--
ALTER TABLE `lab_orders`
  MODIFY `order_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_results`
--
ALTER TABLE `lab_results`
  MODIFY `result_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lookup_bed_types`
--
ALTER TABLE `lookup_bed_types`
  MODIFY `bed_type_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `lookup_departments`
--
ALTER TABLE `lookup_departments`
  MODIFY `department_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `lookup_genders`
--
ALTER TABLE `lookup_genders`
  MODIFY `gender_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lookup_invoice_statuses`
--
ALTER TABLE `lookup_invoice_statuses`
  MODIFY `invoice_status_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `lookup_order_statuses`
--
ALTER TABLE `lookup_order_statuses`
  MODIFY `order_status_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `lookup_payment_methods`
--
ALTER TABLE `lookup_payment_methods`
  MODIFY `payment_method_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `lookup_roles`
--
ALTER TABLE `lookup_roles`
  MODIFY `role_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `lookup_test_types`
--
ALTER TABLE `lookup_test_types`
  MODIFY `test_type_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `lookup_visit_statuses`
--
ALTER TABLE `lookup_visit_statuses`
  MODIFY `visit_status_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `lookup_visit_types`
--
ALTER TABLE `lookup_visit_types`
  MODIFY `visit_type_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `record_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medications`
--
ALTER TABLE `medications`
  MODIFY `medication_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `patient_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `patient_insurance`
--
ALTER TABLE `patient_insurance`
  MODIFY `patient_insurance_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `prescription_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescription_items`
--
ALTER TABLE `prescription_items`
  MODIFY `item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `procedures`
--
ALTER TABLE `procedures`
  MODIFY `procedure_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `staff_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `visit_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vital_signs`
--
ALTER TABLE `vital_signs`
  MODIFY `vital_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wards`
--
ALTER TABLE `wards`
  MODIFY `ward_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `fk_log_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`);

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appt_dept` FOREIGN KEY (`department_id`) REFERENCES `lookup_departments` (`department_id`),
  ADD CONSTRAINT `fk_appt_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `staff` (`staff_id`),
  ADD CONSTRAINT `fk_appt_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `beds`
--
ALTER TABLE `beds`
  ADD CONSTRAINT `fk_beds_type` FOREIGN KEY (`bed_type_id`) REFERENCES `lookup_bed_types` (`bed_type_id`),
  ADD CONSTRAINT `fk_beds_ward` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`ward_id`);

--
-- Constraints for table `bed_assignments`
--
ALTER TABLE `bed_assignments`
  ADD CONSTRAINT `fk_bedassign_bed` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`bed_id`),
  ADD CONSTRAINT `fk_bedassign_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `fk_bedassign_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`visit_id`);

--
-- Constraints for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD CONSTRAINT `fk_schedule_dept` FOREIGN KEY (`department_id`) REFERENCES `lookup_departments` (`department_id`),
  ADD CONSTRAINT `fk_schedule_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `staff` (`staff_id`);

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_invoices_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `fk_invoices_status` FOREIGN KEY (`invoice_status_id`) REFERENCES `lookup_invoice_statuses` (`invoice_status_id`),
  ADD CONSTRAINT `fk_invoices_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`visit_id`);

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `fk_invitems_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`invoice_id`) ON DELETE CASCADE;

--
-- Constraints for table `lab_orders`
--
ALTER TABLE `lab_orders`
  ADD CONSTRAINT `fk_laborders_doctor` FOREIGN KEY (`ordered_by`) REFERENCES `staff` (`staff_id`),
  ADD CONSTRAINT `fk_laborders_status` FOREIGN KEY (`order_status_id`) REFERENCES `lookup_order_statuses` (`order_status_id`),
  ADD CONSTRAINT `fk_laborders_test` FOREIGN KEY (`test_type_id`) REFERENCES `lookup_test_types` (`test_type_id`),
  ADD CONSTRAINT `fk_laborders_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`visit_id`) ON DELETE CASCADE;

--
-- Constraints for table `lab_results`
--
ALTER TABLE `lab_results`
  ADD CONSTRAINT `fk_results_doctor` FOREIGN KEY (`reviewed_by`) REFERENCES `staff` (`staff_id`),
  ADD CONSTRAINT `fk_results_order` FOREIGN KEY (`order_id`) REFERENCES `lab_orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_results_technician` FOREIGN KEY (`entered_by`) REFERENCES `staff` (`staff_id`);

--
-- Constraints for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD CONSTRAINT `fk_records_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `staff` (`staff_id`),
  ADD CONSTRAINT `fk_records_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `fk_records_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`visit_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`);

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `fk_patients_doctor` FOREIGN KEY (`primary_doctor_id`) REFERENCES `staff` (`staff_id`),
  ADD CONSTRAINT `fk_patients_gender` FOREIGN KEY (`gender_id`) REFERENCES `lookup_genders` (`gender_id`);

--
-- Constraints for table `patient_insurance`
--
ALTER TABLE `patient_insurance`
  ADD CONSTRAINT `fk_pi_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `fk_pi_provider` FOREIGN KEY (`provider_id`) REFERENCES `insurance_providers` (`provider_id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`invoice_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_payments_method` FOREIGN KEY (`payment_method_id`) REFERENCES `lookup_payment_methods` (`payment_method_id`),
  ADD CONSTRAINT `fk_payments_staff` FOREIGN KEY (`received_by`) REFERENCES `staff` (`staff_id`);

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `fk_presc_doctor` FOREIGN KEY (`prescribed_by`) REFERENCES `staff` (`staff_id`),
  ADD CONSTRAINT `fk_presc_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`visit_id`) ON DELETE CASCADE;

--
-- Constraints for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD CONSTRAINT `fk_pitems_med` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`medication_id`),
  ADD CONSTRAINT `fk_pitems_presc` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`prescription_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pitems_staff` FOREIGN KEY (`dispensed_by`) REFERENCES `staff` (`staff_id`);

--
-- Constraints for table `procedures`
--
ALTER TABLE `procedures`
  ADD CONSTRAINT `fk_proc_anesth` FOREIGN KEY (`anesthesiologist_id`) REFERENCES `staff` (`staff_id`),
  ADD CONSTRAINT `fk_proc_assistant` FOREIGN KEY (`assistant_id`) REFERENCES `staff` (`staff_id`),
  ADD CONSTRAINT `fk_proc_surgeon` FOREIGN KEY (`surgeon_id`) REFERENCES `staff` (`staff_id`),
  ADD CONSTRAINT `fk_proc_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`visit_id`);

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `fk_staff_department` FOREIGN KEY (`department_id`) REFERENCES `lookup_departments` (`department_id`),
  ADD CONSTRAINT `fk_staff_role` FOREIGN KEY (`role_id`) REFERENCES `lookup_roles` (`role_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `lookup_roles` (`role_id`),
  ADD CONSTRAINT `fk_users_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE SET NULL;

--
-- Constraints for table `visits`
--
ALTER TABLE `visits`
  ADD CONSTRAINT `fk_visits_dept` FOREIGN KEY (`department_id`) REFERENCES `lookup_departments` (`department_id`),
  ADD CONSTRAINT `fk_visits_doctor` FOREIGN KEY (`attending_doctor_id`) REFERENCES `staff` (`staff_id`),
  ADD CONSTRAINT `fk_visits_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `fk_visits_status` FOREIGN KEY (`visit_status_id`) REFERENCES `lookup_visit_statuses` (`visit_status_id`),
  ADD CONSTRAINT `fk_visits_type` FOREIGN KEY (`visit_type_id`) REFERENCES `lookup_visit_types` (`visit_type_id`);

--
-- Constraints for table `vital_signs`
--
ALTER TABLE `vital_signs`
  ADD CONSTRAINT `fk_vitals_staff` FOREIGN KEY (`recorded_by`) REFERENCES `staff` (`staff_id`),
  ADD CONSTRAINT `fk_vitals_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`visit_id`) ON DELETE CASCADE;

--
-- Constraints for table `wards`
--
ALTER TABLE `wards`
  ADD CONSTRAINT `fk_wards_department` FOREIGN KEY (`department_id`) REFERENCES `lookup_departments` (`department_id`);

-- --------------------------------------------------------

--
-- Table structure for table `ipd_checkups`
--

CREATE TABLE `ipd_checkups` (
  `checkup_id` int(10) UNSIGNED NOT NULL,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `checkup_time` datetime NOT NULL DEFAULT current_timestamp(),
  `progress_notes` text DEFAULT NULL,
  `glucose_level` decimal(5,2) DEFAULT NULL,
  `glucose_unit` varchar(10) DEFAULT 'mg/dL',
  `glucose_type` varchar(20) DEFAULT 'Random',
  `injection_given` tinyint(1) DEFAULT 0,
  `injection_type` varchar(100) DEFAULT NULL,
  `injection_dosage` varchar(50) DEFAULT NULL,
  `medicine_given` tinyint(1) DEFAULT 0,
  `medicine_notes` text DEFAULT NULL,
  `vital_signs` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for table `ipd_checkups`
--
ALTER TABLE `ipd_checkups`
  ADD PRIMARY KEY (`checkup_id`),
  ADD KEY `idx_ipd_checkups_visit` (`visit_id`),
  ADD KEY `idx_ipd_checkups_patient` (`patient_id`),
  ADD KEY `idx_ipd_checkups_recorded` (`recorded_by`),
  ADD KEY `idx_ipd_checkups_time` (`checkup_time`);

-- --------------------------------------------------------

--
-- Table structure for table `ipd_medicine_administration`
--

CREATE TABLE `ipd_medicine_administration` (
  `administration_id` int(10) UNSIGNED NOT NULL,
  `checkup_id` int(10) UNSIGNED NOT NULL,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `medication_id` int(10) UNSIGNED NOT NULL,
  `dosage` varchar(80) DEFAULT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `administered_by` int(10) UNSIGNED NOT NULL,
  `administered_at` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for table `ipd_medicine_administration`
--
ALTER TABLE `ipd_medicine_administration`
  ADD PRIMARY KEY (`administration_id`),
  ADD KEY `idx_ipd_med_checkup` (`checkup_id`),
  ADD KEY `idx_ipd_med_visit` (`visit_id`),
  ADD KEY `idx_ipd_med_medication` (`medication_id`),
  ADD KEY `idx_ipd_med_administered` (`administered_by`);

-- --------------------------------------------------------

--
-- Table structure for table `ipd_records`
--

CREATE TABLE `ipd_records` (
  `ipd_record_id` int(10) UNSIGNED NOT NULL,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `admission_date` datetime NOT NULL DEFAULT current_timestamp(),
  `discharge_date` datetime DEFAULT NULL,
  `attending_doctor_id` int(10) UNSIGNED DEFAULT NULL,
  `bed_id` int(10) UNSIGNED DEFAULT NULL,
  `ward_id` int(10) UNSIGNED DEFAULT NULL,
  `primary_diagnosis` text DEFAULT NULL,
  `admission_notes` text DEFAULT NULL,
  `discharge_notes` text DEFAULT NULL,
  `discharged_by` int(10) UNSIGNED DEFAULT NULL,
  `status` varchar(30) DEFAULT 'Admitted'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for table `ipd_records`
--
ALTER TABLE `ipd_records`
  ADD PRIMARY KEY (`ipd_record_id`),
  ADD KEY `idx_ipd_records_visit` (`visit_id`),
  ADD KEY `idx_ipd_records_patient` (`patient_id`),
  ADD KEY `idx_ipd_records_bed` (`bed_id`),
  ADD KEY `idx_ipd_records_ward` (`ward_id`),
  ADD KEY `idx_ipd_records_doctor` (`attending_doctor_id`),
  ADD KEY `idx_ipd_records_status` (`status`);

--
-- AUTO_INCREMENT for table `ipd_checkups`
--
ALTER TABLE `ipd_checkups`
  MODIFY `checkup_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ipd_medicine_administration`
--
ALTER TABLE `ipd_medicine_administration`
  MODIFY `administration_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ipd_records`
--
ALTER TABLE `ipd_records`
  MODIFY `ipd_record_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for table `ipd_checkups`
--
ALTER TABLE `ipd_checkups`
  ADD CONSTRAINT `fk_ipd_checkups_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`visit_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ipd_checkups_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `fk_ipd_checkups_recorded` FOREIGN KEY (`recorded_by`) REFERENCES `staff` (`staff_id`);

--
-- Constraints for table `ipd_medicine_administration`
--
ALTER TABLE `ipd_medicine_administration`
  ADD CONSTRAINT `fk_ipd_med_checkup` FOREIGN KEY (`checkup_id`) REFERENCES `ipd_checkups` (`checkup_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ipd_med_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`visit_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ipd_med_medication` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`medication_id`),
  ADD CONSTRAINT `fk_ipd_med_administered` FOREIGN KEY (`administered_by`) REFERENCES `staff` (`staff_id`);

--
-- Constraints for table `ipd_records`
--
ALTER TABLE `ipd_records`
  ADD CONSTRAINT `fk_ipd_records_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`visit_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ipd_records_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `fk_ipd_records_bed` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`bed_id`),
  ADD CONSTRAINT `fk_ipd_records_ward` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`ward_id`),
  ADD CONSTRAINT `fk_ipd_records_doctor` FOREIGN KEY (`attending_doctor_id`) REFERENCES `staff` (`staff_id`);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
