-- ============================================================
-- Poisto-hyväksyntätyönkulku — pending_deletions + soft-delete-sarakkeet
-- Lisää is_deleted/deleted_at-sarakkeet foals/competitions/showrecords/posts-tauluihin
-- (mallina horses-taulu, schema.sql rivit 111-113, 121) ja uuden pending_deletions-jonotaulun.
-- Aja phpMyAdminissa: Import → valitse tämä tiedosto
-- ============================================================

ALTER TABLE `foals`
  ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  ADD KEY `idx_foals_deleted` (`is_deleted`);

ALTER TABLE `competitions`
  ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  ADD KEY `idx_competitions_deleted` (`is_deleted`);

ALTER TABLE `showrecords`
  ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  ADD KEY `idx_showrecords_deleted` (`is_deleted`);

ALTER TABLE `posts`
  ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  ADD KEY `idx_posts_deleted` (`is_deleted`);

CREATE TABLE IF NOT EXISTS `pending_deletions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` ENUM('horse','foal','competition','showrecord','post') NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `requested_by` INT UNSIGNED NOT NULL COMMENT 'admin_users.id (mod)',
  `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` INT UNSIGNED DEFAULT NULL COMMENT 'admin_users.id (admin)',
  `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pending_deletions_status` (`status`),
  KEY `idx_pending_deletions_entity` (`entity_type`, `entity_id`),
  CONSTRAINT `fk_pending_deletions_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pending_deletions_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
