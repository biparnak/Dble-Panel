SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `website_files` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(255) NOT NULL,
  `size` bigint(20) NOT NULL DEFAULT '0',
  `is_index` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `download_cards` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `slug` varchar(120) DEFAULT NULL,
  `custom_css` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `download_versions` (
  `id` int(11) NOT NULL,
  `card_id` int(11) NOT NULL,
  `version_name` varchar(100) NOT NULL,
  `description` text,
  `original_filename` varchar(255) NOT NULL,
  `file_data` longblob NOT NULL,
  `file_size` int(11) NOT NULL DEFAULT '0',
  `compressed_size` int(11) NOT NULL DEFAULT '0',
  `mime_type` varchar(100) DEFAULT 'application/octet-stream',
  `download_count` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `site_settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` longtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

ALTER TABLE `website_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_filename` (`filename`),
  ADD KEY `idx_is_index` (`is_index`);

ALTER TABLE `download_cards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_title` (`title`),
  ADD KEY `idx_slug` (`slug`);

ALTER TABLE `download_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_card_id` (`card_id`),
  ADD KEY `idx_version_name` (`version_name`);

ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_key`);

ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `website_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `download_cards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `download_versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `download_versions`
  ADD CONSTRAINT `download_versions_ibfk_1`
  FOREIGN KEY (`card_id`) REFERENCES `download_cards` (`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;
