-- Lisää showrecords-taulu (näyttelytulokset)
-- Aja tämä, jos käytät olemassaolevaa tietokantaa (ei tuore asennus schema.sql:sta)

CREATE TABLE IF NOT EXISTS `showrecords` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `horse_id` INT UNSIGNED NOT NULL,
  `show_date` DATE NOT NULL,
  `discipline` VARCHAR(100) DEFAULT NULL COMMENT 'Laji',
  `country` VARCHAR(100) DEFAULT NULL COMMENT 'Maa',
  `organizer` VARCHAR(200) DEFAULT NULL COMMENT 'Järjestäjän nimi',
  `organizer_url` VARCHAR(500) DEFAULT NULL COMMENT 'Järjestäjän URL',
  `class` VARCHAR(100) DEFAULT NULL COMMENT 'Luokka',
  `placement` VARCHAR(50) DEFAULT NULL COMMENT 'Tulos',
  `points` DECIMAL(8,2) DEFAULT NULL COMMENT 'Pisteet',
  `judge_contact_id` INT UNSIGNED DEFAULT NULL COMMENT 'Tuomari (contacts.id)',
  `photo_id` INT UNSIGNED DEFAULT NULL COMMENT 'Kuva jolla tulos saatu (horse_photos.id)',
  `review` TEXT DEFAULT NULL COMMENT 'Sanallinen arvostelu',
  `notes` TEXT DEFAULT NULL COMMENT 'Huom',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_showrecords_horse` (`horse_id`),
  KEY `idx_showrecords_date` (`show_date`),
  KEY `idx_showrecords_judge` (`judge_contact_id`),
  KEY `idx_showrecords_photo` (`photo_id`),
  CONSTRAINT `fk_showrecords_horse` FOREIGN KEY (`horse_id`) REFERENCES `horses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_showrecords_judge` FOREIGN KEY (`judge_contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_showrecords_photo` FOREIGN KEY (`photo_id`) REFERENCES `horse_photos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
