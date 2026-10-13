-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 13, 2026 at 10:04 PM
-- Server version: 11.8.5-MariaDB-log
-- PHP Version: 8.5.1

-- NOTE: Import this file in an empty db
-- called 'artgateway_db' with utf8mb4_unicode_ci

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `artgateway_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `artists`
--

CREATE TABLE `artists` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `birthYear` int(11) DEFAULT NULL,
  `deathYear` int(11) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: One artist can be associated with multiple artworks.
-- --------------------------------------------------------

--
-- Table structure for table `artworks`
--

CREATE TABLE `artworks` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `creationYear` int(11) NOT NULL,
  `medium` varchar(150) DEFAULT NULL,
  `period` varchar(100) DEFAULT NULL,
  `dimensions` varchar(100) DEFAULT NULL,
  `artistId` int(10) UNSIGNED NOT NULL,
  `museumId` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: Table links each artwork to its artist, its museum (if known),
-- and the user who submitted it (if known).
-- --------------------------------------------------------

--
-- Table structure for table `artwork_tags`
--

CREATE TABLE `artwork_tags` (
  `artworkId` int(10) UNSIGNED NOT NULL,
  `tagId` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: Connects artowkrs to their tags using IDs from
-- 'artworks' and 'tags'. This allows one artwork to have
-- many tags and one tag to describe many artworks.
-- --------------------------------------------------------

--
-- Table structure for table `collections`
--

CREATE TABLE `collections` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `isPublic` tinyint(1) NOT NULL DEFAULT 1,
  `userId` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: Users can organize artworks based on themes,
-- artistic periods, or personal interests.
-- --------------------------------------------------------

--
-- Table structure for table `collection_artworks`
--

CREATE TABLE `collection_artworks` (
  `collectionId` int(10) UNSIGNED NOT NULL,
  `artworkId` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: Connects collections to artworks using their IDs.
-- A collection can contain many artworks and an artwork
-- can appear in many collections.
-- --------------------------------------------------------

--
-- Table structure for table `museums`
--

CREATE TABLE `museums` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `city` varchar(100) NOT NULL,
  `country` varchar(100) NOT NULL,
  `website` varchar(2048) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: One museum can be associated with many artworks.
-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: Stores unique labels that describe artworks, such as
-- landscape, night, or portrait. Keeping tags in a separate 
-- table allows the same tag to be reused for multiple artworks.
-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: Stores the accounts of people who can log into the
-- ArtGateway web application.

-- --------------------------------------------------------

--
-- Indexes for dumped tables
--

--
-- Indexes for table `artists`
--
ALTER TABLE `artists`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `artworks`
--
ALTER TABLE `artworks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_artworks_creator` (`created_by`),
  ADD KEY `idx_artworks_period` (`period`),
  ADD KEY `idx_artworks_year` (`creationYear`),
  ADD KEY `idx_artworks_artist` (`artistId`),
  ADD KEY `idx_artworks_museum` (`museumId`);

--
-- Indexes for table `artwork_tags`
--
ALTER TABLE `artwork_tags`
  ADD PRIMARY KEY (`artworkId`,`tagId`),
  ADD KEY `tagId` (`tagId`);

--
-- Indexes for table `collections`
--
ALTER TABLE `collections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `userId` (`userId`);

--
-- Indexes for table `collection_artworks`
--
ALTER TABLE `collection_artworks`
  ADD PRIMARY KEY (`collectionId`,`artworkId`),
  ADD KEY `artworkId` (`artworkId`);

--
-- Indexes for table `museums`
--
ALTER TABLE `museums`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `artists`
--
ALTER TABLE `artists`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `artworks`
--
ALTER TABLE `artworks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `collections`
--
ALTER TABLE `collections`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `museums`
--
ALTER TABLE `museums`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `artworks`
--
ALTER TABLE `artworks`
  ADD CONSTRAINT `fk_artworks_artist` FOREIGN KEY (`artistId`) REFERENCES `artists` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_artworks_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_artworks_museum` FOREIGN KEY (`museumId`) REFERENCES `museums` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `artwork_tags`
--
ALTER TABLE `artwork_tags`
  ADD CONSTRAINT `artwork_tags_ibfk_1` FOREIGN KEY (`artworkId`) REFERENCES `artworks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `artwork_tags_ibfk_2` FOREIGN KEY (`tagId`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `collections`
--
ALTER TABLE `collections`
  ADD CONSTRAINT `collections_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `collection_artworks`
--
ALTER TABLE `collection_artworks`
  ADD CONSTRAINT `collection_artworks_ibfk_1` FOREIGN KEY (`collectionId`) REFERENCES `collections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `collection_artworks_ibfk_2` FOREIGN KEY (`artworkId`) REFERENCES `artworks` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
