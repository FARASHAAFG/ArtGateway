-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 05, 2026 at 02:52 PM
-- Server version: 11.8.5-MariaDB-log
-- PHP Version: 8.5.1

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

-- THIS CONTAINS MORE SAMPLE DATA
-- INCLUDING STUFF FOR THE CHICAGO ART API

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

--
-- Dumping data for table `artists`
--

INSERT INTO `artists` (`id`, `name`, `birthYear`, `deathYear`, `nationality`) VALUES
(1, 'Claude Monet', 1840, 1926, 'French'),
(2, 'Vincent van Gogh', 1853, 1890, 'Dutch'),
(3, 'Leonardo da Vinci', 1452, 1519, 'Italian'),
(5, 'Frida Kahlo', 1907, 1954, 'Mexican'),
(6, 'Jackson Pollock', 1912, 1956, 'American'),
(7, 'Johannes Vermeer', 1632, 1675, 'Dutch'),
(8, 'Edward Hopper', 1882, 1967, 'American'),
(9, 'Georges Seurat', 1859, 1891, 'French'),
(10, 'Grant Wood', 1891, 1942, 'American'),
(11, 'Georgia O\'Keeffe', 1887, 1986, 'American');

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

--
-- Dumping data for table `artworks`
--

INSERT INTO `artworks` (`id`, `title`, `creationYear`, `medium`, `period`, `dimensions`, `artistId`, `museumId`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Poppies', 1873, 'Oil on canvas', 'Impressionism', NULL, 1, 1, NULL, '2026-10-15 16:56:11', '2026-10-15 16:56:11'),
(2, 'Sunflowers', 1889, 'Oil on canvas', 'Post-Impressionism', NULL, 2, 2, NULL, '2026-10-15 16:56:11', '2026-10-15 16:56:11'),
(3, 'The Bedroom', 1888, 'Oil on canvas', 'Post-Impressionism', NULL, 2, 2, NULL, '2026-10-15 16:56:11', '2026-10-15 16:56:11'),
(4, 'The Starry Night', 1889, 'Oil on canvas', 'Post-Impressionism', '73.7 cm × 92.1 cm', 1, 2, NULL, '2026-11-05 14:37:27', '2026-11-05 14:37:27'),
(5, 'Mona Lisa', 1503, 'Oil on poplar panel', 'High Renaissance', '77 cm × 53 cm', 3, 3, 1, '2026-11-05 20:00:00', '2026-11-05 20:00:00'),
(6, 'The Last Supper', 1495, 'Tempera on gesso, pitch and mastic', 'High Renaissance', '460 cm × 880 cm', 3, 3, 1, '2026-11-05 20:00:00', '2026-11-05 20:00:00'),
(7, 'The Two Fridas', 1939, 'Oil on canvas', 'Surrealism', '173.5 cm × 173 cm', 5, 4, 2, '2026-11-05 20:00:00', '2026-11-05 20:00:00'),
(8, 'No. 5, 1948', 1948, 'Oil on fiberboard', 'Abstract Expressionism', '243.8 cm × 121.9 cm', 6, 4, 2, '2026-11-05 20:00:00', '2026-11-05 20:00:00'),
(9, 'Girl with a Pearl Earring', 1665, 'Oil on canvas', 'Dutch Golden Age', '44.5 cm × 39 cm', 7, 5, 1, '2026-11-05 20:00:00', '2026-11-05 20:00:00'),
(10, 'The Milkmaid', 1658, 'Oil on canvas', 'Dutch Golden Age', '45.5 cm × 41 cm', 7, 5, 1, '2026-11-05 20:00:00', '2026-11-05 20:00:00'),
(11, 'The Little Street', 1658, 'Oil on canvas', 'Dutch Golden Age', '54.3 cm × 44 cm', 7, 5, 1, '2026-11-05 20:00:00', '2026-11-05 20:00:00'),
(12, 'The Basket of Apples', 1893, 'Oil on canvas', 'Post-Impressionism', '65 cm × 80 cm', 2, 4, 2, '2026-11-05 20:00:00', '2026-11-05 20:00:00'),
(13, 'Water Lilies', 1919, 'Oil on canvas', 'Impressionism', '101 cm × 200 cm', 1, 1, 1, '2026-11-05 20:00:00', '2026-11-05 20:00:00'),
(14, 'Self-Portrait with Thorn Necklace and Hummingbird', 1940, 'Oil on canvas', 'Surrealism', '49.5 cm × 40 cm', 5, 4, 2, '2026-11-05 20:00:00', '2026-11-05 20:00:00'),
(15, 'Nighthawks', 1942, 'Oil on canvas', 'American Realism', '84.1 cm × 152.4 cm', 8, 6, 1, '2026-11-05 21:00:00', '2026-11-05 21:00:00'),
(16, 'A Sunday Afternoon on the Island of La Grande Jatte', 1884, 'Oil on canvas', 'Post-Impressionism', '207.6 cm × 308 cm', 9, 6, 1, '2026-11-05 21:00:00', '2026-11-05 21:00:00'),
(17, 'American Gothic', 1930, 'Oil on beaverboard', 'Regionalism', '78 cm × 65.3 cm', 10, 6, 1, '2026-11-05 21:00:00', '2026-11-05 21:00:00'),
(18, 'Blue and Green Music', 1921, 'Oil on canvas', 'Modernism', '58.4 cm × 48.3 cm', 11, 6, 2, '2026-11-05 21:00:00', '2026-11-05 21:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `artwork_tags`
--

CREATE TABLE `artwork_tags` (
  `artworkId` int(10) UNSIGNED NOT NULL,
  `tagId` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `artwork_tags`
--

INSERT INTO `artwork_tags` (`artworkId`, `tagId`) VALUES
(1, 1),
(13, 1),
(1, 2),
(2, 2),
(13, 2),
(5, 3),
(7, 3),
(9, 3),
(5, 4),
(9, 4),
(10, 4),
(15, 4),
(16, 4),
(17, 4),
(8, 5),
(18, 5),
(15, 7),
(16, 7),
(17, 7),
(15, 8),
(18, 9);

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

--
-- Dumping data for table `collections`
--

INSERT INTO `collections` (`id`, `title`, `description`, `isPublic`, `userId`, `created_at`, `updated_at`) VALUES
(1, 'Renaissance & Golden Age', 'A curated look at classic European masterworks.', 1, 1, '2026-11-05 20:30:00', '2026-11-05 20:30:00'),
(2, 'Modern & Surrealist Icons', 'Explorations of abstract and personal expression.', 1, 2, '2026-11-05 20:30:00', '2026-11-05 20:30:00'),
(3, 'Chicago Gallery Essentials', 'Must-see iconic paintings housed permanently at the Art Institute of Chicago.', 1, 1, '2026-11-05 21:10:00', '2026-11-05 21:10:00');

-- --------------------------------------------------------

--
-- Table structure for table `collection_artworks`
--

CREATE TABLE `collection_artworks` (
  `collectionId` int(10) UNSIGNED NOT NULL,
  `artworkId` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `collection_artworks`
--

INSERT INTO `collection_artworks` (`collectionId`, `artworkId`) VALUES
(1, 5),
(2, 7),
(2, 8),
(1, 9),
(3, 15),
(3, 16),
(3, 17);

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

--
-- Dumping data for table `museums`
--

INSERT INTO `museums` (`id`, `name`, `city`, `country`, `website`) VALUES
(1, 'Musée d\'Orsay', 'Paris', 'France', 'https://www.musee-orsay.fr'),
(2, 'Van Gogh Museum', 'Amsterdam', 'Netherlands', 'https://www.vangoghmuseum.nl'),
(3, 'Louvre Museum', 'Paris', 'France', 'https://www.louvre.fr'),
(4, 'The Metropolitan Museum of Art', 'New York', 'United States', 'https://www.metmuseum.org'),
(5, 'Rijksmuseum', 'Amsterdam', 'Netherlands', 'https://www.rijksmuseum.nl'),
(6, 'Art Institute of Chicago', 'Chicago', 'United States', 'https://www.artic.edu');

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tags`
--

INSERT INTO `tags` (`id`, `name`) VALUES
(5, 'abstract'),
(6, 'baroque'),
(2, 'flowers'),
(7, 'iconic'),
(1, 'landscape'),
(4, 'masterpiece'),
(9, 'modern-art'),
(3, 'portrait'),
(8, 'urban');

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

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role`, `created_at`, `updated_at`) VALUES
(1, 'Alice Curator', 'alice@artgateway.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2026-11-01 15:00:00', '2026-11-01 15:00:00'),
(2, 'Bob Artlover', 'bob@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', '2026-11-02 17:30:00', '2026-11-02 17:30:00');

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `artworks`
--
ALTER TABLE `artworks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `collections`
--
ALTER TABLE `collections`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `museums`
--
ALTER TABLE `museums`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
