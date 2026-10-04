-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 04, 2026 at 02:37 PM
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
-- Database: `crm_software`
--

-- --------------------------------------------------------


-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `source` enum('referral','website','cold_outreach','other') NOT NULL DEFAULT 'other',
  `status` enum('lead','active','inactive') NOT NULL DEFAULT 'lead',
  `assigned_to` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `owner_id` bigint(20) UNSIGNED DEFAULT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `website` varchar(255) DEFAULT NULL,
  `industry` varchar(255) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`address`)),
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `id` char(36) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `email` varchar(254) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `company` varchar(120) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'lead',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contacts`
--

INSERT INTO `contacts` (`id`, `first_name`, `last_name`, `email`, `phone`, `company`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
('01a0f945-4b34-7159-81de-820a55bc50df', 'John', 'Doe', 'john.doe@example.com', '+8801712345678', 'Acme Ltd', 'lead', '2026-10-01 15:01:03', '2026-10-01 15:01:03', NULL),
('01a0f945-cae6-71e2-b685-0175d1b37f19', 'Consectetur nostrum', 'Neque veniam nesciu', 'tuvicatubu@mailinator.com', 'Fuga Sequi culpa a', 'Nobis dicta exercita', 'inactive', '2026-10-01 15:01:35', '2026-10-01 15:02:44', NULL),
('01a0f954-99ec-7121-9fdd-33b38f633325', 'Quo magna explicabo', 'Nihil facere ipsam e', 'cygixe@mailinator.com', 'Aliquip deserunt ear', 'Dolores est asperna', 'active', '2026-10-01 15:17:46', '2026-10-01 15:17:46', NULL),
('01a0f954-d28f-70bd-842b-ad4466e34c5a', 'Et est minima ipsam', 'Non et molestiae mol', 'fohi@mailinator.com', 'Irure in inventore l', 'Dolorum quod beatae', 'inactive', '2026-10-01 15:18:00', '2026-10-01 15:18:00', NULL),
('01a0f955-027d-703f-a2f7-dcb79b80eb50', 'Laborum Incidunt d', 'Ipsa repellendus V', 'civehanugu@mailinator.com', 'Animi consequuntur', 'Quidem reiciendis co', 'active', '2026-10-01 15:18:13', '2026-10-01 15:18:13', NULL),
('01a0f955-2405-7060-8917-003cf47d86e6', 'Enim et aut ea sed s', 'Dolorem cupiditate e', 'kusulagyb@mailinator.com', 'Elit dolore delenit', 'Voluptatem dolorem u', 'lead', '2026-10-01 15:18:21', '2026-10-01 15:18:21', NULL),
('01a0f955-5b7b-7278-bb1b-5f6a4f2f89e6', 'Adipisci in facilis', 'Numquam laboriosam', 'xaveb@mailinator.com', 'Dolor quaerat expedi', 'Reprehenderit optio', 'active', '2026-10-01 15:18:35', '2026-10-01 15:18:35', NULL),
('01a0f955-9d98-73d8-969b-4ceaa7e4097b', 'Assumenda odio in vo', 'Ea eos accusantium', 'vuxyleb@mailinator.com', 'Lorem reiciendis quo', 'Corrupti consequat', 'active', '2026-10-01 15:18:52', '2026-10-01 15:18:52', NULL),
('01a0f955-f484-736d-87ef-84d8f8b9523d', 'Sed natus iusto dolo', 'Dolor aut exercitati', 'cuzixunuru@mailinator.com', 'Officia ut quis eius', 'Alias tempora tempor', 'lead', '2026-10-01 15:19:15', '2026-10-01 15:19:15', NULL),
('01a0f956-12b6-732b-a779-925a1ecf4fdf', 'Velit perferendis ne', 'Iusto quos dolor ut', 'vogugiqixe@mailinator.com', 'Natus aute rem elige', 'Blanditiis illo ut a', 'inactive', '2026-10-01 15:19:22', '2026-10-01 15:19:22', NULL),
('01a0f956-3343-701f-ac25-e54e188b4883', 'Quidem molestias ali', 'Esse dolores volupt', 'susagofyjy@mailinator.com', 'Id mollit ut ut vol', 'Illo qui atque assum', 'active', '2026-10-01 15:19:31', '2026-10-01 15:19:31', NULL),
('01a0f956-666f-71bd-97a1-e60ea41560d2', 'Blanditiis animi es', 'Esse aut doloribus', 'karyt@mailinator.com', 'Quae est do nostrum', 'Elit omnis occaecat', 'lead', '2026-10-01 15:19:44', '2026-10-01 15:19:44', NULL),
('01a0f956-91a4-7190-ac98-06fc4e7f5ec1', 'Velit occaecat aut e', 'Qui est fuga Deleni', 'tuwi@mailinator.com', 'Eius aut unde incidi', 'Voluptatem atque asp', 'inactive', '2026-10-01 15:19:55', '2026-10-01 15:19:55', NULL),
('01a0f956-b33e-7110-9b7d-f56d1c34a96a', 'Esse distinctio Qui', 'Aperiam vel odio ull', 'pufyd@mailinator.com', 'Voluptas dolor vitae', 'Ad perspiciatis exp', 'active', '2026-10-01 15:20:03', '2026-10-01 15:20:03', NULL),
('01a0f956-d4df-711c-b70d-3ebb88a215dc', 'Qui recusandae Itaq', 'Nisi et in vitae sin', 'cilypefox@mailinator.com', 'In qui nisi ab liber', 'Veniam dignissimos', 'lead', '2026-10-01 15:20:12', '2026-10-01 15:20:12', NULL),
('01a0f956-f8a2-73f9-b81a-75888f12e7d0', 'Commodo ut et conseq', 'Mollitia enim hic ni', 'vumi@mailinator.com', 'Ipsam in in laborios', 'Est voluptatem enim', 'inactive', '2026-10-01 15:20:21', '2026-10-01 15:20:21', NULL),
('01a0f957-2ee3-7218-be25-ef3d466f737e', 'Laudantium iusto hi', 'Quis ullamco assumen', 'hojipeku@mailinator.com', 'Omnis nesciunt tota', 'Eaque vitae quam ali', 'active', '2026-10-01 15:20:35', '2026-10-01 15:20:35', NULL),
('01a0f957-6623-72e3-95ab-64e74c975283', 'Qui temporibus volup', 'Qui modi eum optio', 'suduz@mailinator.com', 'Aut qui dolore omnis', 'Dolor ut culpa dolo', 'lead', '2026-10-01 15:20:49', '2026-10-01 15:20:49', NULL),
('01a0f957-93ec-7134-8fa5-29cea6d03abb', 'Sit in soluta dolor', 'Sint occaecat imped', 'nony@mailinator.com', 'Fugit dolore quod q', 'Consectetur qui ape', 'inactive', '2026-10-01 15:21:01', '2026-10-01 15:21:01', NULL),
('01a0f957-c662-7361-aff0-98bc757d11a6', 'Dolor eius duis ut c', 'Dolore laboriosam n', 'cejuh@mailinator.com', 'Nisi ipsum aliquam a', 'Libero molestiae sit', 'lead', '2026-10-01 15:21:14', '2026-10-01 15:21:14', NULL),
('01a0f957-e9b9-73a7-9ff8-117adabfe10b', 'Qui provident non a', 'Non omnis natus fugi', 'hasuv@mailinator.com', 'Dignissimos ipsa to', 'Labore dolorem fugit', 'active', '2026-10-01 15:21:23', '2026-10-01 15:21:23', NULL),
('01a0fde5-35c9-7276-a62b-6e10ca9595de', 'Asperiores mollitia', 'Eos eaque cupidatat', 'deqanu@mailinator.com', 'Ut in quia non offic', 'Accusamus eos nihil', 'active', '2026-10-02 12:34:12', '2026-10-02 12:34:12', NULL),
('01a0fde6-1ca4-7032-b146-a051c0190a1f', 'Ut iste doloribus cu', 'Et dolor ullam persp', 'fyjojy@mailinator.com', 'Et maiores libero im', 'Rerum vel ullam poss', 'inactive', '2026-10-02 12:35:11', '2026-10-02 12:35:11', NULL),
('01a10332-7069-73a7-ba25-116f100370a1', 'Quas molestiae amet', 'Occaecat molestias o', 'vosuq@mailinator.com', '+1 (295) 471-9919', 'Quod nesciunt amet', 'active', '2026-10-03 13:16:39', '2026-10-03 13:16:39', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `deals`
--

CREATE TABLE `deals` (
  `id` char(36) NOT NULL,
  `title` varchar(160) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'USD',
  `stage` varchar(20) NOT NULL DEFAULT 'new',
  `expected_close_date` date DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `contact_id` char(36) NOT NULL,
  `pipeline_id` char(36) DEFAULT NULL,
  `pipeline_stage_id` char(36) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `deals`
--

INSERT INTO `deals` (`id`, `title`, `amount`, `currency`, `stage`, `expected_close_date`, `closed_at`, `contact_id`, `pipeline_id`, `pipeline_stage_id`, `created_at`, `updated_at`) VALUES
('01a0f946-8130-714d-889f-92ae955c58c9', 'Harum in incidunt s', 62.00, 'ASP', 'new', '2005-11-16', NULL, '01a0f945-4b34-7159-81de-820a55bc50df', NULL, NULL, '2026-10-01 15:02:22', '2026-10-01 15:02:22'),
('01a0f946-a539-7107-9da7-5807f7c41adf', 'Harum in incidunt s', 62.00, 'ASP', 'new', '2005-11-16', NULL, '01a0f945-cae6-71e2-b685-0175d1b37f19', NULL, NULL, '2026-10-01 15:02:31', '2026-10-01 15:02:31'),
('01a0f94f-9ebb-7183-a72d-375bcf322cfc', 'Sit blanditiis sit', 33.00, 'PLA', 'new', '1978-07-06', NULL, '01a0f945-4b34-7159-81de-820a55bc50df', NULL, NULL, '2026-10-01 15:12:19', '2026-10-01 15:12:19'),
('01a0fddf-c2e5-7266-a7c0-d9d86b68fdf5', 'Est recusandae Magn', 70.00, 'SIT', 'new', '1998-01-28', NULL, '01a0f954-d28f-70bd-842b-ad4466e34c5a', NULL, NULL, '2026-10-02 12:28:15', '2026-10-02 12:28:15'),
('01a0fde3-cc05-736f-99c3-4ec9bc8dadf2', 'Et laboriosam aliqu', 59.00, 'ANI', 'new', '1980-03-21', NULL, '01a0f956-b33e-7110-9b7d-f56d1c34a96a', NULL, NULL, '2026-10-02 12:32:39', '2026-10-02 12:32:39'),
('01a0fde4-cd32-73a1-9fa2-10166e44776a', 'Quia incidunt elige', 75.00, 'ERR', 'new', '2006-07-07', NULL, '01a0f955-f484-736d-87ef-84d8f8b9523d', NULL, NULL, '2026-10-02 12:33:45', '2026-10-02 12:33:45'),
('01a0fde6-4498-700c-b9e2-9644a5271f68', 'Omnis numquam pariat', 19.00, 'EUM', 'new', '2005-11-16', NULL, '01a0f945-cae6-71e2-b685-0175d1b37f19', NULL, NULL, '2026-10-02 12:35:21', '2026-10-02 12:35:21'),
('01a10267-a164-73cd-b43a-e82d9ecc193d', 'Lorem doloremque et', 37.00, 'UTC', 'new', '1982-02-01', NULL, '01a0f945-4b34-7159-81de-820a55bc50df', NULL, NULL, '2026-10-03 09:35:08', '2026-10-03 09:35:08');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` char(36) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `email` varchar(254) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `company` varchar(120) DEFAULT NULL,
  `job_title` varchar(120) DEFAULT NULL,
  `source` varchar(30) NOT NULL DEFAULT 'other',
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `score` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `estimated_value` decimal(14,2) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'USD',
  `notes` text DEFAULT NULL,
  `assigned_to` bigint(20) UNSIGNED DEFAULT NULL,
  `converted_contact_id` char(36) DEFAULT NULL,
  `last_contacted_at` timestamp NULL DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`id`, `first_name`, `last_name`, `email`, `phone`, `company`, `job_title`, `source`, `status`, `score`, `estimated_value`, `currency`, `notes`, `assigned_to`, `converted_contact_id`, `last_contacted_at`, `converted_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
('01a10266-3a82-71bb-a2bb-3e8fbe8b2d14', 'Sed commodi ipsum b', 'Id quidem reprehend', 'vubewy@mailinator.com', 'Blanditiis dolor eve', 'Quia laudantium ani', 'Esse dolores dolorem', 'other', 'new', 25, 50.00, 'ALI', 'Numquam dolore est', 4, NULL, NULL, NULL, '2026-10-03 09:33:36', '2026-10-03 09:34:23', '2026-10-03 09:34:23'),
('01a10266-c1a4-7370-9f27-3f7c7fca6a36', 'Lorem magna dolorem', 'Aut cupiditate optio', 'lide@mailinator.com', 'Sit beatae quo tota', 'Odit non commodi aut', 'Repudiandae nulla be', 'website', 'new', 55, 43.00, 'OBC', 'Voluptates qui est', 4, NULL, NULL, NULL, '2026-10-03 09:34:11', '2026-10-03 09:34:27', '2026-10-03 09:34:27'),
('01a10267-290f-7336-895c-e52afdc5d132', 'Aut officiis dolores', 'Id exercitationem oc', 'gyco@mailinator.com', 'Quasi amet eveniet', 'Itaque corrupti qui', 'Id et dicta tenetur', 'email_campaign', 'new', 65, 55.00, 'QUO', 'Ea ex reiciendis lab', NULL, NULL, NULL, NULL, '2026-10-03 09:34:37', '2026-10-03 09:51:26', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2025_01_01_000001_create_contacts_table', 1),
(5, '2025_01_01_000002_create_deals_table', 1),
(6, '2026_09_27_194221_create_clients_table', 1),
(7, '2026_09_28_134844_create_notifications_table', 1),
(8, '2026_09_28_153406_create_personal_access_tokens_table', 1),
(9, '2026_09_28_154409_create_permission_tables', 1),
(10, '2026_09_28_154548_create_companies_table', 1),
(12, '2025_01_01_000003_create_leads_table', 2),
(13, '2026_10_03_000010_create_pipelines_table', 3),
(14, '2026_10_03_000011_create_pipeline_stages_table', 3),
(15, '2026_10_03_000012_add_pipeline_to_deals_table', 3);

-- --------------------------------------------------------

--
-- Table structure for table `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) UNSIGNED NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `personal_access_tokens`
--

INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(2, 'App\\Models\\User', 4, 'spa', 'bde0976a7dc2431aefe195cc1fee07ce69d61b98293658b5d9de13c291cfb66b', '[\"*\"]', '2026-10-01 14:49:27', NULL, '2026-10-01 14:48:15', '2026-10-01 14:49:27'),
(3, 'App\\Models\\User', 4, 'spa', '4c3e504b9838d6848e22544f76cc8b7b44868f19b5c9a2ddfee2b7f484d399ca', '[\"*\"]', '2026-10-01 15:01:00', NULL, '2026-10-01 14:59:02', '2026-10-01 15:01:00'),
(4, 'App\\Models\\User', 4, 'spa', 'e9879d2b0df4caab315b170a89aee88214e618a6c158ec93bdcb82bfed192cb5', '[\"*\"]', '2026-10-03 13:17:14', NULL, '2026-10-01 15:01:21', '2026-10-03 13:17:14'),
(5, 'App\\Models\\User', 4, 'spa', 'b974e0803c23aa6acd56906e391cf9623d8984fcb931c11bef23923c7229f5a9', '[\"*\"]', '2026-10-03 09:33:08', NULL, '2026-10-03 09:24:24', '2026-10-03 09:33:08');

-- --------------------------------------------------------

--
-- Table structure for table `pipelines`
--

CREATE TABLE `pipelines` (
  `id` char(36) NOT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `description` varchar(1000) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pipelines`
--

INSERT INTO `pipelines` (`id`, `name`, `slug`, `description`, `is_default`, `is_active`, `created_at`, `updated_at`) VALUES
('01a10318-fe87-70c0-a0d5-4af5c3ec6d1d', 'Corporis vero tempor', 'corporis-vero-tempor', 'Eius laborum Libero', 0, 0, '2026-10-03 12:48:52', '2026-10-03 12:52:04'),
('01a1031b-ed05-70b0-b11e-db8ee856e920', 'Quis temporibus reru', 'quis-temporibus-reru', 'Corporis magnam aute', 1, 0, '2026-10-03 12:52:04', '2026-10-03 12:52:04'),
('01a1031f-9f1c-709e-855c-0838358923d6', 'Nulla dolores cumque', 'nulla-dolores-cumque', 'Incidunt voluptatum', 0, 1, '2026-10-03 12:56:06', '2026-10-03 12:56:06');

-- --------------------------------------------------------

--
-- Table structure for table `pipeline_stages`
--

CREATE TABLE `pipeline_stages` (
  `id` char(36) NOT NULL,
  `pipeline_id` char(36) NOT NULL,
  `name` varchar(80) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `position` smallint(5) UNSIGNED NOT NULL,
  `color` varchar(30) NOT NULL DEFAULT 'secondary',
  `probability` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `is_won` tinyint(1) NOT NULL DEFAULT 0,
  `is_lost` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pipeline_stages`
--

INSERT INTO `pipeline_stages` (`id`, `pipeline_id`, `name`, `slug`, `position`, `color`, `probability`, `is_won`, `is_lost`, `created_at`, `updated_at`) VALUES
('01a1031c-40c0-702d-949a-73bf71376f46', '01a1031b-ed05-70b0-b11e-db8ee856e920', 'CXCXZCZX', 'cxcxzczx', 1, 'primary', 0, 1, 0, '2026-10-03 12:52:25', '2026-10-03 12:52:25'),
('01a1031f-f236-714b-a5f2-2ef76e67dab9', '01a1031f-9f1c-709e-855c-0838358923d6', 'HFDHGFD', 'hfdhgfd', 1, 'secondary', 0, 1, 0, '2026-10-03 12:56:27', '2026-10-03 12:56:27');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_has_permissions`
--

CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('3ZP0rzVfHGAgHMCM3feB7jpl6hrXBSXCMThAMx5Z', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiZTdKTDJEd0YzMHNSVnExVWlZNEJlZER6ekl2dDltaWZKWmJiWHhMTCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9waXBlbGluZXMiO3M6NToicm91dGUiO047fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1791053460);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
(4, 'Admin', 'zillurrahman3052@gmail.com', '2026-10-01 14:33:35', '$2y$12$jNbacDNIma/k5se6dE8qZO.c/hj8tkyaz6syRwBGXXNJySiVfOH.K', 'NqTRu1fKRP', '2026-10-01 14:33:36', '2026-10-01 14:33:36'),
(5, 'Admin', 'zillurrahman3053@gmail.com', '2026-10-03 12:43:24', '$2y$12$gXBkH/PewoMacucUJzLS.e6Rr1mpsABKLB36nuTnyH.VXBNYR5p.u', 'hdUiqAolEf', '2026-10-03 12:43:24', '2026-10-03 12:43:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `clients_assigned_to_foreign` (`assigned_to`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `companies_owner_id_foreign` (`owner_id`),
  ADD KEY `companies_parent_id_foreign` (`parent_id`),
  ADD KEY `companies_name_index` (`name`);

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `contacts_email_unique` (`email`),
  ADD KEY `contacts_status_index` (`status`),
  ADD KEY `contacts_last_name_first_name_index` (`last_name`,`first_name`);

--
-- Indexes for table `deals`
--
ALTER TABLE `deals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `deals_stage_index` (`stage`),
  ADD KEY `deals_contact_id_stage_index` (`contact_id`,`stage`),
  ADD KEY `deals_created_at_index` (`created_at`),
  ADD KEY `deals_pipeline_stage_id_foreign` (`pipeline_stage_id`),
  ADD KEY `deals_pipeline_id_pipeline_stage_id_index` (`pipeline_id`,`pipeline_stage_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `leads_converted_contact_id_foreign` (`converted_contact_id`),
  ADD KEY `leads_email_index` (`email`),
  ADD KEY `leads_status_created_at_index` (`status`,`created_at`),
  ADD KEY `leads_source_index` (`source`),
  ADD KEY `leads_assigned_to_status_index` (`assigned_to`,`status`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  ADD KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  ADD KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `pipelines`
--
ALTER TABLE `pipelines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pipelines_slug_unique` (`slug`),
  ADD KEY `pipelines_is_active_is_default_index` (`is_active`,`is_default`);

--
-- Indexes for table `pipeline_stages`
--
ALTER TABLE `pipeline_stages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pipeline_stages_pipeline_id_slug_unique` (`pipeline_id`,`slug`),
  ADD UNIQUE KEY `pipeline_stages_pipeline_id_position_unique` (`pipeline_id`,`position`),
  ADD KEY `pipeline_stages_pipeline_id_position_index` (`pipeline_id`,`position`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`role_id`),
  ADD KEY `role_has_permissions_role_id_foreign` (`role_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `clients_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `companies`
--
ALTER TABLE `companies`
  ADD CONSTRAINT `companies_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `companies_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `deals`
--
ALTER TABLE `deals`
  ADD CONSTRAINT `deals_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`),
  ADD CONSTRAINT `deals_pipeline_id_foreign` FOREIGN KEY (`pipeline_id`) REFERENCES `pipelines` (`id`),
  ADD CONSTRAINT `deals_pipeline_stage_id_foreign` FOREIGN KEY (`pipeline_stage_id`) REFERENCES `pipeline_stages` (`id`);

--
-- Constraints for table `leads`
--
ALTER TABLE `leads`
  ADD CONSTRAINT `leads_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `leads_converted_contact_id_foreign` FOREIGN KEY (`converted_contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pipeline_stages`
--
ALTER TABLE `pipeline_stages`
  ADD CONSTRAINT `pipeline_stages_pipeline_id_foreign` FOREIGN KEY (`pipeline_id`) REFERENCES `pipelines` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
