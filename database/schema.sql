-- ============================================================
-- Virtuaalitalli — MySQL-skeema
-- Versio: 1.1 (2026-06-19)
-- Aja phpMyAdminissa: Import → valitse tämä tiedosto
-- Huom: Aja seed.sql tämän jälkeen (referenssidata + testidata)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Rodut (breeds) — lookup-taulu
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `breeds` (
  `id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `abbreviation` VARCHAR(100) DEFAULT NULL COMMENT 'Lyhenne',
  `is_rare` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = harvinainen rotu',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_breed_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Värit (colors) — lookup-taulu
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `colors` (
  `id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `abbreviation` VARCHAR(100) DEFAULT NULL COMMENT 'Lyhenne',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_color_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Yhteystiedot (contacts) — osoitekirja
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nickname` VARCHAR(150) DEFAULT NULL COMMENT 'Nimimerkki',
  `stable_name` VARCHAR(150) DEFAULT NULL COMMENT 'Tallin nimi',
  `stable_url` VARCHAR(500) DEFAULT NULL COMMENT 'Tallin URL',
  `character_url` VARCHAR(500) DEFAULT NULL COMMENT 'Hahmon sivujen URL',
  `vrl_id` VARCHAR(50) DEFAULT NULL COMMENT 'VRL-tunnus',
  `email` VARCHAR(255) DEFAULT NULL COMMENT 'Sähköposti',
  `country` VARCHAR(100) DEFAULT NULL COMMENT 'Maa',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Lajit (disciplines) — lookup-taulu
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `disciplines` (
  `id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `abbreviation` VARCHAR(20) DEFAULT NULL COMMENT 'Lyhenne',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_discipline_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tasot (levels) — lookup-taulu
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `levels` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_level_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Hevoset — päätaulu (itseviittaava sukutaulu)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `horses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  -- Perustiedot
  `name` VARCHAR(150) NOT NULL COMMENT 'Virallinen nimi',
  `slug` VARCHAR(200) DEFAULT NULL COMMENT 'URL-tunniste (haetaan /pages/horse/slug)',
  `call_name` VARCHAR(100) DEFAULT NULL COMMENT 'Kutsumanimi',
  `breed_id` INT UNSIGNED DEFAULT NULL COMMENT 'Rotu (breeds.id)',
  `birth_date` DATE DEFAULT NULL COMMENT 'Syntymäpäivä',
  `aging_system` ENUM('IRL','VHKR','VARL','CAS','KATT','SHS') DEFAULT NULL COMMENT 'Ikääntymisjärjestelmä',
  `gender` ENUM('ori','tamma','ruuna') NOT NULL DEFAULT 'tamma',
  `color_id` INT UNSIGNED DEFAULT NULL COMMENT 'Väri (colors.id)',
  `genes` VARCHAR(255) DEFAULT NULL COMMENT 'Geenit',
  `height_cm` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Säkäkorkeus cm',
  `vh_id` VARCHAR(50) DEFAULT NULL COMMENT 'VH-tunnus',
  `pkk_id` VARCHAR(100) DEFAULT NULL COMMENT 'PKK-tunnus',
  `porrastetut` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Kilpailee porrastetuissa',
  `porrastetut_discipline_id` INT UNSIGNED DEFAULT NULL COMMENT 'Porrastettu-laji (disciplines.id)',
  -- Painotus
  `level_ko` VARCHAR(150) DEFAULT NULL COMMENT 'Koulutaso (vapaa teksti)',
  `level_re` VARCHAR(150) DEFAULT NULL COMMENT 'Esteratsastustaso (vapaa teksti)',
  `level_ke` VARCHAR(150) DEFAULT NULL COMMENT 'Kenttäratsastustaso (vapaa teksti)',
  -- Yhteystiedot (FK → contacts)
  `owner_contact_id` INT UNSIGNED DEFAULT NULL COMMENT 'Omistaja (contacts.id)',
  `breeder_contact_id` INT UNSIGNED DEFAULT NULL COMMENT 'Kasvattaja (contacts.id)',
  `importer_contact_id` INT UNSIGNED DEFAULT NULL COMMENT 'Tuoja (contacts.id)',
  -- Kuvaus
  `description` TEXT DEFAULT NULL COMMENT 'Luonnekuvaus',
  `pedigree_notes` TEXT DEFAULT NULL COMMENT 'Sukuselvitys',
  -- Sukutaulu (itseviittaus)
  `sire_id` INT UNSIGNED DEFAULT NULL COMMENT 'Isä (horses.id)',
  `dam_id` INT UNSIGNED DEFAULT NULL COMMENT 'Emä (horses.id)',
  -- Ulkopuoliset hevoset (eivät asu tässä tallissa)
  `evm` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = ei virtuaalimaailmassa (pelkkä sukutieto)',
  `ancestor` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = toisen tallin hevonen, linkki profile_url:iin',
  `profile_url` VARCHAR(500) DEFAULT NULL COMMENT 'Ulkopuolisen tallin URL',
  -- Pehmeä poisto
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  -- Aikaleima
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_horses_slug` (`slug`),
  KEY `idx_horses_sire` (`sire_id`),
  KEY `idx_horses_dam` (`dam_id`),
  KEY `idx_horses_deleted` (`is_deleted`),
  KEY `idx_horses_owner_contact` (`owner_contact_id`),
  KEY `idx_horses_breeder_contact` (`breeder_contact_id`),
  KEY `idx_horses_importer_contact` (`importer_contact_id`),
  CONSTRAINT `fk_horses_breed` FOREIGN KEY (`breed_id`) REFERENCES `breeds` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_horses_color` FOREIGN KEY (`color_id`) REFERENCES `colors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_horses_sire` FOREIGN KEY (`sire_id`) REFERENCES `horses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_horses_dam` FOREIGN KEY (`dam_id`) REFERENCES `horses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_horses_owner_contact` FOREIGN KEY (`owner_contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_horses_breeder_contact` FOREIGN KEY (`breeder_contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_horses_importer_contact` FOREIGN KEY (`importer_contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Hevosen lajit (many-to-many)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `horse_disciplines` (
  `horse_id`      INT UNSIGNED NOT NULL,
  `discipline_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`horse_id`, `discipline_id`),
  CONSTRAINT `fk_hd_horse`      FOREIGN KEY (`horse_id`)      REFERENCES `horses`      (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hd_discipline` FOREIGN KEY (`discipline_id`) REFERENCES `disciplines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Hevoskuvat
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `horse_photos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `horse_id` INT UNSIGNED NOT NULL,
  `filename` VARCHAR(255) NOT NULL COMMENT 'Tiedostonimi uploads/-kansiossa',
  `original_name` VARCHAR(255) DEFAULT NULL COMMENT 'Alkuperäinen tiedostonimi',
  `title` VARCHAR(255) DEFAULT NULL COMMENT 'Kuvan otsikko',
  `caption` TEXT DEFAULT NULL COMMENT 'Kuvan kuvateksti',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT 'Näyttöjärjestys (ASC)',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_photos_horse` (`horse_id`),
  KEY `idx_photos_order` (`horse_id`, `sort_order`),
  CONSTRAINT `fk_photos_horse` FOREIGN KEY (`horse_id`) REFERENCES `horses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Kisakalenteri
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `competitions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `horse_id` INT UNSIGNED NOT NULL,
  `competition_date` DATE NOT NULL,
  `discipline` VARCHAR(100) DEFAULT NULL COMMENT 'Laji',
  `country` VARCHAR(100) DEFAULT NULL COMMENT 'Maa',
  `organizer` VARCHAR(200) DEFAULT NULL COMMENT 'Järjestäjän nimi',
  `organizer_url` VARCHAR(500) DEFAULT NULL COMMENT 'Järjestäjän URL',
  `notes` TEXT DEFAULT NULL COMMENT 'Huom',
  `class` VARCHAR(100) DEFAULT NULL COMMENT 'Luokka',
  `placement` VARCHAR(50) DEFAULT NULL COMMENT 'Tulos (esim. "1.", "DQ", "Hyv")',
  `points` DECIMAL(8,2) DEFAULT NULL COMMENT 'Pisteet',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comp_horse` (`horse_id`),
  KEY `idx_comp_date` (`competition_date`),
  CONSTRAINT `fk_comp_horse` FOREIGN KEY (`horse_id`) REFERENCES `horses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Näyttelytulokset
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
-- Kasvatus (varsomiset)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `foals` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `horse_id`     INT UNSIGNED  DEFAULT NULL COMMENT 'Tämän tallin hevonen (emo/ori), voi olla NULL',
  `foal_name`    VARCHAR(150)  DEFAULT NULL COMMENT 'Varsan nimi (jos tiedossa)',
  `breed_id`     INT UNSIGNED  DEFAULT NULL COMMENT 'Varsan rotu (breeds.id)',
  `sire_id`      INT UNSIGNED  DEFAULT NULL COMMENT 'Isä (horses.id)',
  `dam_id`       INT UNSIGNED  DEFAULT NULL COMMENT 'Emä (horses.id)',
  `birth_date`   DATE          DEFAULT NULL,
  `gender`       ENUM('ori','tamma','ruuna','tuntematon') DEFAULT 'tuntematon',
  `status`       ENUM('born','expected') NOT NULL DEFAULT 'born',
  `owner_contact_id` INT UNSIGNED DEFAULT NULL,
  `merits`           TEXT         DEFAULT NULL,
  `foal_horse_id`    INT UNSIGNED  DEFAULT NULL COMMENT 'Linkki hevoseen kun status=born (horses.id)',
  `notes`        TEXT          DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_foals_horse` (`horse_id`),
  CONSTRAINT `fk_foals_horse` FOREIGN KEY (`horse_id`)         REFERENCES `horses`   (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_foals_breed` FOREIGN KEY (`breed_id`)         REFERENCES `breeds`   (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_foals_sire`  FOREIGN KEY (`sire_id`)          REFERENCES `horses`   (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_foals_dam`   FOREIGN KEY (`dam_id`)           REFERENCES `horses`   (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_foals_owner` FOREIGN KEY (`owner_contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_foals_foal_horse` FOREIGN KEY (`foal_horse_id`) REFERENCES `horses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Admin-käyttäjät
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `role` ENUM('admin','mod','author') NOT NULL DEFAULT 'author'
    COMMENT 'admin = kaikki oikeudet, mod = rajattu sisällönhallinta, author = vain omat postaukset',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1
    COMMENT 'Deaktivoitu tunnus ei voi kirjautua sisään',
  `password` VARCHAR(255) NOT NULL COMMENT 'bcrypt-tiiviste',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Blogijulkaisut
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `posts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(255) NOT NULL,
  `slug`       VARCHAR(255) NOT NULL,
  `content`    MEDIUMTEXT   NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_post_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Postauksen hevoset (many-to-many)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `post_horses` (
  `post_id`  INT UNSIGNED NOT NULL,
  `horse_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`post_id`, `horse_id`),
  KEY `idx_ph_horse` (`horse_id`),
  CONSTRAINT `fk_ph_post`  FOREIGN KEY (`post_id`)  REFERENCES `posts`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ph_horse` FOREIGN KEY (`horse_id`) REFERENCES `horses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tallin asetukset
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT         DEFAULT NULL,
  `updated_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('owner_nickname',   ''),
  ('owner_vrl_id',     ''),
  ('owner_email',      ''),
  ('owner_forum_url',  ''),
  ('stable_name',      ''),
  ('color_theme',      'savi');

SET FOREIGN_KEY_CHECKS = 1;
