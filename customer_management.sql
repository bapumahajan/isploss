-- phpMyAdmin SQL Dump
-- version 5.2.2-1.fc42
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 09, 2025 at 11:10 PM
-- Server version: 10.11.11-MariaDB
-- PHP Version: 8.4.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `customer_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `access_control`
--

CREATE TABLE `access_control` (
  `id` int(11) NOT NULL,
  `role` enum('admin','editor','viewer') NOT NULL,
  `module_id` int(11) NOT NULL,
  `permission` enum('view','edit','none') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `circuit_ips`
--

CREATE TABLE `circuit_ips` (
  `circuit_id` varchar(20) NOT NULL,
  `ip_address` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `circuit_network_details`
--

CREATE TABLE `circuit_network_details` (
  `circuit_id` varchar(20) NOT NULL,
  `installation_date` date DEFAULT NULL,
  `wan_ip` varchar(45) NOT NULL,
  `wan_gateway` varchar(45) NOT NULL,
  `dns1` varchar(45) NOT NULL,
  `dns2` varchar(45) DEFAULT NULL,
  `auth_type` enum('Static','PPPoE') NOT NULL DEFAULT 'Static',
  `PPPoE_auth_username` varchar(255) DEFAULT NULL,
  `PPPoE_auth_password` varchar(255) DEFAULT NULL,
  `cacti_url` varchar(255) DEFAULT NULL,
  `cacti_username` varchar(255) DEFAULT NULL,
  `cacti_password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_basic_information`
--

CREATE TABLE `customer_basic_information` (
  `circuit_id` varchar(20) NOT NULL,
  `organization_name` varchar(255) DEFAULT NULL,
  `customer_address` mediumtext DEFAULT NULL,
  `City` varchar(255) NOT NULL,
  `contact_person_name` varchar(255) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `ce_email_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `device_types`
--

CREATE TABLE `device_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `id` int(11) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `network_details`
--

CREATE TABLE `network_details` (
  `id` int(11) NOT NULL,
  `circuit_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `product_type` enum('ILL','EBB','Point-To-Point') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `pop_name` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `pop_ip` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `switch_name` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `switch_ip` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `switch_port` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `bandwidth` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `circuit_status` enum('Active','Terminated','Suspended') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `vlan` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `network_inventory`
--

CREATE TABLE `network_inventory` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `type` enum('POP','SWITCH') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pop_inventory`
--

CREATE TABLE `pop_inventory` (
  `id` int(11) NOT NULL,
  `pop_name` varchar(255) DEFAULT NULL,
  `pop_ip` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `switch_inventory`
--

CREATE TABLE `switch_inventory` (
  `id` int(11) NOT NULL,
  `switch_name` varchar(255) DEFAULT NULL,
  `switch_ip` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `pop_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user','viewer','editor','manager') NOT NULL,
  `is_active` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `access_control`
--
ALTER TABLE `access_control`
  ADD PRIMARY KEY (`id`),
  ADD KEY `module_id` (`module_id`);

--
-- Indexes for table `circuit_ips`
--
ALTER TABLE `circuit_ips`
  ADD PRIMARY KEY (`circuit_id`,`ip_address`);

--
-- Indexes for table `circuit_network_details`
--
ALTER TABLE `circuit_network_details`
  ADD PRIMARY KEY (`circuit_id`);

--
-- Indexes for table `customer_basic_information`
--
ALTER TABLE `customer_basic_information`
  ADD PRIMARY KEY (`circuit_id`),
  ADD UNIQUE KEY `circuit_id` (`circuit_id`),
  ADD UNIQUE KEY `circuit_id_2` (`circuit_id`),
  ADD UNIQUE KEY `circuit_id_3` (`circuit_id`),
  ADD UNIQUE KEY `circuit_id_4` (`circuit_id`);

--
-- Indexes for table `device_types`
--
ALTER TABLE `device_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type_name` (`type_name`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip_address` (`ip_address`),
  ADD KEY `attempt_time` (`attempt_time`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `network_details`
--
ALTER TABLE `network_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_circuit_id` (`circuit_id`);

--
-- Indexes for table `network_inventory`
--
ALTER TABLE `network_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ip` (`ip`),
  ADD UNIQUE KEY `ip` (`ip`);

--
-- Indexes for table `pop_inventory`
--
ALTER TABLE `pop_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip` (`pop_ip`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `switch_inventory`
--
ALTER TABLE `switch_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip` (`switch_ip`),
  ADD KEY `pop_id` (`pop_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `access_control`
--
ALTER TABLE `access_control`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `device_types`
--
ALTER TABLE `device_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `network_details`
--
ALTER TABLE `network_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `network_inventory`
--
ALTER TABLE `network_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pop_inventory`
--
ALTER TABLE `pop_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `switch_inventory`
--
ALTER TABLE `switch_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `access_control`
--
ALTER TABLE `access_control`
  ADD CONSTRAINT `access_control_ibfk_1` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `circuit_ips`
--
ALTER TABLE `circuit_ips`
  ADD CONSTRAINT `circuit_ips_ibfk_1` FOREIGN KEY (`circuit_id`) REFERENCES `network_details` (`circuit_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `circuit_network_details`
--
ALTER TABLE `circuit_network_details`
  ADD CONSTRAINT `fk_circuit_id` FOREIGN KEY (`circuit_id`) REFERENCES `customer_basic_information` (`circuit_id`),
  ADD CONSTRAINT `fk_cnd_cid` FOREIGN KEY (`circuit_id`) REFERENCES `network_details` (`circuit_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `switch_inventory`
--
ALTER TABLE `switch_inventory`
  ADD CONSTRAINT `switch_inventory_ibfk_1` FOREIGN KEY (`pop_id`) REFERENCES `pop_inventory` (`id`);

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
