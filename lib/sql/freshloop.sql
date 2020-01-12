-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Erstellungszeit: 08. Sep 2019 um 12:02
-- Server-Version: 10.1.26-MariaDB
-- PHP-Version: 7.1.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `freshloop`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `fragrances`
--

CREATE TABLE `fragrances` (
  `fragrance_id` int(12) NOT NULL,
  `fragrance_name` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `fragrance_scent` varchar(255) CHARACTER SET utf8 NOT NULL,
  `fragrance_position` int(12) NOT NULL,
  `fragrance_inUseSince` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Daten für Tabelle `fragrances`
--

INSERT INTO `fragrances` (`fragrance_id`, `fragrance_name`, `fragrance_scent`, `fragrance_position`, `fragrance_inUseSince`) VALUES
(1, 'test', 'test', 1, '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mods`
--

CREATE TABLE `mods` (
  `mod_id` int(12) NOT NULL,
  `mod_name` varchar(255) NOT NULL,
  `mod_status` tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Daten für Tabelle `mods`
--

INSERT INTO `mods` (`mod_id`, `mod_name`, `mod_status`) VALUES
(1, 'mod_auth', 1),
(2, 'mod_fragranceCollective', 0),
(3, 'mod_fragranceChoice', 1),
(4, 'mod_sensorResponder', 0),
(5, 'mod_time', 0);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mod_fragrancechoice`
--

CREATE TABLE `mod_fragrancechoice` (
  `fragrance_id` int(12) NOT NULL,
  `fragrance_name` varchar(255) CHARACTER SET utf8 NOT NULL,
  `fragrance_scent` varchar(255) CHARACTER SET utf8 NOT NULL,
  `fragrance_unit` int(12) DEFAULT NULL,
  `fragrance_position` int(12) DEFAULT NULL,
  `fragrance_isGeneralFragrance` tinyint(1) NOT NULL DEFAULT '0',
  `fragrance_createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Daten für Tabelle `mod_fragrancechoice`
--

INSERT INTO `mod_fragrancechoice` (`fragrance_id`, `fragrance_name`, `fragrance_scent`, `fragrance_unit`, `fragrance_position`, `fragrance_isGeneralFragrance`, `fragrance_createdAt`) VALUES
(5, 'Morgenduft', 'Vanille', NULL, NULL, 1, '2019-06-28 17:49:38'),
(6, 'Mittagsduft', 'Frucht', NULL, NULL, 1, '2019-06-28 17:49:38'),
(33, 'Mittagsduft', 'Frucht', 12, 2, 0, '2019-07-06 10:10:52'),
(34, 'Morgenduft', 'Vanille', 12, 1, 0, '2019-07-06 10:13:05'),
(35, 'Morgenduft', 'Vanille', 12, 2, 0, '2019-07-16 13:23:14'),
(36, 'Mittagsduft', 'Frucht', 12, 2, 0, '2019-07-16 13:23:23');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mod_fragrancecollective`
--

CREATE TABLE `mod_fragrancecollective` (
  `ref_id` int(12) NOT NULL,
  `ref_event_id` int(12) DEFAULT NULL,
  `ref_fragrance_id` int(12) DEFAULT NULL,
  `ref_createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Daten für Tabelle `mod_fragrancecollective`
--

INSERT INTO `mod_fragrancecollective` (`ref_id`, `ref_event_id`, `ref_fragrance_id`, `ref_createdAt`) VALUES
(3, 25, 5, '2019-09-04 21:45:39');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mod_time`
--

CREATE TABLE `mod_time` (
  `event_id` int(12) NOT NULL,
  `event_name` varchar(255) CHARACTER SET utf8 NOT NULL,
  `event_type` int(12) NOT NULL,
  `event_targetUnits` varchar(255) CHARACTER SET utf8 NOT NULL,
  `event_fragrance_id` int(12) DEFAULT NULL,
  `event_loop` tinyint(1) NOT NULL DEFAULT '0',
  `event_plannedExecution_time` time NOT NULL,
  `event_plannedExecution_date` date NOT NULL,
  `event_createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `event_executedAt` datetime DEFAULT NULL,
  `event_executed_status` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Daten für Tabelle `mod_time`
--

INSERT INTO `mod_time` (`event_id`, `event_name`, `event_type`, `event_targetUnits`, `event_fragrance_id`, `event_loop`, `event_plannedExecution_time`, `event_plannedExecution_date`, `event_createdAt`, `event_executedAt`, `event_executed_status`) VALUES
(11, 'Morgen', 0, '12', 5, 1, '08:00:00', '2019-06-19', '2019-06-07 19:45:50', NULL, NULL),
(13, 'Abendroutine', 0, '12', 5, 0, '18:00:00', '2019-06-11', '2019-06-08 15:41:28', '2019-06-08 15:41:28', NULL),
(14, 'Mittag', 0, '12', 5, 1, '13:00:00', '2019-06-19', '2019-06-08 15:59:38', '2019-06-08 15:59:38', NULL),
(24, 'Nachmittag', 2, '12', 6, 0, '10:00:00', '2019-06-24', '2019-06-09 17:08:48', '2019-06-09 17:08:48', NULL),
(25, 'dasdas', 3, '12', NULL, 0, '00:40:00', '2019-08-12', '2019-08-10 10:13:09', NULL, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `units`
--

CREATE TABLE `units` (
  `unit_id` int(12) NOT NULL,
  `unit_name` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `unit_isHub` tinyint(1) NOT NULL DEFAULT '0',
  `unit_room` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `unit_priority` int(12) DEFAULT NULL,
  `unit_isActivated` tinyint(1) NOT NULL,
  `unit_timesSprayed` int(12) DEFAULT NULL,
  `unit_lastSprayed` datetime DEFAULT NULL,
  `unit_img` varchar(255) CHARACTER SET utf8 NOT NULL,
  `unit_createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Daten für Tabelle `units`
--

INSERT INTO `units` (`unit_id`, `unit_name`, `unit_isHub`, `unit_room`, `unit_priority`, `unit_isActivated`, `unit_timesSprayed`, `unit_lastSprayed`, `unit_img`, `unit_createdAt`) VALUES
(10, 'Einheit 1', 1, 'Schlafzimmer', 100, 0, 0, NULL, '', '2019-06-07 14:42:29'),
(12, 'Einheit 2', 0, 'Wohnzimmer', 50, 1, NULL, NULL, '', '2019-06-07 19:35:23');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(249) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(2) UNSIGNED NOT NULL DEFAULT '0',
  `verified` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `resettable` tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
  `roles_mask` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `registered` int(10) UNSIGNED NOT NULL,
  `last_login` int(10) UNSIGNED DEFAULT NULL,
  `force_logout` mediumint(7) UNSIGNED NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `username`, `status`, `verified`, `resettable`, `roles_mask`, `registered`, `last_login`, `force_logout`, `created_at`) VALUES
(25, 'test@test.de', '$2y$10$aniVReBgy/NQuhkmIHAzTe3HtBvN/RgQFUVtGWOmXEiKD7NG8tMYC', 'test', 0, 1, 1, 16, 0, 1567876641, 4, '0000-00-00 00:00:00'),
(24, 'Max@Mustermann.de', '$2y$10$/JBqGm.UYxw3QQ5kA1.j6eXVAFmZZpuOYn5FLnoO7c0xPT.VzwONa', 'Max Mustermann', 0, 1, 1, 16, 0, 1564256532, 0, '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users_confirmations`
--

CREATE TABLE `users_confirmations` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `email` varchar(249) COLLATE utf8mb4_unicode_ci NOT NULL,
  `selector` varchar(16) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `token` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `expires` int(10) UNSIGNED NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `users_confirmations`
--

INSERT INTO `users_confirmations` (`id`, `user_id`, `email`, `selector`, `token`, `expires`) VALUES
(25, 25, 'test@test.des', 'X0PGADAL4N2bxlTS', '$2y$10$y/kH3sbQ4.oxl9do0pVFm.fwWN7ShUwOouzeAz8Y1CLtzpm40GPZC', 1562775765);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users_remembered`
--

CREATE TABLE `users_remembered` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user` int(10) UNSIGNED NOT NULL,
  `selector` varchar(24) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `token` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `expires` int(10) UNSIGNED NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users_resets`
--

CREATE TABLE `users_resets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user` int(10) UNSIGNED NOT NULL,
  `selector` varchar(20) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `token` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `expires` int(10) UNSIGNED NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `users_resets`
--

INSERT INTO `users_resets` (`id`, `user`, `selector`, `token`, `expires`) VALUES
(8, 25, 'SEvYH6-qMBn_lRD8JERu', '$2y$10$hNh3BRJFN5OunLkn.zyp/eAh2YH5jKFNUKAgpg9VettNSF7b7amIG', 1561079326);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users_throttling`
--

CREATE TABLE `users_throttling` (
  `bucket` varchar(44) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `tokens` float UNSIGNED NOT NULL,
  `replenished_at` int(10) UNSIGNED NOT NULL,
  `expires_at` int(10) UNSIGNED NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `users_throttling`
--

INSERT INTO `users_throttling` (`bucket`, `tokens`, `replenished_at`, `expires_at`) VALUES
('VRApYyw5ttbUTCgkCi9c_eofcTJc8uiChFmq4lDm5tE', 24.0133, 1561057750, 1561129750),
('pFy2Fy1ViGnRXNfmgZTtfvYeZS0oHD5xy1hdTaiUbxE', 24.0133, 1561057750, 1561129750),
('Ncoz2MCff3trViQs3jCwfWIXeUUHU5qGXIq7OhmxgI8', 44.0222, 1561057750, 1561129750),
('VfcgbEHh8Aojsi7isAMkVtWAnlowRo5zaG3LsZ_1VJA', 7, 1561057726, 1563476926),
('rLATZfaJDZw7SVWxt-1hI19daCVBXEsE61dIUH_QEy4', 7, 1561057726, 1563476926),
('Jjl8HEbTSJpZBWoyXOajJXqciuUdngUbah061jwhliE', 19, 1564256499, 1564292499),
('zl0fLQ9kuhgupwEJzI6X7wFJg3onG_sR3jarQWOPiiY', 499, 1564256499, 1564429299),
('ejWtPDKvxt-q7LZ3mFjzUoIWKJYzu47igC8Jd9mffFk', 74, 1567876640, 1568416640);

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `fragrances`
--
ALTER TABLE `fragrances`
  ADD PRIMARY KEY (`fragrance_id`);

--
-- Indizes für die Tabelle `mods`
--
ALTER TABLE `mods`
  ADD PRIMARY KEY (`mod_id`);

--
-- Indizes für die Tabelle `mod_fragrancechoice`
--
ALTER TABLE `mod_fragrancechoice`
  ADD PRIMARY KEY (`fragrance_id`);

--
-- Indizes für die Tabelle `mod_fragrancecollective`
--
ALTER TABLE `mod_fragrancecollective`
  ADD PRIMARY KEY (`ref_id`);

--
-- Indizes für die Tabelle `mod_time`
--
ALTER TABLE `mod_time`
  ADD PRIMARY KEY (`event_id`);

--
-- Indizes für die Tabelle `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`unit_id`);

--
-- Indizes für die Tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indizes für die Tabelle `users_confirmations`
--
ALTER TABLE `users_confirmations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `selector` (`selector`),
  ADD KEY `email_expires` (`email`,`expires`),
  ADD KEY `user_id` (`user_id`);

--
-- Indizes für die Tabelle `users_remembered`
--
ALTER TABLE `users_remembered`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `selector` (`selector`),
  ADD KEY `user` (`user`);

--
-- Indizes für die Tabelle `users_resets`
--
ALTER TABLE `users_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `selector` (`selector`),
  ADD KEY `user_expires` (`user`,`expires`);

--
-- Indizes für die Tabelle `users_throttling`
--
ALTER TABLE `users_throttling`
  ADD PRIMARY KEY (`bucket`),
  ADD KEY `expires_at` (`expires_at`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `fragrances`
--
ALTER TABLE `fragrances`
  MODIFY `fragrance_id` int(12) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT für Tabelle `mods`
--
ALTER TABLE `mods`
  MODIFY `mod_id` int(12) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT für Tabelle `mod_fragrancechoice`
--
ALTER TABLE `mod_fragrancechoice`
  MODIFY `fragrance_id` int(12) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT für Tabelle `mod_fragrancecollective`
--
ALTER TABLE `mod_fragrancecollective`
  MODIFY `ref_id` int(12) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT für Tabelle `mod_time`
--
ALTER TABLE `mod_time`
  MODIFY `event_id` int(12) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT für Tabelle `units`
--
ALTER TABLE `units`
  MODIFY `unit_id` int(12) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT für Tabelle `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT für Tabelle `users_confirmations`
--
ALTER TABLE `users_confirmations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT für Tabelle `users_remembered`
--
ALTER TABLE `users_remembered`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `users_resets`
--
ALTER TABLE `users_resets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
