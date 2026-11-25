CREATE TABLE `_artgateway_test_environment` (
  `id` tinyint unsigned NOT NULL PRIMARY KEY,
  `marker` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `_artgateway_test_environment` (`id`, `marker`)
VALUES (1, 'artgateway-isolated-test-db-v1');
