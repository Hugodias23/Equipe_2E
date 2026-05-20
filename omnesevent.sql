-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3308
-- Généré le : mar. 19 mai 2026 à 11:52
-- Version du serveur : 8.4.7
-- Version de PHP : 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `omnesevent`
--

-- --------------------------------------------------------

--
-- Structure de la table `events`
--

DROP TABLE IF EXISTS `events`;
CREATE TABLE IF NOT EXISTS `events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organizer_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_date` datetime NOT NULL,
  `place` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('Soiree','Sport','Culture','Conference','Autre') COLLATE utf8mb4_unicode_ci NOT NULL,
  `association` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacity` int NOT NULL,
  `price` decimal(8,2) NOT NULL DEFAULT '0.00',
  `poster` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('published','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `organizer_id` (`organizer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `events`
--

INSERT INTO `events` (`id`, `organizer_id`, `title`, `description`, `event_date`, `place`, `category`, `association`, `capacity`, `price`, `poster`, `status`, `created_at`) VALUES
(1, 2, 'Soiree de rentree Omnes', 'Musique, animations et rencontres entre promotions dans une ambiance etudiante.', '2026-05-21 20:00:00', 'Campus Paris - Hall A', 'Soiree', 'BDE', 120, 8.00, NULL, 'published', '2026-05-13 21:15:52'),
(2, 3, 'Tournoi futsal inter-assos', 'Equipes mixtes, phases de poules et finale en fin d apres-midi.', '2026-05-26 14:00:00', 'Gymnase Omnes', 'Sport', 'BDS', 48, 4.50, NULL, 'published', '2026-05-13 21:15:52'),
(3, 2, 'Conference entrepreneuriat', 'Retour d experience de diplomes et atelier questions reponses.', '2026-06-02 18:30:00', 'Amphi 2', 'Conference', 'Junior Entreprise', 80, 0.00, NULL, 'published', '2026-05-13 21:15:52'),
(4, 2, 'Expo photo campus', 'Selection de photos prises par les etudiants autour de la vie Omnes.', '2026-06-12 17:00:00', 'Atrium', 'Culture', 'BDE', 60, 2.00, NULL, 'published', '2026-05-13 21:15:52'),
(5, 1, 'test attente', 'test', '2026-05-20 23:00:00', 'lyon', 'Soiree', 'Omnes', 2, 0.00, NULL, 'published', '2026-05-14 14:20:09'),
(6, 1, 'Bobinou au ski', 'ca ride les gars ?', '2026-11-05 08:00:00', 'lyon', 'Culture', 'Omnes', 500, 30000.00, NULL, 'published', '2026-05-18 13:54:37');

-- --------------------------------------------------------

--
-- Structure de la table `event_ratings`
--

DROP TABLE IF EXISTS `event_ratings`;
CREATE TABLE IF NOT EXISTS `event_ratings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `event_id` int NOT NULL,
  `rating` tinyint UNSIGNED NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_event` (`user_id`,`event_id`),
  KEY `event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `follows`
--

DROP TABLE IF EXISTS `follows`;
CREATE TABLE IF NOT EXISTS `follows` (
  `id` int NOT NULL AUTO_INCREMENT,
  `follower_id` int NOT NULL,
  `following_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','accepted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'accepted',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_follow` (`follower_id`,`following_id`),
  KEY `following_id` (`following_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `follows`
--

INSERT INTO `follows` (`id`, `follower_id`, `following_id`, `created_at`, `status`) VALUES
(1, 5, 1, '2026-05-14 14:44:22', 'accepted'),
(2, 1, 5, '2026-05-15 15:39:53', 'accepted'),
(3, 1, 4, '2026-05-18 13:55:33', 'accepted'),
(4, 1, 3, '2026-05-18 13:55:34', 'accepted'),
(5, 1, 2, '2026-05-18 13:55:35', 'accepted');

-- --------------------------------------------------------

--
-- Structure de la table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_id` int NOT NULL,
  `user_id` int NOT NULL,
  `ticket_id` int DEFAULT NULL,
  `amount` decimal(8,2) NOT NULL,
  `method` enum('card_demo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'card_demo',
  `reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('paid','refunded','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'paid',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `event_id` (`event_id`),
  KEY `user_id` (`user_id`),
  KEY `ticket_id` (`ticket_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `payments`
--

INSERT INTO `payments` (`id`, `event_id`, `user_id`, `ticket_id`, `amount`, `method`, `reference`, `status`, `created_at`) VALUES
(1, 6, 1, 4, 30000.00, 'card_demo', 'PAY-865696167C', 'paid', '2026-05-18 13:55:14');

-- --------------------------------------------------------

--
-- Structure de la table `personal_events`
--

DROP TABLE IF EXISTS `personal_events`;
CREATE TABLE IF NOT EXISTS `personal_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `personal_events`
--

INSERT INTO `personal_events` (`id`, `user_id`, `title`, `event_date`, `event_time`, `created_at`) VALUES
(2, 1, 'ds', '2026-05-30', '08:30:00', '2026-05-15 15:22:56');

-- --------------------------------------------------------

--
-- Structure de la table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_id` int NOT NULL,
  `user_id` int NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('reserved','cancelled','present') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reserved',
  `payment_status` enum('free','paid','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free',
  `payment_reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  UNIQUE KEY `unique_ticket` (`event_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `tickets`
--

INSERT INTO `tickets` (`id`, `event_id`, `user_id`, `code`, `status`, `payment_status`, `payment_reference`, `created_at`) VALUES
(1, 5, 5, '56EDD8DB-5-5', 'cancelled', 'free', NULL, '2026-05-15 15:23:49'),
(2, 5, 1, '0147C0C7-5-1', 'reserved', 'free', NULL, '2026-05-15 16:04:38'),
(4, 6, 1, 'C44E1028-6-1', 'reserved', 'paid', 'PAY-865696167C', '2026-05-18 13:55:14'),
(5, 3, 1, '6B83866C-3-1', 'reserved', 'free', NULL, '2026-05-18 13:55:52');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','organizer','participant') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'participant',
  `status` enum('pending','active','blocked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `association` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_private` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `status`, `association`, `avatar`, `bio`, `created_at`, `is_private`) VALUES
(1, 'admin', 'admin@omnes.fr', '$2y$10$II6Aidbkv/IXs8PXF/8Rq.YiMdDzbnN3dmYyEPc54TOHeZcUYQs7W', 'admin', 'active', NULL, 'uploads/avatars/avatar_1_1778769674.jpg', 'super admin\r\nabonnez-vous!!!', '2026-05-13 21:15:52', 1),
(2, 'BDE Omnes', 'bde@omnes.fr', '$2y$10$0LeHDREKBXkRSVySLTLiI.EnEFmW7agJiPNnIUdtCCWuO8eHVui1y', 'organizer', 'active', 'BDE', NULL, NULL, '2026-05-13 21:15:52', 0),
(3, 'BDS Omnes', 'bds@omnes.fr', '$2y$10$DaEU0lkZXlco1ky8Mnzoo.GuiiW2./TJuI8djMyiATu3n9u2WqRYO', 'organizer', 'active', 'BDS', NULL, NULL, '2026-05-13 21:15:52', 0),
(4, 'Etudiant Demo', 'etu@omnes.fr', '$2y$10$V7lrSvsTRNiOAdwl14VkguyoLk3y9PQAFfMM2rQD335c5auANbCFG', 'participant', 'active', NULL, NULL, NULL, '2026-05-13 21:15:52', 0),
(5, 'dias hugo', 'hugo.dias@edu.ece.fr', '$2y$10$bUxhHFFdma2Xehg.ORXhj.5wjLbIx4zeNaCRL/yWU9anH1lf.1a.S', 'participant', 'active', NULL, 'uploads/avatars/avatar_5_1779188756.png', NULL, '2026-05-13 21:28:37', 1);

-- --------------------------------------------------------

--
-- Structure de la table `user_blocks`
--

DROP TABLE IF EXISTS `user_blocks`;
CREATE TABLE IF NOT EXISTS `user_blocks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `blocker_id` int NOT NULL,
  `blocked_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_block` (`blocker_id`,`blocked_id`),
  KEY `blocked_id` (`blocked_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_photos`
--

DROP TABLE IF EXISTS `user_photos`;
CREATE TABLE IF NOT EXISTS `user_photos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `event_id` int DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_posts`
--

DROP TABLE IF EXISTS `user_posts`;
CREATE TABLE IF NOT EXISTS `user_posts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `photo_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_stories`
--

DROP TABLE IF EXISTS `user_stories`;
CREATE TABLE IF NOT EXISTS `user_stories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `waitlist`
--

DROP TABLE IF EXISTS `waitlist`;
CREATE TABLE IF NOT EXISTS `waitlist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_id` int NOT NULL,
  `user_id` int NOT NULL,
  `status` enum('waiting','promoted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'waiting',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_waitlist` (`event_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `event_ratings`
--
ALTER TABLE `event_ratings`
  ADD CONSTRAINT `event_ratings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_ratings_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `follows`
--
ALTER TABLE `follows`
  ADD CONSTRAINT `follows_ibfk_1` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `follows_ibfk_2` FOREIGN KEY (`following_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `personal_events`
--
ALTER TABLE `personal_events`
  ADD CONSTRAINT `personal_events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_blocks`
--
ALTER TABLE `user_blocks`
  ADD CONSTRAINT `user_blocks_ibfk_1` FOREIGN KEY (`blocker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_blocks_ibfk_2` FOREIGN KEY (`blocked_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_photos`
--
ALTER TABLE `user_photos`
  ADD CONSTRAINT `user_photos_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_photos_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `user_posts`
--
ALTER TABLE `user_posts`
  ADD CONSTRAINT `user_posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_stories`
--
ALTER TABLE `user_stories`
  ADD CONSTRAINT `user_stories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `waitlist`
--
ALTER TABLE `waitlist`
  ADD CONSTRAINT `waitlist_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `waitlist_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
