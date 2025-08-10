-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Gegenereerd op: 15 jul 2025 om 16:37
-- Serverversie: 10.4.28-MariaDB
-- PHP-versie: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `themeparkdb`
--

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `accounts`
--

INSERT INTO `accounts` (`id`, `name`, `contact_email`, `created_at`) VALUES
(1, 'Fantasy Kingdom', 'contact@fantasykingdom.com', '2025-07-15 12:47:03'),
(2, '\'s Theme Park', NULL, '2025-07-15 13:18:18'),
(3, '\'s Theme Park', NULL, '2025-07-15 13:18:26'),
(4, '\'s Theme Park', NULL, '2025-07-15 13:18:28');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `problem_notes`
--

CREATE TABLE `problem_notes` (
  `id` int(11) NOT NULL,
  `problem_id` int(11) DEFAULT NULL,
  `author_id` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `problem_notes`
--

INSERT INTO `problem_notes` (`id`, `problem_id`, `author_id`, `note`, `created_at`) VALUES
(1, 1, 2, 'Maintenance has been notified.', '2025-07-15 12:47:03'),
(2, 2, 2, 'Cleaning crew dispatched.', '2025-07-15 12:47:03'),
(3, 3, 1, 'Issue resolved with guest services.', '2025-07-15 12:47:03'),
(4, 4, 1, 'This was checked and found to be not critical.', '2025-07-15 12:47:03');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `problem_reports`
--

CREATE TABLE `problem_reports` (
  `id` int(11) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `attachment_url` varchar(255) DEFAULT NULL,
  `status` enum('open','in_progress','resolved','wont_resolve') DEFAULT 'open',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `problem_reports`
--

INSERT INTO `problem_reports` (`id`, `account_id`, `submitted_by`, `category`, `description`, `attachment_url`, `status`, `submitted_at`, `updated_at`) VALUES
(1, 1, 4, 'Ride Malfunction', 'The Dragon Coaster stopped mid-ride.', NULL, 'open', '2025-07-15 12:47:03', '2025-07-15 12:47:03'),
(2, 1, 4, 'Trash Overflow', 'Trash bin near entrance is overflowing.', NULL, 'wont_resolve', '2025-07-15 12:47:03', '2025-07-15 14:03:34'),
(3, 1, 4, 'Guest Complaint', 'A guest was upset about a long wait time.', NULL, 'resolved', '2025-07-15 12:47:03', '2025-07-15 12:47:03'),
(4, 1, 4, 'Safety Concern', 'Loose bolt spotted on Ferris wheel.', NULL, 'wont_resolve', '2025-07-15 12:47:03', '2025-07-15 12:47:03'),
(5, 1, 5, 'Trash Overflow', '54ew5', '', 'wont_resolve', '2025-07-15 14:03:04', '2025-07-15 14:03:38');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `roles`
--

INSERT INTO `roles` (`id`, `name`) VALUES
(1, 'admin'),
(4, 'attractions_operator'),
(2, 'manager'),
(3, 'ticket_sales');

-- --------------------------------------------------------

--
-- Table structure for table `role_rights`
--

CREATE TABLE `role_rights` (
  `role_id` int(11) NOT NULL,
  `right_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_rights`
--

INSERT INTO `role_rights` (`role_id`, `right_name`) VALUES
(1, 'settings'),
(1, 'rosters'),
(1, 'tickets'),
(1, 'my_roster'),
(1, 'analytics'),
(1, 'maintenance'),
(1, 'report_problem'),
(1, 'admin_problem'),
(1, 'user_management'),
(1, 'roles_management'),
(1, 'logout'),
(1, 'daily_operations'),
(2, 'rosters'),
(2, 'tickets'),
(2, 'my_roster'),
(2, 'maintenance'),
(2, 'report_problem'),
(2, 'logout'),
(3, 'tickets'),
(3, 'logout'),
(4, 'my_roster'),
(4, 'report_problem'),
(4, 'logout');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `sales`
--

INSERT INTO `sales` (`id`, `user_id`, `ticket_id`, `quantity`, `sale_date`) VALUES
(1, 3, 1, 3, '2025-07-15 12:47:03'),
(2, 3, 2, 1, '2025-07-15 12:47:03'),
(3, 5, 1, 1, '2025-07-15 13:50:56'),
(4, 5, 4, 1, '2025-07-15 13:51:11');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `shift_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `shifts`
--

INSERT INTO `shifts` (`id`, `user_id`, `shift_date`, `start_time`, `end_time`) VALUES
(1, 4, '2025-07-15', '09:00:00', '13:00:00'),
(2, 4, '2025-07-16', '13:00:00', '17:00:00'),
(3, 5, '2025-07-17', '17:46:00', '19:48:00'),
(4, 7, '2025-07-16', '01:01:00', '02:02:00');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `available_quantity` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `tickets`
--

INSERT INTO `tickets` (`id`, `account_id`, `name`, `price`, `available_quantity`, `created_at`) VALUES
(1, 1, 'Day Pass', 35.00, 499, '2025-07-15 12:47:03'),
(2, 1, 'VIP Pass', 60.00, 100, '2025-07-15 12:47:03'),
(4, 1, 'new', 1.00, 0, '2025-07-15 13:51:07');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `ticket_discounts`
--

CREATE TABLE `ticket_discounts` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `discount_percent` decimal(5,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `ticket_discounts`
--

INSERT INTO `ticket_discounts` (`id`, `ticket_id`, `start_datetime`, `end_datetime`, `discount_percent`) VALUES
(1, 1, '2025-12-01 09:00:00', '2025-12-01 12:00:00', 20.00);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `users`
--

INSERT INTO `users` (`id`, `account_id`, `role_id`, `username`, `email`, `password_hash`, `created_at`) VALUES
(1, 1, 1, 'admin_user', 'admin@fantasykingdom.com', 'hashed_pw_admin', '2025-07-15 12:47:03'),
(2, 1, 2, 'manager_user', 'manager@fantasykingdom.com', 'hashed_pw_mgr', '2025-07-15 12:47:03'),
(3, 1, 3, 'sales_user', 'sales@fantasykingdom.com', 'hashed_pw_sales', '2025-07-15 12:47:03'),
(4, 1, 4, 'operator_user', 'operator@fantasykingdom.com', 'hashed_pw_op', '2025-07-15 12:47:03'),
(5, 1, 1, 'test', 'info@thexssrat.com', '$2y$10$MS6RZGufMZt/ubCxwmj4pOK3kw6o/3lJyl5vh.gOQAoNd7xUpikYa', '2025-07-15 13:03:51'),
(6, 3, 1, 'test3', 'info@thexssrat.com', '$2y$10$4gjeZJK8P6o/L0Pzq2eTfOdhzZ3BCTcD392Fkl.m5SPlYFMDvkzp6', '2025-07-15 13:18:26'),
(7, 1, 4, 'low', 'low@thexssrat.com', '$2y$10$XN5axlfiLHkmoAVm0jPy6uWrxZ0OyY1E9JPKSlpDKQA.Bvp4BTAFm', '2025-07-15 13:45:32'),
(8, 1, 2, 'man', 'man@thexss.com', '$2y$10$Mljoo5mQo8zxd5hI0eu60usZPGdPBfcaV.dRWslSb215DP4FS6TWm', '2025-07-15 13:46:54');

-- --------------------------------------------------------

-- Table structure for table `daily_operations`
--

CREATE TABLE `daily_operations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `operation_date` date NOT NULL,
  `guest_count` int(11) DEFAULT NULL,
  `weather` varchar(50) DEFAULT NULL,
  `incoming_money` decimal(10,2) DEFAULT NULL,
  `outgoing_money` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT NULL,
  `land` int(11) DEFAULT 0,
  `attractions` int(11) DEFAULT 0,
  `stalls` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexen voor geëxporteerde tabellen
--

--
-- Indexen voor tabel `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `problem_notes`
--
ALTER TABLE `problem_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `problem_id` (`problem_id`),
  ADD KEY `author_id` (`author_id`);

--
-- Indexen voor tabel `problem_reports`
--
ALTER TABLE `problem_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `submitted_by` (`submitted_by`);

--
-- Indexen voor tabel `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexen voor tabel `role_rights`
--
ALTER TABLE `role_rights`
  ADD KEY `role_id` (`role_id`);

--
-- Indexen voor tabel `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `ticket_id` (`ticket_id`);

--
-- Indexen voor tabel `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexen voor tabel `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`);

-- Indexen voor tabel `ticket_discounts`
ALTER TABLE `ticket_discounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`);

--
-- Indexen voor tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `role_id` (`role_id`);

-- Indexen voor tabel `daily_operations`
ALTER TABLE `daily_operations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id_operation_date` (`user_id`,`operation_date`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT voor geëxporteerde tabellen
--

--
-- AUTO_INCREMENT voor een tabel `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT voor een tabel `problem_notes`
--
ALTER TABLE `problem_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT voor een tabel `problem_reports`
--
ALTER TABLE `problem_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT voor een tabel `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT voor een tabel `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT voor een tabel `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT voor een tabel `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

-- AUTO_INCREMENT voor een tabel `ticket_discounts`
ALTER TABLE `ticket_discounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT voor een tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

-- AUTO_INCREMENT voor een tabel `daily_operations`
--
ALTER TABLE `daily_operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Beperkingen voor geëxporteerde tabellen
--

--
-- Beperkingen voor tabel `problem_notes`
--
ALTER TABLE `problem_notes`
  ADD CONSTRAINT `problem_notes_ibfk_1` FOREIGN KEY (`problem_id`) REFERENCES `problem_reports` (`id`),
  ADD CONSTRAINT `problem_notes_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`);

--
-- Beperkingen voor tabel `problem_reports`
--
ALTER TABLE `problem_reports`
  ADD CONSTRAINT `problem_reports_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `problem_reports_ibfk_2` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`);

--
-- Beperkingen voor tabel `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`);

--
-- Beperkingen voor tabel `shifts`
--
ALTER TABLE `shifts`
  ADD CONSTRAINT `shifts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

-- Beperkingen voor tabel `ticket_discounts`
ALTER TABLE `ticket_discounts`
  ADD CONSTRAINT `ticket_discounts_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

-- Beperkingen voor tabel `daily_operations`
ALTER TABLE `daily_operations`
  ADD CONSTRAINT `daily_operations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `role_rights`
--
ALTER TABLE `role_rights`
  ADD CONSTRAINT `role_rights_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
