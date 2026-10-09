-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 04, 2026 at 08:49 PM
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
-- Database: `dascare`
--

-- --------------------------------------------------------

--
-- Table structure for table `ambulances`
--

CREATE TABLE `ambulances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `organization_id` bigint(20) UNSIGNED NOT NULL,
  `unit_code` varchar(50) NOT NULL,
  `plate_number` varchar(30) NOT NULL,
  `vehicle_make_model` varchar(120) DEFAULT NULL,
  `model_year` smallint(5) UNSIGNED DEFAULT NULL,
  `ambulance_type` enum('basic_life_support','advanced_life_support','patient_transport','rescue_unit') NOT NULL,
  `capability_notes` text DEFAULT NULL,
  `capacity` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `registration_expiry` date DEFAULT NULL,
  `inspection_expiry` date DEFAULT NULL,
  `status` enum('available','reserved','dispatched','on_scene','transporting','returning','maintenance','offline') NOT NULL DEFAULT 'offline',
  `last_latitude` decimal(10,7) DEFAULT NULL,
  `last_longitude` decimal(10,7) DEFAULT NULL,
  `last_accuracy_m` decimal(8,2) DEFAULT NULL,
  `last_location_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ambulances`
--

INSERT INTO `ambulances` (`id`, `organization_id`, `unit_code`, `plate_number`, `vehicle_make_model`, `model_year`, `ambulance_type`, `capability_notes`, `capacity`, `registration_expiry`, `inspection_expiry`, `status`, `last_latitude`, `last_longitude`, `last_accuracy_m`, `last_location_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 2, 'ASD', 'ABC 1234', 'Van', 2023, 'basic_life_support', 'asdasdasdd', 2, '2026-10-07', '2026-10-21', 'available', NULL, NULL, NULL, NULL, '2026-10-04 17:29:40', '2026-10-04 17:30:14', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `ambulance_locations`
--

CREATE TABLE `ambulance_locations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ambulance_id` bigint(20) UNSIGNED NOT NULL,
  `emergency_request_id` bigint(20) UNSIGNED DEFAULT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `accuracy_m` decimal(8,2) DEFAULT NULL,
  `speed_kph` decimal(6,2) DEFAULT NULL,
  `heading_degrees` smallint(5) UNSIGNED DEFAULT NULL,
  `recorded_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ambulance_maintenance_records`
--

CREATE TABLE `ambulance_maintenance_records` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ambulance_id` bigint(20) UNSIGNED NOT NULL,
  `created_by_user_id` bigint(20) UNSIGNED NOT NULL,
  `maintenance_type` enum('preventive','repair','inspection','other') NOT NULL DEFAULT 'preventive',
  `status` enum('scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `scheduled_for` date DEFAULT NULL,
  `provider` varchar(160) DEFAULT NULL,
  `cost` decimal(12,2) DEFAULT NULL,
  `notes` varchar(1000) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ambulance_readiness_checks`
--

CREATE TABLE `ambulance_readiness_checks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ambulance_id` bigint(20) UNSIGNED NOT NULL,
  `checked_by_user_id` bigint(20) UNSIGNED NOT NULL,
  `overall_status` enum('ready','needs_attention','out_of_service') NOT NULL,
  `fuel_level` enum('low','adequate','full') NOT NULL DEFAULT 'adequate',
  `oxygen_ready` tinyint(1) NOT NULL DEFAULT 0,
  `medical_supplies_ready` tinyint(1) NOT NULL DEFAULT 0,
  `lights_siren_ready` tinyint(1) NOT NULL DEFAULT 0,
  `communications_ready` tinyint(1) NOT NULL DEFAULT 0,
  `stretcher_ready` tinyint(1) NOT NULL DEFAULT 0,
  `cleanliness_ready` tinyint(1) NOT NULL DEFAULT 0,
  `critical_issue` tinyint(1) NOT NULL DEFAULT 0,
  `notes` varchar(500) DEFAULT NULL,
  `checked_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ambulance_readiness_checks`
--

INSERT INTO `ambulance_readiness_checks` (`id`, `ambulance_id`, `checked_by_user_id`, `overall_status`, `fuel_level`, `oxygen_ready`, `medical_supplies_ready`, `lights_siren_ready`, `communications_ready`, `stretcher_ready`, `cleanliness_ready`, `critical_issue`, `notes`, `checked_at`) VALUES
(1, 1, 4, 'ready', 'adequate', 1, 1, 1, 1, 1, 1, 0, 's', '2026-10-05 01:30:07');

-- --------------------------------------------------------

--
-- Table structure for table `ambulance_status_logs`
--

CREATE TABLE `ambulance_status_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ambulance_id` bigint(20) UNSIGNED NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `changed_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ambulance_status_logs`
--

INSERT INTO `ambulance_status_logs` (`id`, `ambulance_id`, `old_status`, `new_status`, `changed_by_user_id`, `reason`, `created_at`) VALUES
(1, 1, NULL, 'offline', 4, 'Ambulance added to organization fleet.', '2026-10-04 17:29:40'),
(2, 1, 'offline', 'available', 4, 'Readiness confirmed.', '2026-10-04 17:30:14');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `organization_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `organization_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 3, NULL, 'kyc.approve', 'kyc_verification', 7, '{\"status\":1,\"verification_note\":null,\"verified_at\":null,\"verified_by\":null}', '{\"status\":2,\"decision\":\"approve\",\"verification_note\":null,\"reviewed_by\":3}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 15:01:09'),
(2, 3, NULL, 'citizen.account_suspend', 'citizen_account', 7, '{\"account_status\":\"active\"}', '{\"account_status\":\"suspended\",\"reason\":\"casdasd\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 16:43:11'),
(3, 3, NULL, 'citizen.account_reactivate', 'citizen_account', 7, '{\"account_status\":\"suspended\"}', '{\"account_status\":\"active\",\"reason\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 16:43:43'),
(4, 3, NULL, 'dispatch.dss_generated', 'emergency_request', 1, NULL, '{\"run_id\":1,\"candidate_count\":0,\"top_offer\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 17:10:21'),
(5, 3, NULL, 'dispatch.dss_generated', 'emergency_request', 2, NULL, '{\"run_id\":2,\"candidate_count\":0,\"top_offer\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 17:10:33'),
(6, 4, 2, 'fleet.ambulance_created', 'ambulance', 1, NULL, '{\"unit_code\":\"ASD\",\"plate_number\":\"ABC 1234\",\"ambulance_type\":\"basic_life_support\",\"vehicle_make_model\":\"Van\",\"model_year\":2023,\"capacity\":2,\"capability_notes\":\"asdasdasdd\",\"registration_expiry\":\"2026-10-07\",\"inspection_expiry\":\"2026-10-21\",\"status\":\"offline\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 17:29:40'),
(7, 4, 2, 'fleet.readiness_checked', 'ambulance_readiness_check', 1, NULL, '{\"ambulance_id\":1,\"overall_status\":\"ready\",\"fuel_level\":\"adequate\",\"checks\":{\"oxygen_ready\":1,\"medical_supplies_ready\":1,\"lights_siren_ready\":1,\"communications_ready\":1,\"stretcher_ready\":1,\"cleanliness_ready\":1},\"critical_issue\":false,\"notes\":\"s\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 17:30:07'),
(8, 4, 2, 'fleet.ambulance_status_changed', 'ambulance', 1, '{\"status\":\"offline\"}', '{\"status\":\"available\",\"reason\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 17:30:14'),
(9, 3, NULL, 'dispatch.dss_generated', 'emergency_request', 1, NULL, '{\"run_id\":3,\"candidate_count\":0,\"top_offer\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 17:30:41'),
(10, 3, NULL, 'dispatch.dss_generated', 'emergency_request', 2, NULL, '{\"run_id\":4,\"candidate_count\":0,\"top_offer\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 17:40:59'),
(11, 3, NULL, 'dispatch.dss_generated', 'emergency_request', 1, NULL, '{\"run_id\":5,\"candidate_count\":0,\"top_offer\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 18:20:26'),
(12, 4, 2, 'organization.profile_updated', 'organization', 2, '{\"email\":\"demo-rescue@dascare.test\",\"phone\":\"09170000100\",\"address_line\":\"Dasmariñas City, Cavite\",\"latitude\":null,\"longitude\":null}', '{\"email\":\"demo-rescue@dascare.test\",\"phone\":\"09170000100\",\"address_line\":\"Dasmariñas City, Cavite\",\"latitude\":14.329559061805034,\"longitude\":120.93584730818466,\"service_areas\":[\"Dasma\"]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 18:31:37'),
(13, 3, NULL, 'dispatch.dss_generated', 'emergency_request', 2, NULL, '{\"run_id\":6,\"candidate_count\":1,\"top_offer\":{\"id\":1,\"expires_at\":\"2026-10-04 20:33:12\",\"organization_id\":2}}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 18:31:42');

-- --------------------------------------------------------

--
-- Table structure for table `citizen_saved_locations`
--

CREATE TABLE `citizen_saved_locations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `label` varchar(100) NOT NULL COMMENT 'e.g. Home, Work',
  `address_text` varchar(255) NOT NULL,
  `barangay` varchar(120) DEFAULT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crew_assignments`
--

CREATE TABLE `crew_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `dispatch_assignment_id` bigint(20) UNSIGNED NOT NULL,
  `organization_member_id` bigint(20) UNSIGNED NOT NULL,
  `crew_role` enum('driver','team_leader','emt','paramedic','rescuer') NOT NULL,
  `response_status` enum('assigned','acknowledged','declined','completed') NOT NULL DEFAULT 'assigned',
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `acknowledged_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dispatch_assignments`
--

CREATE TABLE `dispatch_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `emergency_request_id` bigint(20) UNSIGNED NOT NULL,
  `incident_offer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `organization_id` bigint(20) UNSIGNED NOT NULL,
  `ambulance_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_by_user_id` bigint(20) UNSIGNED NOT NULL,
  `assignment_status` enum('recommended','assigned','acknowledged','declined','responding','on_scene','transporting','completed','cancelled','reassigned') NOT NULL DEFAULT 'assigned',
  `dss_score` decimal(6,2) DEFAULT NULL,
  `dss_explanation` varchar(500) DEFAULT NULL,
  `override_reason` varchar(500) DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `acknowledged_at` datetime DEFAULT NULL,
  `departed_at` datetime DEFAULT NULL,
  `arrived_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dispatch_assignment_logs`
--

CREATE TABLE `dispatch_assignment_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `dispatch_assignment_id` bigint(20) UNSIGNED NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `changed_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dss_recommendations`
--

CREATE TABLE `dss_recommendations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `run_id` bigint(20) UNSIGNED NOT NULL,
  `emergency_request_id` bigint(20) UNSIGNED NOT NULL,
  `organization_id` bigint(20) UNSIGNED NOT NULL,
  `recommended_ambulance_id` bigint(20) UNSIGNED DEFAULT NULL,
  `rank_position` smallint(5) UNSIGNED NOT NULL,
  `distance_km` decimal(7,2) DEFAULT NULL,
  `distance_score` decimal(6,2) NOT NULL DEFAULT 0.00,
  `availability_score` decimal(6,2) NOT NULL DEFAULT 0.00,
  `capability_score` decimal(6,2) NOT NULL DEFAULT 0.00,
  `workload_score` decimal(6,2) NOT NULL DEFAULT 0.00,
  `total_score` decimal(6,2) NOT NULL DEFAULT 0.00,
  `explanation` varchar(700) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dss_recommendations`
--

INSERT INTO `dss_recommendations` (`id`, `run_id`, `emergency_request_id`, `organization_id`, `recommended_ambulance_id`, `rank_position`, `distance_km`, `distance_score`, `availability_score`, `capability_score`, `workload_score`, `total_score`, `explanation`, `created_at`) VALUES
(1, 6, 2, 2, 1, 1, 0.45, 97.76, 75.00, 60.00, 100.00, 83.60, 'DASCARE Demo Rescue Organization is 0.4 km away with 1 ready unit; recommended unit ASD (basic life support). Active workload: 0.', '2026-10-04 18:31:42');

-- --------------------------------------------------------

--
-- Table structure for table `dss_recommendation_runs`
--

CREATE TABLE `dss_recommendation_runs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `emergency_request_id` bigint(20) UNSIGNED NOT NULL,
  `generated_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `weights_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`weights_json`)),
  `candidate_count` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `run_status` enum('active','accepted','exhausted','superseded') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dss_recommendation_runs`
--

INSERT INTO `dss_recommendation_runs` (`id`, `emergency_request_id`, `generated_by_user_id`, `weights_json`, `candidate_count`, `run_status`, `created_at`) VALUES
(1, 1, 3, '{\"distance\":0.4000000000000001,\"availability\":0.30000000000000004,\"capability\":0.20000000000000004,\"workload\":0.10000000000000002}', 0, 'superseded', '2026-10-04 17:10:21'),
(2, 2, 3, '{\"distance\":0.4000000000000001,\"availability\":0.30000000000000004,\"capability\":0.20000000000000004,\"workload\":0.10000000000000002}', 0, 'superseded', '2026-10-04 17:10:33'),
(3, 1, 3, '{\"distance\":0.4000000000000001,\"availability\":0.30000000000000004,\"capability\":0.20000000000000004,\"workload\":0.10000000000000002}', 0, 'superseded', '2026-10-04 17:30:41'),
(4, 2, 3, '{\"distance\":0.4000000000000001,\"availability\":0.30000000000000004,\"capability\":0.20000000000000004,\"workload\":0.10000000000000002}', 0, 'superseded', '2026-10-04 17:40:59'),
(5, 1, 3, '{\"distance\":0.4000000000000001,\"availability\":0.30000000000000004,\"capability\":0.20000000000000004,\"workload\":0.10000000000000002}', 0, 'exhausted', '2026-10-04 18:20:26'),
(6, 2, 3, '{\"distance\":0.4000000000000001,\"availability\":0.30000000000000004,\"capability\":0.20000000000000004,\"workload\":0.10000000000000002}', 1, 'exhausted', '2026-10-04 18:31:42');

-- --------------------------------------------------------

--
-- Table structure for table `emergency_categories`
--

CREATE TABLE `emergency_categories` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `emergency_categories`
--

INSERT INTO `emergency_categories` (`id`, `code`, `name`, `description`, `is_active`) VALUES
(1, 'medical', 'Medical Emergency', 'Sudden illness or urgent medical condition', 1),
(2, 'road_accident', 'Road Accident', 'Vehicle collision or road-related trauma', 1),
(3, 'trauma', 'Trauma or Injury', 'Serious physical injury', 1),
(4, 'maternal', 'Maternal Emergency', 'Pregnancy or childbirth emergency', 1),
(5, 'cardiac', 'Cardiac Emergency', 'Suspected cardiac arrest or severe chest pain', 1),
(6, 'fire_related', 'Fire-related Rescue', 'Injury or rescue related to fire', 1),
(7, 'disaster', 'Disaster Response', 'Flood, earthquake, collapse, or other disaster', 1),
(8, 'other', 'Other Emergency', 'Emergency not covered by another category', 1);

-- --------------------------------------------------------

--
-- Table structure for table `emergency_requests`
--

CREATE TABLE `emergency_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference_number` varchar(30) NOT NULL,
  `requester_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `source` enum('citizen_app','web','guest','phone','walk_in') NOT NULL,
  `guest_verification_flag` varchar(60) DEFAULT NULL COMMENT 'e.g. daily_threshold_exceeded, repeat_false_alarm_history — see reusables/guest_request_limit.php. NULL = no extra scrutiny needed.',
  `merged_into_request_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Set by a dispatcher confirming this is a duplicate of another request; pair with status = duplicate.',
  `request_mode` enum('instant','standard') NOT NULL DEFAULT 'instant' COMMENT 'instant = dispatch immediately; standard = scheduled incident/patient transport request',
  `scheduled_for` datetime DEFAULT NULL COMMENT 'requested date/time, only used when request_mode = standard',
  `requester_name` varchar(160) DEFAULT NULL,
  `requester_phone` varchar(30) NOT NULL,
  `emergency_category_id` smallint(5) UNSIGNED NOT NULL,
  `severity` enum('low','moderate','high','critical') NOT NULL DEFAULT 'moderate',
  `description` text NOT NULL,
  `address_text` varchar(255) NOT NULL,
  `barangay` varchar(120) DEFAULT NULL,
  `landmark` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `status` enum('submitted','validating','verified','assigned','acknowledged','responding','on_scene','transporting','completed','cancelled','rejected','duplicate','false_alarm') NOT NULL DEFAULT 'submitted',
  `attention_level` enum('normal','overdue','critical_overdue') NOT NULL DEFAULT 'normal',
  `attention_flagged_at` datetime DEFAULT NULL,
  `attention_reason` varchar(255) DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `verified_at` datetime DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `arrived_at` datetime DEFAULT NULL,
  `transport_started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `emergency_requests`
--

INSERT INTO `emergency_requests` (`id`, `reference_number`, `requester_user_id`, `created_by_user_id`, `source`, `guest_verification_flag`, `merged_into_request_id`, `request_mode`, `scheduled_for`, `requester_name`, `requester_phone`, `emergency_category_id`, `severity`, `description`, `address_text`, `barangay`, `landmark`, `latitude`, `longitude`, `status`, `attention_level`, `attention_flagged_at`, `attention_reason`, `submitted_at`, `verified_at`, `assigned_at`, `acknowledged_at`, `arrived_at`, `transport_started_at`, `completed_at`, `archived_at`, `created_at`, `updated_at`) VALUES
(1, 'DAS-2026-000001', 1, 1, 'citizen_app', NULL, NULL, 'instant', NULL, 'Macorli Dimaculangan', '09977460502', 8, 'critical', 'INSTANT RESCUE REQUEST. Submitted via one-tap instant request. Category unspecified — confirm with requester on contact.', 'Amaris Homes Dasmariñas, Burol', 'Burol', NULL, 14.3321812, 120.9429932, 'validating', 'critical_overdue', '2026-10-05 02:49:12', 'Emergency request has remained unresolved beyond the critical escalation threshold.', '2026-08-05 16:29:12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 08:29:12', '2026-10-04 18:49:12'),
(2, 'DAS-2026-000002', NULL, NULL, 'guest', NULL, NULL, 'instant', NULL, 'Maco', '09977460502', 8, 'critical', 'INSTANT RESCUE REQUEST. Submitted via one-tap instant request. Category unspecified — confirm with requester on contact.', 'Aguinaldo Highway', 'Poblacion', NULL, 14.3291485, 120.9399891, 'validating', 'critical_overdue', '2026-10-05 02:49:12', 'Emergency request has remained unresolved beyond the critical escalation threshold.', '2026-08-05 17:11:28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 09:11:28', '2026-10-04 18:49:12'),
(3, 'DAS-2026-000003', NULL, NULL, 'guest', NULL, NULL, 'standard', NULL, 'asdasd', '09123454794', 2, 'high', 'Patients involved: 1 · Conscious: Yes · Breathing: Yes\r\n\r\ndasdasdasdasdasd', 'Aguinaldo Highway', 'Fatima II', 'tindahan', 14.3359688, 120.9355030, 'submitted', 'normal', NULL, NULL, '2026-10-04 21:00:01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-04 13:00:01', '2026-10-04 13:00:02');

-- --------------------------------------------------------

--
-- Table structure for table `emergency_request_media`
--

CREATE TABLE `emergency_request_media` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `emergency_request_id` bigint(20) UNSIGNED NOT NULL,
  `uploaded_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emergency_request_medical_snapshots`
--

CREATE TABLE `emergency_request_medical_snapshots` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `emergency_request_id` bigint(20) UNSIGNED NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-','unknown') NOT NULL DEFAULT 'unknown',
  `allergies` text DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `medical_conditions` text DEFAULT NULL,
  `disabilities_mobility_notes` text DEFAULT NULL,
  `organ_donor` tinyint(1) NOT NULL DEFAULT 0,
  `emergency_contact_name` varchar(160) DEFAULT NULL,
  `emergency_contact_phone` varchar(30) DEFAULT NULL,
  `emergency_contact_relationship` varchar(80) DEFAULT NULL,
  `primary_physician_name` varchar(160) DEFAULT NULL,
  `primary_physician_phone` varchar(30) DEFAULT NULL,
  `insurance_provider` varchar(160) DEFAULT NULL,
  `insurance_policy_number` varchar(100) DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `captured_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When this snapshot was taken — i.e. medical_records.updated_at at the moment the request was filed.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emergency_request_status_logs`
--

CREATE TABLE `emergency_request_status_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `emergency_request_id` bigint(20) UNSIGNED NOT NULL,
  `old_status` varchar(40) DEFAULT NULL,
  `new_status` varchar(40) NOT NULL,
  `changed_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `emergency_request_status_logs`
--

INSERT INTO `emergency_request_status_logs` (`id`, `emergency_request_id`, `old_status`, `new_status`, `changed_by_user_id`, `notes`, `latitude`, `longitude`, `created_at`) VALUES
(1, 1, NULL, 'submitted', 1, 'Request submitted', 14.3321812, 120.9429932, '2026-08-05 08:29:12'),
(2, 2, NULL, 'submitted', NULL, 'Request submitted', 14.3291485, 120.9399891, '2026-08-05 09:11:28'),
(3, 3, NULL, 'submitted', NULL, 'Request submitted', 14.3359688, 120.9355030, '2026-10-04 13:00:02'),
(4, 1, 'submitted', 'validating', 3, 'DSS resource screening started.', NULL, NULL, '2026-10-04 17:10:21'),
(5, 2, 'submitted', 'validating', 3, 'DSS resource screening started.', NULL, NULL, '2026-10-04 17:10:33');

-- --------------------------------------------------------

--
-- Table structure for table `incident_offers`
--

CREATE TABLE `incident_offers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `emergency_request_id` bigint(20) UNSIGNED NOT NULL,
  `dss_run_id` bigint(20) UNSIGNED NOT NULL,
  `recommendation_id` bigint(20) UNSIGNED NOT NULL,
  `organization_id` bigint(20) UNSIGNED NOT NULL,
  `offer_status` enum('sent','accepted','declined','timed_out','cancelled') NOT NULL DEFAULT 'sent',
  `offered_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `responded_at` datetime DEFAULT NULL,
  `responded_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `response_note` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `incident_offers`
--

INSERT INTO `incident_offers` (`id`, `emergency_request_id`, `dss_run_id`, `recommendation_id`, `organization_id`, `offer_status`, `offered_at`, `expires_at`, `responded_at`, `responded_by_user_id`, `response_note`, `created_at`, `updated_at`) VALUES
(1, 2, 6, 1, 2, 'timed_out', '2026-10-05 02:31:42', '2026-10-04 20:33:12', '2026-10-05 02:31:42', NULL, 'Offer window expired.', '2026-10-04 18:31:42', '2026-10-04 18:31:42');

-- --------------------------------------------------------

--
-- Table structure for table `incident_reports`
--

CREATE TABLE `incident_reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `emergency_request_id` bigint(20) UNSIGNED NOT NULL,
  `organization_id` bigint(20) UNSIGNED NOT NULL,
  `prepared_by_user_id` bigint(20) UNSIGNED NOT NULL,
  `destination_facility_id` bigint(20) UNSIGNED DEFAULT NULL,
  `incident_summary` text NOT NULL,
  `actions_taken` text NOT NULL,
  `outcome` enum('treated_on_scene','transported','refused_transport','no_patient_found','deceased','other') NOT NULL,
  `departure_scene_at` datetime DEFAULT NULL,
  `arrival_facility_at` datetime DEFAULT NULL,
  `report_status` enum('draft','submitted','reviewed','locked') NOT NULL DEFAULT 'draft',
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kyc_verifications`
--

CREATE TABLE `kyc_verifications` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=unverified,1=pending,2=approved,3=rejected,4=resubmission_requested',
  `id_image` varchar(255) DEFAULT NULL,
  `id_type` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `barangay` varchar(120) DEFAULT NULL,
  `city` varchar(100) NOT NULL DEFAULT 'Dasmariñas',
  `province` varchar(100) NOT NULL DEFAULT 'Cavite',
  `zip_code` varchar(4) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `verification_note` text DEFAULT NULL,
  `rejection_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `last_rejected_at` datetime DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `verified_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kyc_verifications`
--

INSERT INTO `kyc_verifications` (`user_id`, `status`, `id_image`, `id_type`, `phone_number`, `birthdate`, `address`, `barangay`, `city`, `province`, `zip_code`, `latitude`, `longitude`, `submitted_at`, `verification_note`, `rejection_count`, `last_rejected_at`, `verified_at`, `verified_by`, `created_at`, `updated_at`) VALUES
(1, 2, 'id_1_412f8e3fba2f97ea76e861bc1ab6a393.png', 'Passport', '09977460502', '2010-07-14', 'Milk Maid Street', 'Paliparan I', 'Dasmariñas', 'Cavite', '4115', 14.33679649, 120.95745663, '2026-08-05 16:15:49', NULL, 0, NULL, NULL, NULL, '2026-08-05 08:15:49', '2026-10-04 14:06:57'),
(6, 2, NULL, NULL, '09170000005', NULL, 'Development test address', 'Burol Main', 'Dasmariñas', 'Cavite', '4114', NULL, NULL, '2026-08-05 21:33:38', 'Automatically approved development account.', 0, NULL, '2026-08-05 21:33:38', 3, '2026-08-05 13:33:38', NULL),
(7, 2, 'id_7_75cb99ea94100b7b1986239e30717a72.jpg', 'Passport', '09123456789', '2006-06-14', 'blk 22, lot 12', 'Paliparan II', 'Dasmariñas', 'Cavite', '4114', 14.31860000, 120.96604330, '2026-10-04 21:11:26', NULL, 0, NULL, '2026-10-04 23:01:09', 3, '2026-10-04 13:11:26', '2026-10-04 15:01:09');

-- --------------------------------------------------------

--
-- Table structure for table `medical_facilities`
--

CREATE TABLE `medical_facilities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `organization_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'set when this facility is claimed by a registered hospital organization',
  `name` varchar(180) NOT NULL,
  `facility_type` enum('hospital','clinic','trauma_center','maternity','other') NOT NULL,
  `address_text` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `capabilities` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_facilities`
--

INSERT INTO `medical_facilities` (`id`, `organization_id`, `name`, `facility_type`, `address_text`, `phone`, `latitude`, `longitude`, `capabilities`, `status`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Development General Hospital', 'hospital', 'Dasmariñas, Cavite', NULL, 14.3294000, 120.9367000, 'General emergency care', 'active', '2026-07-29 13:53:12', '2026-07-29 13:53:12');

-- --------------------------------------------------------

--
-- Table structure for table `medical_records`
--

CREATE TABLE `medical_records` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-','unknown') NOT NULL DEFAULT 'unknown',
  `allergies` text DEFAULT NULL COMMENT 'Free text, e.g. Penicillin, peanuts',
  `medications` text DEFAULT NULL COMMENT 'Current medications the citizen is on',
  `medical_conditions` text DEFAULT NULL COMMENT 'Chronic conditions, e.g. asthma, diabetes, epilepsy',
  `disabilities_mobility_notes` text DEFAULT NULL COMMENT 'Mobility aids, sensory/communication needs responders should know on arrival',
  `organ_donor` tinyint(1) NOT NULL DEFAULT 0,
  `emergency_contact_name` varchar(160) DEFAULT NULL,
  `emergency_contact_phone` varchar(30) DEFAULT NULL,
  `emergency_contact_relationship` varchar(80) DEFAULT NULL,
  `primary_physician_name` varchar(160) DEFAULT NULL,
  `primary_physician_phone` varchar(30) DEFAULT NULL,
  `insurance_provider` varchar(160) DEFAULT NULL,
  `insurance_policy_number` varchar(100) DEFAULT NULL,
  `additional_notes` text DEFAULT NULL COMMENT 'Anything else responders should know (DNR, recent surgery, pregnancy, etc.)',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_records`
--

INSERT INTO `medical_records` (`user_id`, `blood_type`, `allergies`, `medications`, `medical_conditions`, `disabilities_mobility_notes`, `organ_donor`, `emergency_contact_name`, `emergency_contact_phone`, `emergency_contact_relationship`, `primary_physician_name`, `primary_physician_phone`, `insurance_provider`, `insurance_policy_number`, `additional_notes`, `updated_at`, `created_at`) VALUES
(1, 'A+', 'allergic sa ket ano', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-06 07:50:24', '2026-08-06 07:50:24');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `notification_type` varchar(80) NOT NULL,
  `title` varchar(180) NOT NULL,
  `message` varchar(500) NOT NULL,
  `related_type` varchar(80) DEFAULT NULL,
  `related_id` bigint(20) UNSIGNED DEFAULT NULL,
  `dedup_key` varchar(190) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `notification_type`, `title`, `message`, `related_type`, `related_id`, `dedup_key`, `read_at`, `created_at`) VALUES
(1, 7, 'kyc_approved', 'Identity Verification Approved', 'Your DASCARE identity verification has been approved.', 'kyc_verification', 7, 'kyc_review_7_a447f6d85a27a2091ff836aba635c28a', '2026-10-04 23:01:36', '2026-10-04 15:01:09'),
(2, 7, 'citizen_account_suspended', 'Account Suspended', 'Your DASCARE account has been suspended following an administrative review. You may still request emergency assistance as a guest. Review note: casdasd', 'citizen_account', 7, 'citizen_account_status_7_suspended_202610041843', '2026-10-05 00:44:38', '2026-10-04 16:43:11'),
(3, 7, 'citizen_account_reactivated', 'Account Reactivated', 'Your DASCARE account has been reactivated. You may sign in again using your existing credentials.', 'citizen_account', 7, 'citizen_account_status_7_active_202610041843', '2026-10-05 00:44:38', '2026-10-04 16:43:43'),
(4, 4, 'incident_offer', 'New Incident Offer', 'DAS-2026-000002 • Critical emergency in Poblacion. Respond within 90 seconds.', 'incident_offer', 1, 'incident_offer:1:user:4', NULL, '2026-10-04 18:31:42'),
(5, 5, 'incident_offer', 'New Incident Offer', 'DAS-2026-000002 • Critical emergency in Poblacion. Respond within 90 seconds.', 'incident_offer', 1, 'incident_offer:1:user:5', NULL, '2026-10-04 18:31:42');

-- --------------------------------------------------------

--
-- Table structure for table `organizations`
--

CREATE TABLE `organizations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `application_reference` varchar(32) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `organization_type` enum('city_rescue','barangay_rescue','hospital','private_ambulance','other') NOT NULL,
  `registration_number` varchar(100) DEFAULT NULL COMMENT 'LGU business permit / DTI-SEC registration number',
  `accreditation_body` varchar(150) DEFAULT NULL COMMENT 'e.g. LTFRB, DOH, City Government of Dasmarinas',
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address_line` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `status` enum('pending','active','suspended','inactive') NOT NULL DEFAULT 'pending',
  `application_status` enum('pending','revision_requested','approved','rejected') NOT NULL DEFAULT 'pending',
  `primary_admin_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `application_submitted_at` datetime DEFAULT NULL,
  `application_reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `application_reviewed_at` datetime DEFAULT NULL,
  `verification_note` text DEFAULT NULL,
  `rejection_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `last_rejected_at` datetime DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `verified_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'users.id of the platform_executive_admin who approved this org',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organizations`
--

INSERT INTO `organizations` (`id`, `application_reference`, `name`, `organization_type`, `registration_number`, `accreditation_body`, `email`, `phone`, `address_line`, `latitude`, `longitude`, `status`, `application_status`, `primary_admin_user_id`, `application_submitted_at`, `application_reviewed_by`, `application_reviewed_at`, `verification_note`, `rejection_count`, `last_rejected_at`, `verified_at`, `verified_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, NULL, 'Dasmariñas City Rescue - Development Seed', 'city_rescue', NULL, NULL, 'rescue@example.test', '09170000000', 'City of Dasmariñas, Cavite', NULL, NULL, 'active', 'approved', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, '2026-07-29 13:53:12', '2026-10-04 15:18:42', NULL),
(2, NULL, 'DASCARE Demo Rescue Organization', 'city_rescue', 'DEMO-ORG-001', 'City Government of Dasmariñas', 'demo-rescue@dascare.test', '09170000100', 'Dasmariñas City, Cavite', 14.3295591, 120.9358473, 'active', 'approved', NULL, NULL, 3, '2026-08-05 21:33:38', 'Development organization created by the demo-account seed.', 0, NULL, '2026-08-05 21:33:38', 3, '2026-08-05 13:33:38', '2026-10-04 18:31:37', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `organization_documents`
--

CREATE TABLE `organization_documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `organization_id` bigint(20) UNSIGNED NOT NULL,
  `doc_type` varchar(100) NOT NULL COMMENT 'e.g. business_permit, lto_franchise, doh_license, barangay_clearance',
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'platform_executive_admin users.id',
  `reviewed_at` datetime DEFAULT NULL,
  `uploaded_by_user_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'organization_admin users.id who uploaded it',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `organization_invitations`
--

CREATE TABLE `organization_invitations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `organization_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `invited_by_user_id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `employee_code` varchar(50) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `certification_details` text DEFAULT NULL,
  `token_hash` char(64) NOT NULL,
  `status` enum('pending','accepted','cancelled','expired') NOT NULL DEFAULT 'pending',
  `expires_at` datetime NOT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `organization_members`
--

CREATE TABLE `organization_members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `organization_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `employee_code` varchar(50) DEFAULT NULL,
  `is_default_password` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'forces password change on first login',
  `invited_by_user_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'organization_admin (or operational user with rbac:members:create) who added this member',
  `membership_status` enum('invited','active','inactive','terminated') NOT NULL DEFAULT 'active',
  `joined_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organization_members`
--

INSERT INTO `organization_members` (`id`, `organization_id`, `user_id`, `employee_code`, `is_default_password`, `invited_by_user_id`, `membership_status`, `joined_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 2, 4, 'ORG-ADMIN-001', 0, 3, 'active', '2026-08-05', '2026-08-05 13:33:38', '2026-08-05 13:33:38', NULL),
(2, 2, 5, 'DISPATCH-001', 0, 4, 'active', '2026-08-05', '2026-08-05 13:33:38', '2026-08-05 13:33:38', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `organization_service_areas`
--

CREATE TABLE `organization_service_areas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `organization_id` bigint(20) UNSIGNED NOT NULL,
  `barangay` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organization_service_areas`
--

INSERT INTO `organization_service_areas` (`id`, `organization_id`, `barangay`, `created_at`) VALUES
(1, 2, 'Dasma', '2026-10-04 18:31:37');

-- --------------------------------------------------------

--
-- Table structure for table `org_member_roles`
--

CREATE TABLE `org_member_roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `organization_member_id` bigint(20) UNSIGNED NOT NULL COMMENT 'organization_members.id (the staff member) being granted this role',
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'users.id who made the assignment (organization_admin or an operational user with rbac:roles:update)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `org_member_roles`
--

INSERT INTO `org_member_roles` (`id`, `organization_member_id`, `role_id`, `assigned_by`, `created_at`) VALUES
(1, 2, 1, 4, '2026-08-05 13:33:38');

-- --------------------------------------------------------

--
-- Table structure for table `org_roles`
--

CREATE TABLE `org_roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `organization_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Tenant (rescue/ambulance org) this role belongs to',
  `role_name` varchar(100) NOT NULL COMMENT 'e.g. Dispatcher, Driver, Medic/EMT, Rescuer, Fleet Manager, Hospital Staff',
  `description` text DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'reserved for future starter-role templates',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'users.id of the organization_admin who created it',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `org_roles`
--

INSERT INTO `org_roles` (`id`, `organization_id`, `role_name`, `description`, `is_system`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 2, 'Dispatcher', 'Receives incident offers, reviews incidents, and assigns resources.', 1, 4, '2026-08-05 13:33:38', '2026-08-05 13:33:38', NULL),
(2, 1, 'Dispatcher', 'Receives incident offers, reviews incidents, and coordinates resource assignment.', 1, NULL, '2026-10-04 16:16:52', '2026-10-04 16:16:52', NULL),
(3, 1, 'Driver', 'Operates assigned ambulances, follows mission status, and shares field location updates.', 1, NULL, '2026-10-04 16:16:52', '2026-10-04 16:16:52', NULL),
(4, 2, 'Driver', 'Operates assigned ambulances, follows mission status, and shares field location updates.', 1, NULL, '2026-10-04 16:16:52', '2026-10-04 16:16:52', NULL),
(5, 1, 'Medic / EMT', 'Handles patient assessment, field care records, endorsement, and handoff coordination.', 1, NULL, '2026-10-04 16:16:52', '2026-10-04 16:16:52', NULL),
(6, 2, 'Medic / EMT', 'Handles patient assessment, field care records, endorsement, and handoff coordination.', 1, NULL, '2026-10-04 16:16:52', '2026-10-04 16:16:52', NULL),
(7, 1, 'Rescuer', 'Supports field response, incident documentation, and operational mission tasks.', 1, NULL, '2026-10-04 16:16:52', '2026-10-04 16:16:52', NULL),
(8, 2, 'Rescuer', 'Supports field response, incident documentation, and operational mission tasks.', 1, NULL, '2026-10-04 16:16:52', '2026-10-04 16:16:52', NULL),
(9, 1, 'Fleet Manager', 'Maintains ambulance readiness, maintenance records, and fleet availability.', 1, NULL, '2026-10-04 16:16:52', '2026-10-04 16:16:52', NULL),
(10, 2, 'Fleet Manager', 'Maintains ambulance readiness, maintenance records, and fleet availability.', 1, NULL, '2026-10-04 16:16:52', '2026-10-04 16:16:52', NULL),
(11, 1, 'Supervisor', 'Oversees organization operations, personnel readiness, incidents, and reports.', 1, NULL, '2026-10-04 16:16:52', '2026-10-04 16:16:52', NULL),
(12, 2, 'Supervisor', 'Oversees organization operations, personnel readiness, incidents, and reports.', 1, NULL, '2026-10-04 16:16:52', '2026-10-04 16:16:52', NULL);

--
-- Triggers `org_roles`
--
DELIMITER $$
CREATE TRIGGER `trg_org_roles_active_org_only` BEFORE INSERT ON `org_roles` FOR EACH ROW BEGIN
    DECLARE v_status ENUM('pending','active','suspended','inactive');

    SELECT `status` INTO v_status
    FROM `organizations`
    WHERE `id` = NEW.`organization_id`;

    IF v_status IS NULL OR v_status <> 'active' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Roles can only be created for approved (active) organizations.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `org_role_permissions`
--

CREATE TABLE `org_role_permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `org_role_permissions`
--

INSERT INTO `org_role_permissions` (`id`, `role_id`, `permission_id`) VALUES
(4, 1, 6),
(5, 1, 7),
(6, 1, 8),
(1, 1, 9),
(2, 1, 10),
(3, 1, 11),
(7, 1, 12),
(8, 1, 13),
(9, 1, 14),
(10, 1, 15),
(11, 1, 16),
(820, 1, 33),
(12, 1, 36),
(759, 1, 46),
(774, 1, 47),
(819, 1, 49),
(821, 1, 50),
(17, 2, 6),
(18, 2, 7),
(19, 2, 8),
(14, 2, 9),
(15, 2, 10),
(16, 2, 11),
(20, 2, 12),
(21, 2, 13),
(823, 2, 33),
(13, 2, 36),
(760, 2, 46),
(775, 2, 47),
(822, 2, 49),
(824, 2, 50),
(29, 3, 6),
(28, 3, 10),
(31, 3, 12),
(30, 3, 25),
(761, 3, 46),
(776, 3, 47),
(789, 3, 48),
(33, 4, 6),
(32, 4, 10),
(35, 4, 12),
(34, 4, 25),
(762, 4, 46),
(777, 4, 47),
(790, 4, 48),
(44, 5, 6),
(43, 5, 10),
(47, 5, 12),
(48, 5, 13),
(49, 5, 14),
(50, 5, 15),
(51, 5, 16),
(52, 5, 18),
(53, 5, 19),
(54, 5, 20),
(46, 5, 25),
(45, 5, 33),
(806, 5, 34),
(763, 5, 46),
(778, 5, 47),
(791, 5, 48),
(804, 5, 49),
(805, 5, 50),
(56, 6, 6),
(55, 6, 10),
(59, 6, 12),
(60, 6, 13),
(61, 6, 14),
(62, 6, 15),
(63, 6, 16),
(64, 6, 18),
(65, 6, 19),
(66, 6, 20),
(58, 6, 25),
(57, 6, 33),
(809, 6, 34),
(764, 6, 46),
(779, 6, 47),
(792, 6, 48),
(807, 6, 49),
(808, 6, 50),
(75, 7, 6),
(74, 7, 10),
(77, 7, 12),
(78, 7, 13),
(79, 7, 14),
(80, 7, 15),
(81, 7, 16),
(76, 7, 25),
(765, 7, 46),
(780, 7, 47),
(793, 7, 48),
(83, 8, 6),
(82, 8, 10),
(85, 8, 12),
(86, 8, 13),
(87, 8, 14),
(88, 8, 15),
(89, 8, 16),
(84, 8, 25),
(766, 8, 46),
(781, 8, 47),
(794, 8, 48),
(105, 9, 1),
(106, 9, 2),
(107, 9, 3),
(108, 9, 4),
(109, 9, 5),
(110, 9, 22),
(436, 9, 41),
(437, 9, 42),
(438, 9, 43),
(439, 9, 44),
(440, 9, 45),
(111, 10, 1),
(112, 10, 2),
(113, 10, 3),
(114, 10, 4),
(115, 10, 5),
(116, 10, 22),
(441, 10, 41),
(442, 10, 42),
(443, 10, 43),
(444, 10, 44),
(445, 10, 45),
(127, 11, 1),
(128, 11, 2),
(129, 11, 3),
(130, 11, 4),
(131, 11, 5),
(124, 11, 6),
(125, 11, 7),
(126, 11, 8),
(121, 11, 9),
(122, 11, 10),
(123, 11, 11),
(140, 11, 12),
(141, 11, 13),
(142, 11, 14),
(143, 11, 15),
(144, 11, 16),
(145, 11, 17),
(146, 11, 18),
(147, 11, 19),
(148, 11, 20),
(136, 11, 21),
(137, 11, 22),
(138, 11, 23),
(139, 11, 24),
(135, 11, 25),
(133, 11, 33),
(134, 11, 34),
(132, 11, 35),
(120, 11, 36),
(150, 11, 37),
(151, 11, 38),
(149, 11, 40),
(446, 11, 41),
(447, 11, 42),
(448, 11, 43),
(449, 11, 44),
(450, 11, 45),
(767, 11, 46),
(782, 11, 47),
(795, 11, 48),
(810, 11, 49),
(811, 11, 50),
(159, 12, 1),
(160, 12, 2),
(161, 12, 3),
(162, 12, 4),
(163, 12, 5),
(156, 12, 6),
(157, 12, 7),
(158, 12, 8),
(153, 12, 9),
(154, 12, 10),
(155, 12, 11),
(172, 12, 12),
(173, 12, 13),
(174, 12, 14),
(175, 12, 15),
(176, 12, 16),
(177, 12, 17),
(178, 12, 18),
(179, 12, 19),
(180, 12, 20),
(168, 12, 21),
(169, 12, 22),
(170, 12, 23),
(171, 12, 24),
(167, 12, 25),
(165, 12, 33),
(166, 12, 34),
(164, 12, 35),
(152, 12, 36),
(182, 12, 37),
(183, 12, 38),
(181, 12, 40),
(451, 12, 41),
(452, 12, 42),
(453, 12, 43),
(454, 12, 44),
(455, 12, 45),
(768, 12, 46),
(783, 12, 47),
(796, 12, 48),
(812, 12, 49),
(813, 12, 50);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_assessments`
--

CREATE TABLE `patient_assessments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `emergency_request_id` bigint(20) UNSIGNED NOT NULL,
  `recorded_by_user_id` bigint(20) UNSIGNED NOT NULL,
  `patient_name` varchar(160) DEFAULT NULL,
  `approximate_age` tinyint(3) UNSIGNED DEFAULT NULL,
  `sex` enum('male','female','unknown') NOT NULL DEFAULT 'unknown',
  `consciousness` enum('alert','responsive_to_voice','responsive_to_pain','unresponsive','unknown') NOT NULL DEFAULT 'unknown',
  `breathing_status` varchar(120) DEFAULT NULL,
  `condition_summary` text NOT NULL,
  `injuries` text DEFAULT NULL,
  `vital_signs_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`vital_signs_json`)),
  `assessed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_handoffs`
--

CREATE TABLE `patient_handoffs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `emergency_request_id` bigint(20) UNSIGNED NOT NULL,
  `dispatch_assignment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `medical_facility_id` bigint(20) UNSIGNED NOT NULL,
  `initiated_by_user_id` bigint(20) UNSIGNED NOT NULL COMMENT 'ambulance crew/dispatcher requesting the handoff',
  `status` enum('pending','accepted','declined','completed') NOT NULL DEFAULT 'pending',
  `eta_minutes` smallint(5) UNSIGNED DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `responded_by_user_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'hospital_staff (organization_operational_user) who accepted/declined',
  `responded_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Limited hospital endorsement/handoff -- not full hospital records or bed management.';

-- --------------------------------------------------------

--
-- Table structure for table `personnel_availability_logs`
--

CREATE TABLE `personnel_availability_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `organization_member_id` bigint(20) UNSIGNED NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `changed_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personnel_profiles`
--

CREATE TABLE `personnel_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `organization_member_id` bigint(20) UNSIGNED NOT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `certification_details` text DEFAULT NULL,
  `availability_status` enum('available','assigned','off_duty','leave','unavailable') NOT NULL DEFAULT 'off_duty',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `platform_account_roles`
--

CREATE TABLE `platform_account_roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT 'must be a technical_super_admin or platform_executive_admin in user_roles',
  `role_id` int(10) UNSIGNED NOT NULL,
  `assigned_by` bigint(20) UNSIGNED DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `platform_account_roles`
--

INSERT INTO `platform_account_roles` (`id`, `user_id`, `role_id`, `assigned_by`, `assigned_at`) VALUES
(1, 2, 1, 2, '2026-08-05 13:33:38'),
(2, 3, 2, 2, '2026-08-05 13:33:38');

-- --------------------------------------------------------

--
-- Table structure for table `platform_modules`
--

CREATE TABLE `platform_modules` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `module_key` varchar(60) NOT NULL COMMENT 'Slug used in code, e.g. "organizations", "audit"',
  `module_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `platform_modules`
--

INSERT INTO `platform_modules` (`id`, `module_key`, `module_name`, `is_active`, `sort_order`) VALUES
(1, 'organizations', 'Organization Approvals', 1, 1),
(2, 'citizens', 'Citizen Accounts', 1, 2),
(3, 'oversight', 'Incident Oversight', 1, 3),
(4, 'analytics', 'Analytics & Heatmaps', 1, 4),
(5, 'system', 'System Configuration', 1, 5),
(6, 'audit', 'Audit & Logs', 1, 6);

-- --------------------------------------------------------

--
-- Table structure for table `platform_permissions`
--

CREATE TABLE `platform_permissions` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `module_id` tinyint(3) UNSIGNED NOT NULL,
  `resource` varchar(80) NOT NULL COMMENT 'e.g. "organizations", "citizen_accounts"',
  `action` enum('create','read','update','delete','approve','export') NOT NULL,
  `label` varchar(120) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `platform_permissions`
--

INSERT INTO `platform_permissions` (`id`, `module_id`, `resource`, `action`, `label`, `created_at`) VALUES
(1, 1, 'organizations', 'read', 'View organization applications', '2026-08-05 07:04:38'),
(2, 1, 'organizations', 'approve', 'Approve / reject organization applications', '2026-08-05 07:04:38'),
(3, 1, 'organizations', 'update', 'Edit organization records', '2026-08-05 07:04:38'),
(4, 1, 'org_documents', 'read', 'View organization compliance documents', '2026-08-05 07:04:38'),
(5, 1, 'org_documents', 'approve', 'Approve / reject organization compliance documents', '2026-08-05 07:04:38'),
(6, 2, 'citizens', 'read', 'View citizen accounts', '2026-08-05 07:04:38'),
(7, 2, 'citizens', 'update', 'Suspend / restore citizen accounts', '2026-08-05 07:04:38'),
(8, 3, 'emergency_requests', 'read', 'View all emergency & standard requests', '2026-08-05 07:04:38'),
(9, 3, 'emergency_requests', 'update', 'Intervene on a stalled or disputed request', '2026-08-05 07:04:38'),
(10, 4, 'reports', 'read', 'View platform-wide analytics & heatmaps', '2026-08-05 07:04:38'),
(11, 4, 'reports', 'export', 'Export platform-wide analytics', '2026-08-05 07:04:38'),
(12, 5, 'system_settings', 'read', 'View system settings (DSS weights, intervals, etc.)', '2026-08-05 07:04:38'),
(13, 5, 'system_settings', 'update', 'Edit system settings', '2026-08-05 07:04:38'),
(14, 6, 'audit_log', 'read', 'View platform audit log', '2026-08-05 07:04:38'),
(15, 2, 'kyc_verifications', 'read', 'View citizen KYC verification submissions', '2026-10-04 14:59:53'),
(16, 2, 'kyc_verifications', 'approve', 'Approve, reject, or request KYC resubmission', '2026-10-04 14:59:53');

-- --------------------------------------------------------

--
-- Table structure for table `platform_roles`
--

CREATE TABLE `platform_roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = built-in, cannot be edited/deleted',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `platform_roles`
--

INSERT INTO `platform_roles` (`id`, `role_name`, `description`, `is_system`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Technical Super Admin', 'Full, unrestricted access for maintaining the system, infrastructure, and configuration. Cannot be modified.', 1, NULL, '2026-08-05 07:04:38', '2026-08-05 07:04:38', NULL),
(2, 'Platform Executive Admin', 'Reviews and approves organization registrations and compliance documents, and has platform-wide oversight of requests and analytics.', 1, NULL, '2026-08-05 07:04:38', '2026-08-05 07:04:38', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `platform_role_permissions`
--

CREATE TABLE `platform_role_permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `permission_id` smallint(5) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `platform_role_permissions`
--

INSERT INTO `platform_role_permissions` (`id`, `role_id`, `permission_id`) VALUES
(1, 2, 1),
(2, 2, 2),
(3, 2, 3),
(4, 2, 4),
(5, 2, 5),
(6, 2, 6),
(7, 2, 7),
(8, 2, 8),
(9, 2, 9),
(10, 2, 10),
(12, 2, 12),
(13, 2, 13),
(11, 2, 14),
(14, 2, 15),
(15, 2, 16);

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` int(10) UNSIGNED NOT NULL,
  `action_key` varchar(80) NOT NULL,
  `identifier_hash` char(64) NOT NULL,
  `attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rate_limits`
--

INSERT INTO `rate_limits` (`id`, `action_key`, `identifier_hash`, `attempts`, `expires_at`) VALUES
(134, 'kyc_submit', '56f545f17c03f890418bb20aa2b56599e89412219f711977003918019d231f1a', 1, '2026-08-05 10:25:49'),
(135, 'emergency_request', '1e9488e8658dcea9730c3ed9b0b8b5a808b8f0fe5e45c8c65b06cb9f1b16f1a8', 1, '2026-10-04 15:30:02'),
(136, 'emergency_request', '08dbdbb2a1c6d6f07f3ed1826f7751af1b278aa2b89053020dc9d722ddfed656', 1, '2026-08-05 10:59:12'),
(138, 'guest_emergency_request_phone', 'dd75d7c9d5ce64aa77cf40bc94f0e8853dfb7094950ab2d62ba6fe5943332eda', 1, '2026-08-06 11:11:28'),
(139, 'guest_emergency_request_ip', '7b986717eae59d7a9884ae7faca0228fad2e27c8193572030def1f902653a5c8', 1, '2026-10-05 15:00:02'),
(143, 'login', '736b770da2f02156af8b8522d5e3ab1ae1107ae31e3717118318f189a970fdeb', 6, '2026-08-06 08:23:44'),
(157, 'login', 'fb30493a595d30510d9c081624151bd7dfeca6a5fa65d1b0e676f130cecdbd5f', 2, '2026-08-06 08:35:40'),
(161, 'guest_emergency_request_phone', '9c6cf0ea715cbbf8368212098976ec816aa85bb7f50b198209040f7ef418a6cf', 1, '2026-10-05 15:00:02'),
(167, 'register', 'bc72b9d40d196ab801988b0ff199144920156c714a4a6ccc5cacad4e056d3ada', 1, '2026-10-04 16:09:05'),
(169, 'login', 'e0a0cca70360fe94cd9f4747a62f2641b3956dcf6e8c75612159ffdffe24dcf1', 2, '2026-10-04 15:24:57'),
(172, 'kyc_submit', '750d36468122a58573045054fd73207b8735a0de3e75b8087d5f940730d80746', 1, '2026-10-04 15:21:26');

-- --------------------------------------------------------

--
-- Table structure for table `rbac_audit_log`
--

CREATE TABLE `rbac_audit_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `organization_id` bigint(20) UNSIGNED NOT NULL,
  `table_name` varchar(64) NOT NULL COMMENT 'e.g. organization_members, org_member_roles, org_roles, org_role_permissions',
  `record_id` bigint(20) UNSIGNED NOT NULL,
  `field_name` varchar(100) DEFAULT NULL,
  `action` enum('create','update','delete','assign','revoke') NOT NULL DEFAULT 'update',
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `changed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='CRUD audit for the org-level rbac module (staff accounts, roles, permission assignments).';

-- --------------------------------------------------------

--
-- Table structure for table `rbac_modules`
--

CREATE TABLE `rbac_modules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `module_key` varchar(50) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rbac_modules`
--

INSERT INTO `rbac_modules` (`id`, `module_key`, `module_name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'fleet', 'Fleet', 'Ambulances, vehicle status, and maintenance', 1, '2026-08-05 07:04:38', '2026-08-05 07:04:38'),
(2, 'dispatch', 'Dispatch', 'Accepting DSS recommendations, assigning ambulance & crew', 1, '2026-08-05 07:04:38', '2026-08-05 07:04:38'),
(3, 'incidents', 'Incidents', 'Emergency/standard requests, incident reports, patient assessments', 1, '2026-08-05 07:04:38', '2026-08-05 07:04:38'),
(4, 'hr', 'Staff & Personnel', 'Organization members, certifications, and duty availability', 1, '2026-08-05 07:04:38', '2026-08-05 07:04:38'),
(5, 'rbac', 'Roles & Permissions', 'Manage staff accounts, roles, and permissions for this organization', 1, '2026-08-05 07:04:38', '2026-08-05 07:04:38'),
(6, 'hospital', 'Hospital Handoff', 'Facility profile and incoming patient handoff/endorsement', 1, '2026-08-05 07:04:38', '2026-08-05 07:04:38'),
(7, 'analytics', 'Analytics', 'Organization-level performance analytics', 1, '2026-08-05 07:04:38', '2026-08-05 07:04:38'),
(8, 'org_settings', 'Organization Settings', 'Organization profile and compliance documents', 1, '2026-08-05 07:04:38', '2026-08-05 07:04:38');

-- --------------------------------------------------------

--
-- Table structure for table `rbac_permissions`
--

CREATE TABLE `rbac_permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `module_id` bigint(20) UNSIGNED NOT NULL,
  `resource` varchar(100) NOT NULL COMMENT 'e.g. "ambulances", "dispatch_assignments"',
  `action` enum('create','read','update','delete','approve') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rbac_permissions`
--

INSERT INTO `rbac_permissions` (`id`, `module_id`, `resource`, `action`, `created_at`) VALUES
(1, 1, 'ambulances', 'create', '2026-08-05 07:04:38'),
(2, 1, 'ambulances', 'read', '2026-08-05 07:04:38'),
(3, 1, 'ambulances', 'update', '2026-08-05 07:04:38'),
(4, 1, 'ambulances', 'delete', '2026-08-05 07:04:38'),
(5, 1, 'ambulance_status', 'update', '2026-08-05 07:04:38'),
(6, 2, 'dispatch_assignments', 'read', '2026-08-05 07:04:38'),
(7, 2, 'dispatch_assignments', 'update', '2026-08-05 07:04:38'),
(8, 2, 'dispatch_assignments', 'approve', '2026-08-05 07:04:38'),
(9, 2, 'crew_assignments', 'create', '2026-08-05 07:04:38'),
(10, 2, 'crew_assignments', 'read', '2026-08-05 07:04:38'),
(11, 2, 'crew_assignments', 'update', '2026-08-05 07:04:38'),
(12, 3, 'emergency_requests', 'read', '2026-08-05 07:04:38'),
(13, 3, 'emergency_requests', 'update', '2026-08-05 07:04:38'),
(14, 3, 'incident_reports', 'create', '2026-08-05 07:04:38'),
(15, 3, 'incident_reports', 'read', '2026-08-05 07:04:38'),
(16, 3, 'incident_reports', 'update', '2026-08-05 07:04:38'),
(17, 3, 'incident_reports', 'approve', '2026-08-05 07:04:38'),
(18, 3, 'patient_assessments', 'create', '2026-08-05 07:04:38'),
(19, 3, 'patient_assessments', 'read', '2026-08-05 07:04:38'),
(20, 3, 'patient_assessments', 'update', '2026-08-05 07:04:38'),
(21, 4, 'members', 'create', '2026-08-05 07:04:38'),
(22, 4, 'members', 'read', '2026-08-05 07:04:38'),
(23, 4, 'members', 'update', '2026-08-05 07:04:38'),
(24, 4, 'members', 'delete', '2026-08-05 07:04:38'),
(25, 4, 'availability', 'update', '2026-08-05 07:04:38'),
(26, 5, 'roles', 'create', '2026-08-05 07:04:38'),
(27, 5, 'roles', 'read', '2026-08-05 07:04:38'),
(28, 5, 'roles', 'update', '2026-08-05 07:04:38'),
(29, 5, 'roles', 'delete', '2026-08-05 07:04:38'),
(30, 5, 'member_roles', 'create', '2026-08-05 07:04:38'),
(31, 5, 'member_roles', 'delete', '2026-08-05 07:04:38'),
(32, 5, 'audit_log', 'read', '2026-08-05 07:04:38'),
(33, 6, 'handoffs', 'read', '2026-08-05 07:04:38'),
(34, 6, 'handoffs', 'approve', '2026-08-05 07:04:38'),
(35, 6, 'facility_profile', 'update', '2026-08-05 07:04:38'),
(36, 7, 'reports', 'read', '2026-08-05 07:04:38'),
(37, 8, 'org_profile', 'read', '2026-08-05 07:04:38'),
(38, 8, 'org_profile', 'update', '2026-08-05 07:04:38'),
(39, 8, 'org_documents', 'create', '2026-08-05 07:04:38'),
(40, 8, 'org_documents', 'read', '2026-08-05 07:04:38'),
(41, 1, 'ambulance_readiness', 'read', '2026-10-04 16:52:55'),
(42, 1, 'ambulance_readiness', 'update', '2026-10-04 16:52:55'),
(43, 1, 'maintenance_records', 'create', '2026-10-04 16:52:55'),
(44, 1, 'maintenance_records', 'read', '2026-10-04 16:52:55'),
(45, 1, 'maintenance_records', 'update', '2026-10-04 16:52:55'),
(46, 2, 'mission_status', 'update', '2026-10-04 17:24:41'),
(47, 2, 'tracking', 'read', '2026-10-04 17:56:35'),
(48, 2, 'tracking', 'update', '2026-10-04 17:56:35'),
(49, 6, 'handoffs', 'create', '2026-10-04 17:56:35'),
(50, 6, 'handoffs', 'update', '2026-10-04 17:56:35');

-- --------------------------------------------------------

--
-- Table structure for table `site_page_sections`
--

CREATE TABLE `site_page_sections` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `page_slug` varchar(60) NOT NULL COMMENT 'about | terms-of-service | privacy-policy',
  `section_key` varchar(80) NOT NULL COMMENT 'stable key the frontend maps to layout/icon',
  `section_num` varchar(10) DEFAULT NULL COMMENT 'display number for legal clauses, e.g. 01 -- null for About blocks',
  `title` varchar(200) NOT NULL,
  `body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of paragraph strings',
  `list_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of bullet strings, nullable',
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_page_sections`
--

INSERT INTO `site_page_sections` (`id`, `page_slug`, `section_key`, `section_num`, `title`, `body`, `list_items`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'about', 'mission', NULL, 'Mission', '[\"To close the gap between the moment a Dasmariñas resident calls for help and the moment an ambulance reaches them — by giving citizens one trusted way to report an emergency, and giving rescue organizations a shared, real-time picture instead of separate radios, logs, and guesswork.\"]', NULL, 1, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(2, 'about', 'vision', NULL, 'Vision', '[\"A Dasmariñas City where every request for ambulance assistance is seen, ranked, and routed to the organization best placed to respond — with a human dispatcher always in the loop — so no call is lost to a busy line, an unanswered radio, or a system nobody shares.\"]', NULL, 2, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(3, 'about', 'how-it-works-1', NULL, 'A request comes in', '[\"A citizen or guest reports an emergency, or a scheduled patient transport, through the app — no account required for urgent cases.\"]', NULL, 3, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(4, 'about', 'how-it-works-2', NULL, 'DASCARE ranks the response', '[\"Distance, readiness, capability, and current workload rank the most suitable available organization and ambulance — a recommendation, not an automatic dispatch.\"]', NULL, 4, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(5, 'about', 'how-it-works-3', NULL, 'A human accepts and assigns', '[\"The incident is sent to an organization\'s dispatcher, who must still accept it and assign the ambulance and crew before anyone rolls out.\"]', NULL, 5, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(6, 'about', 'scope-covers', NULL, 'What DASCARE covers', NULL, '[\"Emergency and scheduled transport requests, from citizens and guests\", \"Live ambulance tracking and mission status updates\", \"Organization-managed staff, vehicles, schedules, and role-based access\", \"Limited hospital endorsement and handoff coordination\", \"Analytics, incident heatmaps, and audit logs for accountability\"]', 6, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(7, 'about', 'scope-not', NULL, 'What DASCARE does not do', NULL, '[\"Provide medical diagnosis or treatment guidance\", \"Maintain full hospital records or detailed bed management\", \"Dispatch police or fire services\", \"Replace a city\'s official emergency hotline\"]', 7, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(8, 'about', 'disclaimer', NULL, 'Disclaimer', '[\"DASCARE is developed as part of an academic capstone project. For life-threatening emergencies, always contact your local emergency hotline first — DASCARE supports official response, it does not replace it.\"]', NULL, 8, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(9, 'terms-of-service', 'acceptance', '01', 'Acceptance of terms', '[\"By creating an account, submitting a guest request, or otherwise using DASCARE, you agree to these Terms of Service and to the Privacy Policy. If you are using DASCARE on behalf of an ambulance organization, you also agree on behalf of that organization, and confirm you are authorized to do so.\", \"If you do not agree with these terms, do not use DASCARE — you can still contact your local emergency hotline directly.\"]', NULL, 1, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(10, 'terms-of-service', 'who-can-use', '02', 'Who can use DASCARE', '[\"DASCARE is built for residents, guests, and verified rescue personnel operating within Dasmariñas City. Citizen accounts require identity verification before certain features unlock. Guests may submit emergency and standard requests without an account, subject to the guest safeguards described below.\", \"Ambulance organizations must apply and be approved by platform administrators before their staff, vehicles, and schedules can appear in DASCARE. Approval can be suspended or withdrawn for non-compliance with applicable regulations or these terms.\"]', NULL, 2, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(11, 'terms-of-service', 'emergency-scope', '03', 'Emergency use and limitations', '[\"DASCARE is a coordination tool, not an emergency hotline and not a substitute for one. In a life-threatening situation, contact your local emergency hotline first; use DASCARE to coordinate ambulance response alongside that call, not instead of it.\", \"DASCARE\'s decision support system ranks the most suitable available organization and ambulance based on distance, readiness, capability, and workload. This ranking is a recommendation. A human dispatcher at the receiving organization must still accept the incident and assign the ambulance and crew — DASCARE does not dispatch automatically, and cannot guarantee response time, ambulance availability, or outcome.\"]', NULL, 3, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(12, 'terms-of-service', 'guest-requests', '04', 'Guest requests', '[\"Guests may submit requests without registering an account. A genuine emergency is never blocked by guest limits — after a device or phone number sends a number of guest requests in a day, further requests still go through, but receive an additional dispatcher check to guard against misuse.\", \"Guest requests are not saved to any account. The reference number issued at submission is the only way to follow up on a guest request; DASCARE cannot recover it if lost.\"]', NULL, 4, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(13, 'terms-of-service', 'responsibilities', '05', 'Your responsibilities', NULL, '[\"Provide accurate location, contact, and situation information when submitting a request.\", \"Use DASCARE only for genuine emergencies, legitimate scheduled transport, or authorized operational purposes.\", \"Keep your account credentials confidential and notify us of any unauthorized use.\", \"Do not submit false, exaggerated, or duplicate reports — repeated suspected abuse may be reviewed and can result in account restrictions.\"]', 5, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(14, 'terms-of-service', 'organization-responsibilities', '06', 'Organization responsibilities', '[\"Verified organizations are responsible for keeping their fleet, staff, and schedule information current, for responding to offered incidents in good faith, and for maintaining the credentials and compliance documents required for continued approval.\", \"Organizations manage access within their own account through role-based permissions, and are responsible for the actions of staff accounts they create and authorize.\"]', NULL, 6, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(15, 'terms-of-service', 'location-tracking', '07', 'Location and tracking data', '[\"To coordinate a response, DASCARE collects the location submitted with a request and, during an active mission, the live location of the assigned ambulance. This data is used for dispatch, tracking, handoff coordination, and safety analytics, and is handled as described in the Privacy Policy.\"]', NULL, 7, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(16, 'terms-of-service', 'availability', '08', 'Service availability', '[\"DASCARE is provided on an “as available” basis. As an academic capstone system, it does not carry the uptime guarantees of a commercial emergency dispatch platform. Connectivity issues, device limitations, or maintenance may affect availability — this is one more reason to treat official emergency hotlines as the primary channel for life-threatening situations.\"]', NULL, 8, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(17, 'terms-of-service', 'liability', '09', 'Limitation of liability', '[\"DASCARE, its developers, and participating organizations are not liable for delays, unavailability, or outcomes arising from use of the platform, to the fullest extent permitted by law. Nothing in these terms limits liability that cannot be limited under applicable Philippine law.\"]', NULL, 9, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(18, 'terms-of-service', 'termination', '10', 'Suspension and termination', '[\"We may suspend or terminate access for accounts or organizations that violate these terms, misuse the platform, or pose a risk to other users, with notice where practicable. You may stop using DASCARE and request account closure at any time.\"]', NULL, 10, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(19, 'terms-of-service', 'changes', '11', 'Changes to these terms', '[\"We may update these terms as DASCARE evolves. Material changes will be reflected here with an updated date; continued use after changes take effect means you accept the revised terms.\"]', NULL, 11, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(20, 'terms-of-service', 'governing-law', '12', 'Governing law', '[\"These terms are governed by the laws of the Republic of the Philippines, without regard to conflict-of-law principles.\"]', NULL, 12, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(21, 'privacy-policy', 'scope', '01', 'Scope of this policy', '[\"This policy applies to citizens, guests, and organization staff using DASCARE\'s web and mobile system for Dasmariñas City, and describes how we collect, use, share, and protect personal data in connection with emergency and scheduled ambulance coordination.\", \"We process data in line with the Philippine Data Privacy Act of 2012 (RA 10173) and its implementing rules.\"]', NULL, 1, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(22, 'privacy-policy', 'information-we-collect', '02', 'Information we collect', '[\"Depending on how you use DASCARE, we collect:\"]', '[\"Account details — name, contact number, email, and identity verification information for citizen accounts.\", \"Request details — the reported location, situation description, and, for guests, the name and phone number provided at submission.\", \"Location and tracking data — the coordinates attached to a request, and the live position of an assigned ambulance during an active mission.\", \"Limited prehospital information — vital signs, observations, and interventions recorded by responders for a specific incident, not a full medical history.\", \"Operational data from organizations — staff duty status, vehicle and crew assignments, and mission records.\", \"System data — device information, login activity, and audit logs of actions taken within the platform.\"]', 2, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(23, 'privacy-policy', 'how-we-use-it', '03', 'How we use this information', NULL, '[\"To route a request to the decision support system, which ranks suitable organizations and ambulances by distance, readiness, capability, and workload.\", \"To let a dispatcher accept an incident and assign an ambulance and crew.\", \"To provide live mission tracking and status updates to the requester and assigned organization.\", \"To support limited hospital endorsement and handoff at the point of transfer.\", \"To generate analytics, incident heatmaps, and performance reporting at an aggregate level.\", \"To maintain audit logs for accountability, dispute review, and security.\", \"To operate guest request safeguards, which apply an additional dispatcher check after repeated submissions from the same device or number — without ever blocking a genuine emergency.\"]', 3, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(24, 'privacy-policy', 'who-sees-it', '04', 'Who your information is shared with', '[\"Request and location details are shared with the organization and dispatcher assigned to your incident, and with the responding crew, for the duration needed to deliver assistance. Receiving facilities see only the limited handoff information relevant to a transfer, not your full request history.\", \"Platform and organization administrators can access data within their role-based permissions for oversight, compliance, and audit purposes — access is scoped by DASCARE\'s role-based access control (RBAC), not open to every staff account by default.\", \"We do not sell personal data, and we do not share it with advertisers.\"]', NULL, 4, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(25, 'privacy-policy', 'guest-data', '05', 'Guest requests', '[\"Guest requests are not attached to a registered account. The name and phone number you provide are used to process the request, apply guest safeguards, and allow a dispatcher to reach you — they are not retained as part of an ongoing profile.\"]', NULL, 5, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(26, 'privacy-policy', 'retention', '06', 'Data retention', '[\"We retain incident, mission, and audit records for as long as needed to support accountability, dispute resolution, analytics, and legal or regulatory requirements, then delete or anonymize them. Account information is retained while your account remains active, and for a limited period after closure where required for legitimate operational or legal purposes.\"]', NULL, 6, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(27, 'privacy-policy', 'security', '07', 'How we protect it', '[\"DASCARE restricts access through role-based permissions, keeps a logged audit trail of sensitive actions, and applies technical and organizational safeguards appropriate to the sensitivity of emergency and location data. No system is completely immune to risk, and we work to identify and address vulnerabilities as they are found.\"]', NULL, 7, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(28, 'privacy-policy', 'your-rights', '08', 'Your rights', '[\"Subject to applicable law, you may:\"]', '[\"Request access to the personal data we hold about you.\", \"Request correction of inaccurate or outdated information.\", \"Request deletion of your account data, subject to records we must retain for legal or safety reasons.\", \"Object to or request restriction of certain processing.\", \"Lodge a complaint with the National Privacy Commission if you believe your rights have been violated.\"]', 8, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(29, 'privacy-policy', 'children', '09', 'Minors\' data', '[\"DASCARE may process data about a minor when they are the patient in an emergency or scheduled transport request submitted by a parent, guardian, or bystander. Such data is used solely to coordinate the response and is subject to the same safeguards as any other request.\"]', NULL, 9, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05'),
(30, 'privacy-policy', 'changes', '10', 'Changes to this policy', '[\"We may update this policy as DASCARE evolves. Material changes will be reflected here with an updated date.\"]', NULL, 10, 1, '2026-08-06 12:23:05', '2026-08-06 12:23:05');

-- --------------------------------------------------------

--
-- Table structure for table `site_team_members`
--

CREATE TABLE `site_team_members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `role_title` varchar(150) NOT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_team_members`
--

INSERT INTO `site_team_members` (`id`, `name`, `role_title`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Tristan Kent Avendaño', 'Project Manager', 1, 1, '2026-08-06 11:59:44', '2026-08-06 12:02:10'),
(2, 'Noriel Philip Sarno', 'Programmer', 2, 1, '2026-08-06 11:59:44', '2026-08-06 12:02:53'),
(3, 'John Rodmar Magracia', 'System Analyst', 3, 1, '2026-08-06 11:59:44', '2026-08-06 12:03:01'),
(4, 'Beyonce Mae Binag', 'Assistant Project Manager', 4, 1, '2026-08-06 11:59:44', '2026-08-06 12:03:19'),
(5, 'Jose Miguel Arceño', 'Docs and UI/UX Designer', 5, 1, '2026-08-06 11:59:44', '2026-08-06 12:03:41');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`setting_value`)),
  `description` varchar(255) DEFAULT NULL,
  `updated_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`, `updated_by_user_id`, `updated_at`) VALUES
('dispatch.dss.weights', '{\"distance\": 0.40, \"availability\": 0.30, \"capability\": 0.20, \"workload\": 0.10}', 'Initial DSS recommendation weights', NULL, '2026-07-29 13:56:52'),
('dispatch.offer_timeout_seconds', '90', 'Timed organization incident-offer window in seconds', NULL, '2026-10-04 17:08:56'),
('emergency.guest_requests_enabled', 'true', 'Allow emergency requests without an account', NULL, '2026-07-29 13:56:52'),
('platform.app_version', '\"0.1.1\"', 'Version badge shown on the public About page', NULL, '2026-08-06 12:23:05'),
('platform.compliance_warning_days', '30', 'Days before an ambulance credential expiry is treated as a compliance warning.', NULL, '2026-10-04 17:42:49'),
('platform.incident_critical_stale_minutes', '60', 'Minutes before an unresolved pre-assignment emergency is escalated to critical overdue.', NULL, '2026-10-04 18:49:12'),
('platform.incident_stale_minutes', '15', 'Minutes before an unresolved pre-assignment emergency is flagged overdue.', NULL, '2026-10-04 18:49:12'),
('tracking.default_interval_seconds', '{\"active\": 5, \"idle\": 60}', 'Suggested mobile tracking intervals', NULL, '2026-07-29 13:56:52');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `account_status` enum('pending','active','suspended','disabled') NOT NULL DEFAULT 'active',
  `has_two_factor` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `phone`, `password_hash`, `account_status`, `has_two_factor`, `email_verified_at`, `last_login_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Macorlis', 'Dimaculangan', 'thethey247@gmail.com', '09977460502', '$2y$10$N1zPjTmDaRCgx6fQV0O.Ye.nHhvZPRF6Mk/iOnMDT9Q24WVTFmweK', 'active', 0, '2026-07-30 01:53:48', '2026-10-04 17:15:24', '2026-07-30 01:53:20', '2026-10-04 17:15:24', NULL),
(2, 'Technical', 'Administrator', 'techadmin@dascare.test', '09170000001', '$2y$10$N1zPjTmDaRCgx6fQV0O.Ye.nHhvZPRF6Mk/iOnMDT9Q24WVTFmweK', 'active', 0, '2026-08-05 13:33:38', '2026-10-04 17:43:28', '2026-08-05 13:33:38', '2026-10-04 17:43:28', NULL),
(3, 'Platform', 'Executive', 'executive@dascare.test', '09170000002', '$2y$10$N1zPjTmDaRCgx6fQV0O.Ye.nHhvZPRF6Mk/iOnMDT9Q24WVTFmweK', 'active', 0, '2026-08-05 13:33:38', '2026-10-04 18:20:21', '2026-08-05 13:33:38', '2026-10-04 18:20:21', NULL),
(4, 'Organization', 'Administrator', 'orgadmin@dascare.test', '09170000003', '$2y$10$N1zPjTmDaRCgx6fQV0O.Ye.nHhvZPRF6Mk/iOnMDT9Q24WVTFmweK', 'active', 0, '2026-08-05 13:33:38', '2026-10-04 18:19:54', '2026-08-05 13:33:38', '2026-10-04 18:19:54', NULL),
(5, 'Demo', 'Dispatcher', 'dispatcher@dascare.test', '09170000004', '$2y$10$N1zPjTmDaRCgx6fQV0O.Ye.nHhvZPRF6Mk/iOnMDT9Q24WVTFmweK', 'active', 0, '2026-08-05 13:33:38', '2026-10-04 17:44:12', '2026-08-05 13:33:38', '2026-10-04 17:44:12', NULL),
(6, 'Demo', 'Citizen', 'citizen@dascare.test', '09170000005', '$2y$12$Giuz09w5FTrpZU6wmuNlxu8vXikuTfAJEw/ShskyPVkJKslCC26ZO', 'active', 0, '2026-08-05 13:33:38', NULL, '2026-08-05 13:33:38', '2026-08-05 13:33:38', NULL),
(7, 'Joshua', 'Magarcia', 'magracia.johnrodmar@ncst.edu.ph', '09123456789', '$2y$10$NGpW9g6cDm9elEQx.PmDKeOkfrpoDwL0W/88aH/6gbVaHYJ0/iHqC', 'active', 0, '2026-10-04 13:09:45', '2026-10-04 16:44:33', '2026-10-04 13:08:55', '2026-10-04 16:44:33', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role` enum('technical_super_admin','platform_executive_admin','organization_admin','organization_operational_user','citizen') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`id`, `user_id`, `role`, `created_at`) VALUES
(1, 1, 'citizen', '2026-07-30 01:53:20'),
(2, 2, 'technical_super_admin', '2026-08-05 13:33:38'),
(3, 3, 'platform_executive_admin', '2026-08-05 13:33:38'),
(4, 4, 'organization_admin', '2026-08-05 13:33:38'),
(5, 5, 'organization_operational_user', '2026-08-05 13:33:38'),
(6, 6, 'citizen', '2026-08-05 13:33:38'),
(7, 7, 'citizen', '2026-10-04 13:08:55');

-- --------------------------------------------------------

--
-- Table structure for table `user_security_tokens`
--

CREATE TABLE `user_security_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `email_otp` varchar(255) DEFAULT NULL,
  `email_otp_expires_at` datetime DEFAULT NULL,
  `login_otp` varchar(255) DEFAULT NULL,
  `login_otp_expires_at` datetime DEFAULT NULL,
  `reset_otp` varchar(255) DEFAULT NULL,
  `reset_otp_expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_security_tokens`
--

INSERT INTO `user_security_tokens` (`id`, `user_id`, `email_otp`, `email_otp_expires_at`, `login_otp`, `login_otp_expires_at`, `reset_otp`, `reset_otp_expires_at`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, NULL, '$2y$10$9ymkApe.GSrQ2OPobQBJrukc5QkyXZAwBe0m/Lsq1Wxl9KNJKV6Rq', '2026-10-04 16:14:49', '$2y$10$0F2ndTPR3xiwNpcXhxrhkerG40x7decfNnLCLS6mLAgxqlSTJiErC', '2026-08-05 08:50:12', '2026-07-30 01:53:20', '2026-10-04 14:04:49'),
(2, 7, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-04 13:08:55', '2026-10-04 13:09:45');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ambulances`
--
ALTER TABLE `ambulances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ambulance_unit` (`organization_id`,`unit_code`),
  ADD UNIQUE KEY `uq_ambulance_plate` (`plate_number`),
  ADD KEY `idx_ambulance_availability` (`organization_id`,`status`),
  ADD KEY `idx_ambulance_dispatch_ready` (`organization_id`,`status`,`deleted_at`);

--
-- Indexes for table `ambulance_locations`
--
ALTER TABLE `ambulance_locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ambulance_location_timeline` (`ambulance_id`,`recorded_at`),
  ADD KEY `idx_request_tracking_timeline` (`emergency_request_id`,`recorded_at`),
  ADD KEY `idx_location_actor` (`recorded_by_user_id`);

--
-- Indexes for table `ambulance_maintenance_records`
--
ALTER TABLE `ambulance_maintenance_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_maintenance_ambulance_status` (`ambulance_id`,`status`,`scheduled_for`),
  ADD KEY `idx_maintenance_schedule` (`status`,`scheduled_for`),
  ADD KEY `fk_maintenance_creator` (`created_by_user_id`);

--
-- Indexes for table `ambulance_readiness_checks`
--
ALTER TABLE `ambulance_readiness_checks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_readiness_ambulance_time` (`ambulance_id`,`checked_at`),
  ADD KEY `idx_readiness_status` (`overall_status`,`checked_at`),
  ADD KEY `fk_readiness_user` (`checked_by_user_id`);

--
-- Indexes for table `ambulance_status_logs`
--
ALTER TABLE `ambulance_status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ambulance_status_user` (`changed_by_user_id`),
  ADD KEY `idx_ambulance_status_timeline` (`ambulance_id`,`created_at`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_audit_user` (`user_id`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_audit_org_time` (`organization_id`,`created_at`),
  ADD KEY `idx_audit_created_action` (`created_at`,`action`);

--
-- Indexes for table `citizen_saved_locations`
--
ALTER TABLE `citizen_saved_locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_citizen_locations_user` (`user_id`);

--
-- Indexes for table `crew_assignments`
--
ALTER TABLE `crew_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dispatch_crew` (`dispatch_assignment_id`,`organization_member_id`),
  ADD KEY `fk_crew_member` (`organization_member_id`);

--
-- Indexes for table `dispatch_assignments`
--
ALTER TABLE `dispatch_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dispatch_request` (`emergency_request_id`),
  ADD KEY `fk_assignment_org` (`organization_id`),
  ADD KEY `fk_assignment_dispatcher` (`assigned_by_user_id`),
  ADD KEY `idx_assignment_request` (`emergency_request_id`,`assignment_status`),
  ADD KEY `idx_assignment_ambulance` (`ambulance_id`,`assignment_status`),
  ADD KEY `idx_assignment_offer` (`incident_offer_id`),
  ADD KEY `idx_dispatch_org_status_time` (`organization_id`,`assignment_status`,`assigned_at`);

--
-- Indexes for table `dispatch_assignment_logs`
--
ALTER TABLE `dispatch_assignment_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_assignment_log_user` (`changed_by_user_id`),
  ADD KEY `idx_assignment_log_timeline` (`dispatch_assignment_id`,`created_at`);

--
-- Indexes for table `dss_recommendations`
--
ALTER TABLE `dss_recommendations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dss_run_org` (`run_id`,`organization_id`),
  ADD KEY `idx_dss_request_rank` (`emergency_request_id`,`rank_position`),
  ADD KEY `idx_dss_org_score` (`organization_id`,`total_score`),
  ADD KEY `idx_dss_ambulance` (`recommended_ambulance_id`);

--
-- Indexes for table `dss_recommendation_runs`
--
ALTER TABLE `dss_recommendation_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dss_runs_request` (`emergency_request_id`,`run_status`,`created_at`),
  ADD KEY `idx_dss_runs_generator` (`generated_by_user_id`);

--
-- Indexes for table `emergency_categories`
--
ALTER TABLE `emergency_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `emergency_requests`
--
ALTER TABLE `emergency_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_number` (`reference_number`),
  ADD KEY `fk_emergency_requester` (`requester_user_id`),
  ADD KEY `fk_emergency_creator` (`created_by_user_id`),
  ADD KEY `fk_emergency_category` (`emergency_category_id`),
  ADD KEY `idx_emergency_status_severity` (`status`,`severity`),
  ADD KEY `idx_emergency_location` (`barangay`),
  ADD KEY `idx_emergency_submitted` (`submitted_at`),
  ADD KEY `idx_emergency_request_mode` (`request_mode`,`status`),
  ADD KEY `fk_emergency_merged_into` (`merged_into_request_id`),
  ADD KEY `idx_emergency_dispatch_queue` (`request_mode`,`status`,`submitted_at`),
  ADD KEY `idx_emergency_attention` (`attention_level`,`status`,`submitted_at`);

--
-- Indexes for table `emergency_request_media`
--
ALTER TABLE `emergency_request_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_request_media_request` (`emergency_request_id`),
  ADD KEY `fk_request_media_user` (`uploaded_by_user_id`);

--
-- Indexes for table `emergency_request_medical_snapshots`
--
ALTER TABLE `emergency_request_medical_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `emergency_request_id` (`emergency_request_id`);

--
-- Indexes for table `emergency_request_status_logs`
--
ALTER TABLE `emergency_request_status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_request_log_user` (`changed_by_user_id`),
  ADD KEY `idx_request_log_timeline` (`emergency_request_id`,`created_at`);

--
-- Indexes for table `incident_offers`
--
ALTER TABLE `incident_offers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_offer_org_queue` (`organization_id`,`offer_status`,`expires_at`),
  ADD KEY `idx_offer_request` (`emergency_request_id`,`offer_status`,`created_at`),
  ADD KEY `idx_offer_run` (`dss_run_id`,`offer_status`),
  ADD KEY `idx_offer_responder` (`responded_by_user_id`),
  ADD KEY `fk_offer_rec` (`recommendation_id`);

--
-- Indexes for table `incident_reports`
--
ALTER TABLE `incident_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `emergency_request_id` (`emergency_request_id`),
  ADD KEY `fk_report_preparer` (`prepared_by_user_id`),
  ADD KEY `fk_report_destination` (`destination_facility_id`),
  ADD KEY `fk_report_reviewer` (`reviewed_by_user_id`),
  ADD KEY `idx_report_status` (`organization_id`,`report_status`);

--
-- Indexes for table `kyc_verifications`
--
ALTER TABLE `kyc_verifications`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `fk_kyc_verified_by` (`verified_by`),
  ADD KEY `idx_kyc_review_queue` (`status`,`submitted_at`);

--
-- Indexes for table `medical_facilities`
--
ALTER TABLE `medical_facilities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_medical_facilities_org` (`organization_id`);

--
-- Indexes for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_notification_dedup` (`user_id`,`dedup_key`),
  ADD KEY `idx_notification_unread` (`user_id`,`read_at`,`created_at`);

--
-- Indexes for table `organizations`
--
ALTER TABLE `organizations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_organizations_application_reference` (`application_reference`),
  ADD KEY `idx_organizations_status` (`status`),
  ADD KEY `fk_organizations_verified_by` (`verified_by`),
  ADD KEY `idx_organizations_application_queue` (`application_status`,`application_submitted_at`),
  ADD KEY `idx_organizations_primary_admin` (`primary_admin_user_id`),
  ADD KEY `idx_organizations_application_reviewer` (`application_reviewed_by`);

--
-- Indexes for table `organization_documents`
--
ALTER TABLE `organization_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_org_documents_org` (`organization_id`,`doc_type`),
  ADD KEY `fk_org_documents_reviewed_by` (`reviewed_by`),
  ADD KEY `fk_org_documents_uploaded_by` (`uploaded_by_user_id`),
  ADD KEY `idx_org_docs_status` (`organization_id`,`status`,`created_at`);

--
-- Indexes for table `organization_invitations`
--
ALTER TABLE `organization_invitations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_org_invitation_token` (`token_hash`),
  ADD KEY `idx_org_invitation_queue` (`organization_id`,`status`,`created_at`),
  ADD KEY `idx_org_invitation_email` (`organization_id`,`email`,`status`),
  ADD KEY `idx_org_invitation_role` (`role_id`),
  ADD KEY `idx_org_invitation_inviter` (`invited_by_user_id`);

--
-- Indexes for table `organization_members`
--
ALTER TABLE `organization_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_org_member` (`organization_id`,`user_id`),
  ADD KEY `fk_org_members_user` (`user_id`),
  ADD KEY `idx_org_members_status` (`organization_id`,`membership_status`),
  ADD KEY `fk_org_members_invited_by` (`invited_by_user_id`);

--
-- Indexes for table `organization_service_areas`
--
ALTER TABLE `organization_service_areas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_org_service_area` (`organization_id`,`barangay`);

--
-- Indexes for table `org_member_roles`
--
ALTER TABLE `org_member_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_omr` (`organization_member_id`,`role_id`),
  ADD KEY `fk_omr_role` (`role_id`),
  ADD KEY `fk_omr_assigned_by` (`assigned_by`);

--
-- Indexes for table `org_roles`
--
ALTER TABLE `org_roles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_org_roles_org` (`organization_id`),
  ADD KEY `fk_org_roles_created_by` (`created_by`),
  ADD KEY `idx_org_roles_name` (`organization_id`,`role_name`);

--
-- Indexes for table `org_role_permissions`
--
ALTER TABLE `org_role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_orp` (`role_id`,`permission_id`),
  ADD KEY `fk_orp_permission` (`permission_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `fk_password_reset_user` (`user_id`),
  ADD KEY `idx_password_reset_expiry` (`expires_at`,`used_at`);

--
-- Indexes for table `patient_assessments`
--
ALTER TABLE `patient_assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_assessment_user` (`recorded_by_user_id`),
  ADD KEY `idx_assessment_request` (`emergency_request_id`);

--
-- Indexes for table `patient_handoffs`
--
ALTER TABLE `patient_handoffs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_handoff_request` (`emergency_request_id`),
  ADD KEY `fk_handoff_assignment` (`dispatch_assignment_id`),
  ADD KEY `fk_handoff_facility` (`medical_facility_id`),
  ADD KEY `fk_handoff_initiator` (`initiated_by_user_id`),
  ADD KEY `fk_handoff_responder` (`responded_by_user_id`),
  ADD KEY `idx_handoff_status` (`medical_facility_id`,`status`);

--
-- Indexes for table `personnel_availability_logs`
--
ALTER TABLE `personnel_availability_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_availability_user` (`changed_by_user_id`),
  ADD KEY `idx_availability_timeline` (`organization_member_id`,`created_at`);

--
-- Indexes for table `personnel_profiles`
--
ALTER TABLE `personnel_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `organization_member_id` (`organization_member_id`);

--
-- Indexes for table `platform_account_roles`
--
ALTER TABLE `platform_account_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_par` (`user_id`,`role_id`),
  ADD KEY `fk_par_role` (`role_id`),
  ADD KEY `fk_par_assigned_by` (`assigned_by`);

--
-- Indexes for table `platform_modules`
--
ALTER TABLE `platform_modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_platform_modules_key` (`module_key`);

--
-- Indexes for table `platform_permissions`
--
ALTER TABLE `platform_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_platform_perm` (`module_id`,`resource`,`action`);

--
-- Indexes for table `platform_roles`
--
ALTER TABLE `platform_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_platform_roles_name` (`role_name`),
  ADD KEY `fk_platform_roles_created_by` (`created_by`);

--
-- Indexes for table `platform_role_permissions`
--
ALTER TABLE `platform_role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prp` (`role_id`,`permission_id`),
  ADD KEY `fk_prp_permission` (`permission_id`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rate_limit_action_identifier` (`action_key`,`identifier_hash`);

--
-- Indexes for table `rbac_audit_log`
--
ALTER TABLE `rbac_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rbac_audit_org` (`organization_id`,`created_at`),
  ADD KEY `fk_rbac_audit_changed_by` (`changed_by`);

--
-- Indexes for table `rbac_modules`
--
ALTER TABLE `rbac_modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rbac_modules_key` (`module_key`);

--
-- Indexes for table `rbac_permissions`
--
ALTER TABLE `rbac_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rbac_perm` (`module_id`,`resource`,`action`);

--
-- Indexes for table `site_page_sections`
--
ALTER TABLE `site_page_sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_page_section` (`page_slug`,`section_key`),
  ADD KEY `idx_page_active_sort` (`page_slug`,`is_active`,`sort_order`);

--
-- Indexes for table `site_team_members`
--
ALTER TABLE `site_team_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_team_active_sort` (`is_active`,`sort_order`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`),
  ADD KEY `fk_setting_user` (`updated_by_user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD KEY `idx_users_status` (`account_status`),
  ADD KEY `idx_users_status_created` (`account_status`,`created_at`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_user_roles_role` (`role`);

--
-- Indexes for table `user_security_tokens`
--
ALTER TABLE `user_security_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_security_tokens_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ambulances`
--
ALTER TABLE `ambulances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ambulance_locations`
--
ALTER TABLE `ambulance_locations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ambulance_maintenance_records`
--
ALTER TABLE `ambulance_maintenance_records`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ambulance_readiness_checks`
--
ALTER TABLE `ambulance_readiness_checks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ambulance_status_logs`
--
ALTER TABLE `ambulance_status_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `citizen_saved_locations`
--
ALTER TABLE `citizen_saved_locations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crew_assignments`
--
ALTER TABLE `crew_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dispatch_assignments`
--
ALTER TABLE `dispatch_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dispatch_assignment_logs`
--
ALTER TABLE `dispatch_assignment_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dss_recommendations`
--
ALTER TABLE `dss_recommendations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `dss_recommendation_runs`
--
ALTER TABLE `dss_recommendation_runs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `emergency_categories`
--
ALTER TABLE `emergency_categories`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `emergency_requests`
--
ALTER TABLE `emergency_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `emergency_request_media`
--
ALTER TABLE `emergency_request_media`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `emergency_request_medical_snapshots`
--
ALTER TABLE `emergency_request_medical_snapshots`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `emergency_request_status_logs`
--
ALTER TABLE `emergency_request_status_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `incident_offers`
--
ALTER TABLE `incident_offers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `incident_reports`
--
ALTER TABLE `incident_reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medical_facilities`
--
ALTER TABLE `medical_facilities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `organizations`
--
ALTER TABLE `organizations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `organization_documents`
--
ALTER TABLE `organization_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organization_invitations`
--
ALTER TABLE `organization_invitations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organization_members`
--
ALTER TABLE `organization_members`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `organization_service_areas`
--
ALTER TABLE `organization_service_areas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `org_member_roles`
--
ALTER TABLE `org_member_roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `org_roles`
--
ALTER TABLE `org_roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `org_role_permissions`
--
ALTER TABLE `org_role_permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=826;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_assessments`
--
ALTER TABLE `patient_assessments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_handoffs`
--
ALTER TABLE `patient_handoffs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personnel_availability_logs`
--
ALTER TABLE `personnel_availability_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personnel_profiles`
--
ALTER TABLE `personnel_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `platform_account_roles`
--
ALTER TABLE `platform_account_roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `platform_modules`
--
ALTER TABLE `platform_modules`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `platform_permissions`
--
ALTER TABLE `platform_permissions`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `platform_roles`
--
ALTER TABLE `platform_roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `platform_role_permissions`
--
ALTER TABLE `platform_role_permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=177;

--
-- AUTO_INCREMENT for table `rbac_audit_log`
--
ALTER TABLE `rbac_audit_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rbac_modules`
--
ALTER TABLE `rbac_modules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `rbac_permissions`
--
ALTER TABLE `rbac_permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `site_page_sections`
--
ALTER TABLE `site_page_sections`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `site_team_members`
--
ALTER TABLE `site_team_members`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_security_tokens`
--
ALTER TABLE `user_security_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ambulances`
--
ALTER TABLE `ambulances`
  ADD CONSTRAINT `fk_ambulance_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);

--
-- Constraints for table `ambulance_locations`
--
ALTER TABLE `ambulance_locations`
  ADD CONSTRAINT `fk_location_actor` FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_location_ambulance` FOREIGN KEY (`ambulance_id`) REFERENCES `ambulances` (`id`),
  ADD CONSTRAINT `fk_location_request` FOREIGN KEY (`emergency_request_id`) REFERENCES `emergency_requests` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ambulance_maintenance_records`
--
ALTER TABLE `ambulance_maintenance_records`
  ADD CONSTRAINT `fk_maintenance_ambulance` FOREIGN KEY (`ambulance_id`) REFERENCES `ambulances` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_maintenance_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `ambulance_readiness_checks`
--
ALTER TABLE `ambulance_readiness_checks`
  ADD CONSTRAINT `fk_readiness_ambulance` FOREIGN KEY (`ambulance_id`) REFERENCES `ambulances` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_readiness_user` FOREIGN KEY (`checked_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `ambulance_status_logs`
--
ALTER TABLE `ambulance_status_logs`
  ADD CONSTRAINT `fk_ambulance_status_ambulance` FOREIGN KEY (`ambulance_id`) REFERENCES `ambulances` (`id`),
  ADD CONSTRAINT `fk_ambulance_status_user` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `citizen_saved_locations`
--
ALTER TABLE `citizen_saved_locations`
  ADD CONSTRAINT `fk_citizen_locations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `crew_assignments`
--
ALTER TABLE `crew_assignments`
  ADD CONSTRAINT `fk_crew_dispatch` FOREIGN KEY (`dispatch_assignment_id`) REFERENCES `dispatch_assignments` (`id`),
  ADD CONSTRAINT `fk_crew_member` FOREIGN KEY (`organization_member_id`) REFERENCES `organization_members` (`id`);

--
-- Constraints for table `dispatch_assignments`
--
ALTER TABLE `dispatch_assignments`
  ADD CONSTRAINT `fk_assignment_ambulance` FOREIGN KEY (`ambulance_id`) REFERENCES `ambulances` (`id`),
  ADD CONSTRAINT `fk_assignment_dispatcher` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_assignment_offer` FOREIGN KEY (`incident_offer_id`) REFERENCES `incident_offers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_assignment_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  ADD CONSTRAINT `fk_assignment_request` FOREIGN KEY (`emergency_request_id`) REFERENCES `emergency_requests` (`id`);

--
-- Constraints for table `dispatch_assignment_logs`
--
ALTER TABLE `dispatch_assignment_logs`
  ADD CONSTRAINT `fk_assignment_log_assignment` FOREIGN KEY (`dispatch_assignment_id`) REFERENCES `dispatch_assignments` (`id`),
  ADD CONSTRAINT `fk_assignment_log_user` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dss_recommendations`
--
ALTER TABLE `dss_recommendations`
  ADD CONSTRAINT `fk_dss_rec_ambulance` FOREIGN KEY (`recommended_ambulance_id`) REFERENCES `ambulances` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_dss_rec_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dss_rec_request` FOREIGN KEY (`emergency_request_id`) REFERENCES `emergency_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dss_rec_run` FOREIGN KEY (`run_id`) REFERENCES `dss_recommendation_runs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dss_recommendation_runs`
--
ALTER TABLE `dss_recommendation_runs`
  ADD CONSTRAINT `fk_dss_runs_generator` FOREIGN KEY (`generated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_dss_runs_request` FOREIGN KEY (`emergency_request_id`) REFERENCES `emergency_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `emergency_requests`
--
ALTER TABLE `emergency_requests`
  ADD CONSTRAINT `fk_emergency_category` FOREIGN KEY (`emergency_category_id`) REFERENCES `emergency_categories` (`id`),
  ADD CONSTRAINT `fk_emergency_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_emergency_merged_into` FOREIGN KEY (`merged_into_request_id`) REFERENCES `emergency_requests` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_emergency_requester` FOREIGN KEY (`requester_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `emergency_request_media`
--
ALTER TABLE `emergency_request_media`
  ADD CONSTRAINT `fk_request_media_request` FOREIGN KEY (`emergency_request_id`) REFERENCES `emergency_requests` (`id`),
  ADD CONSTRAINT `fk_request_media_user` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `emergency_request_medical_snapshots`
--
ALTER TABLE `emergency_request_medical_snapshots`
  ADD CONSTRAINT `fk_medical_snapshot_request` FOREIGN KEY (`emergency_request_id`) REFERENCES `emergency_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `emergency_request_status_logs`
--
ALTER TABLE `emergency_request_status_logs`
  ADD CONSTRAINT `fk_request_log_request` FOREIGN KEY (`emergency_request_id`) REFERENCES `emergency_requests` (`id`),
  ADD CONSTRAINT `fk_request_log_user` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `incident_offers`
--
ALTER TABLE `incident_offers`
  ADD CONSTRAINT `fk_offer_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_offer_rec` FOREIGN KEY (`recommendation_id`) REFERENCES `dss_recommendations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_offer_request` FOREIGN KEY (`emergency_request_id`) REFERENCES `emergency_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_offer_responder` FOREIGN KEY (`responded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_offer_run` FOREIGN KEY (`dss_run_id`) REFERENCES `dss_recommendation_runs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `incident_reports`
--
ALTER TABLE `incident_reports`
  ADD CONSTRAINT `fk_report_destination` FOREIGN KEY (`destination_facility_id`) REFERENCES `medical_facilities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_report_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  ADD CONSTRAINT `fk_report_preparer` FOREIGN KEY (`prepared_by_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_report_request` FOREIGN KEY (`emergency_request_id`) REFERENCES `emergency_requests` (`id`),
  ADD CONSTRAINT `fk_report_reviewer` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `kyc_verifications`
--
ALTER TABLE `kyc_verifications`
  ADD CONSTRAINT `fk_kyc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_kyc_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `medical_facilities`
--
ALTER TABLE `medical_facilities`
  ADD CONSTRAINT `fk_medical_facilities_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD CONSTRAINT `fk_medical_records_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `organizations`
--
ALTER TABLE `organizations`
  ADD CONSTRAINT `fk_organizations_application_reviewed_by` FOREIGN KEY (`application_reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_organizations_primary_admin` FOREIGN KEY (`primary_admin_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_organizations_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `organization_documents`
--
ALTER TABLE `organization_documents`
  ADD CONSTRAINT `fk_org_documents_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_org_documents_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_org_documents_uploaded_by` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `organization_invitations`
--
ALTER TABLE `organization_invitations`
  ADD CONSTRAINT `fk_org_invitation_inviter` FOREIGN KEY (`invited_by_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_org_invitation_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_org_invitation_role` FOREIGN KEY (`role_id`) REFERENCES `org_roles` (`id`);

--
-- Constraints for table `organization_members`
--
ALTER TABLE `organization_members`
  ADD CONSTRAINT `fk_org_members_invited_by` FOREIGN KEY (`invited_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_org_members_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  ADD CONSTRAINT `fk_org_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `organization_service_areas`
--
ALTER TABLE `organization_service_areas`
  ADD CONSTRAINT `fk_org_service_area_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `org_member_roles`
--
ALTER TABLE `org_member_roles`
  ADD CONSTRAINT `fk_omr_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_omr_member` FOREIGN KEY (`organization_member_id`) REFERENCES `organization_members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_omr_role` FOREIGN KEY (`role_id`) REFERENCES `org_roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `org_roles`
--
ALTER TABLE `org_roles`
  ADD CONSTRAINT `fk_org_roles_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_org_roles_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `org_role_permissions`
--
ALTER TABLE `org_role_permissions`
  ADD CONSTRAINT `fk_orp_permission` FOREIGN KEY (`permission_id`) REFERENCES `rbac_permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_orp_role` FOREIGN KEY (`role_id`) REFERENCES `org_roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_assessments`
--
ALTER TABLE `patient_assessments`
  ADD CONSTRAINT `fk_assessment_request` FOREIGN KEY (`emergency_request_id`) REFERENCES `emergency_requests` (`id`),
  ADD CONSTRAINT `fk_assessment_user` FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `patient_handoffs`
--
ALTER TABLE `patient_handoffs`
  ADD CONSTRAINT `fk_handoff_assignment` FOREIGN KEY (`dispatch_assignment_id`) REFERENCES `dispatch_assignments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_handoff_facility` FOREIGN KEY (`medical_facility_id`) REFERENCES `medical_facilities` (`id`),
  ADD CONSTRAINT `fk_handoff_initiator` FOREIGN KEY (`initiated_by_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_handoff_request` FOREIGN KEY (`emergency_request_id`) REFERENCES `emergency_requests` (`id`),
  ADD CONSTRAINT `fk_handoff_responder` FOREIGN KEY (`responded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `personnel_availability_logs`
--
ALTER TABLE `personnel_availability_logs`
  ADD CONSTRAINT `fk_availability_member` FOREIGN KEY (`organization_member_id`) REFERENCES `organization_members` (`id`),
  ADD CONSTRAINT `fk_availability_user` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `personnel_profiles`
--
ALTER TABLE `personnel_profiles`
  ADD CONSTRAINT `fk_personnel_member` FOREIGN KEY (`organization_member_id`) REFERENCES `organization_members` (`id`);

--
-- Constraints for table `platform_account_roles`
--
ALTER TABLE `platform_account_roles`
  ADD CONSTRAINT `fk_par_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_par_role` FOREIGN KEY (`role_id`) REFERENCES `platform_roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_par_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `platform_permissions`
--
ALTER TABLE `platform_permissions`
  ADD CONSTRAINT `fk_platform_perm_module` FOREIGN KEY (`module_id`) REFERENCES `platform_modules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `platform_roles`
--
ALTER TABLE `platform_roles`
  ADD CONSTRAINT `fk_platform_roles_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `platform_role_permissions`
--
ALTER TABLE `platform_role_permissions`
  ADD CONSTRAINT `fk_prp_permission` FOREIGN KEY (`permission_id`) REFERENCES `platform_permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_prp_role` FOREIGN KEY (`role_id`) REFERENCES `platform_roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rbac_audit_log`
--
ALTER TABLE `rbac_audit_log`
  ADD CONSTRAINT `fk_rbac_audit_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_rbac_audit_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rbac_permissions`
--
ALTER TABLE `rbac_permissions`
  ADD CONSTRAINT `fk_rbac_perm_module` FOREIGN KEY (`module_id`) REFERENCES `rbac_modules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `fk_setting_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
