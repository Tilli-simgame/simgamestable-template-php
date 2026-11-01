-- ============================================================
-- Migraatio: laatuarvostelut (quality_reviews)
-- Rakenne kuten showrecords, ilman luokkaa, lajia ja kuvaa.
-- Aja phpMyAdminissa: Import → valitse tämä tiedosto
-- ============================================================

CREATE TABLE IF NOT EXISTS `quality_reviews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `horse_id` INT UNSIGNED NOT NULL,
  `review_date` DATE NOT NULL,
  `country` VARCHAR(100) DEFAULT NULL COMMENT 'Maa',
  `organizer` VARCHAR(200) DEFAULT NULL COMMENT 'Laatuarvostelun jaos',
  `organizer_url` VARCHAR(500) DEFAULT NULL COMMENT 'Tilaisuuden osoite',
  `placement` VARCHAR(50) DEFAULT NULL COMMENT 'Tulos',
  `points` DECIMAL(8,2) DEFAULT NULL COMMENT 'Pisteet',
  `judge_contact_id` INT UNSIGNED DEFAULT NULL COMMENT 'Tuomari (contacts.id)',
  `review` TEXT DEFAULT NULL COMMENT 'Sanallinen arvostelu',
  `notes` TEXT DEFAULT NULL COMMENT 'Huom',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  -- Pehmeä poisto
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_quality_reviews_horse` (`horse_id`),
  KEY `idx_quality_reviews_date` (`review_date`),
  KEY `idx_quality_reviews_judge` (`judge_contact_id`),
  KEY `idx_quality_reviews_deleted` (`is_deleted`),
  CONSTRAINT `fk_quality_reviews_horse` FOREIGN KEY (`horse_id`) REFERENCES `horses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quality_reviews_judge` FOREIGN KEY (`judge_contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Poistopyynnöt tukemaan laatuarvosteluja
ALTER TABLE `pending_deletions`
  MODIFY `entity_type` ENUM('horse','foal','competition','showrecord','qualityreview','post') NOT NULL;
