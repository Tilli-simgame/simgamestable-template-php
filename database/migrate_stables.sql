-- ============================================================
-- Migraatio: Tallit (useamman tallin hallinta)
--   - stables-taulu: nimi, tallinumero, kategoria, kuvaus, ulkoasu- ja väriteema
--   - horses.stable_id: missä tallissa hevonen asuu
-- Aja phpMyAdminissa kerran. Voi ajaa uudelleen (IF NOT EXISTS).
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `stables` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(150) NOT NULL COMMENT 'Tallin nimi',
  `slug`          VARCHAR(200) NOT NULL COMMENT 'URL-tunniste (/talli/slug)',
  `stable_number` VARCHAR(50)  DEFAULT NULL COMMENT 'Tallinumero (esim. VRL-tallirekisteri)',
  `category`      VARCHAR(100) DEFAULT NULL COMMENT 'Kategoria (vapaa teksti)',
  `description`   TEXT         DEFAULT NULL COMMENT 'Kuvaus',
  `layout_theme`  VARCHAR(100) DEFAULT NULL COMMENT 'Ulkoasuteema (themes/-kansio), NULL = sivuston oletus',
  `color_theme`   VARCHAR(50)  DEFAULT NULL COMMENT 'Väriteema, NULL = sivuston oletus',
  `sort_order`    INT          NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_stable_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- horses.stable_id (MySQL 8 ei tue ADD COLUMN IF NOT EXISTS → tarkistus information_schemasta)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'horses' AND COLUMN_NAME = 'stable_id');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `horses`
     ADD COLUMN `stable_id` INT UNSIGNED DEFAULT NULL COMMENT ''Talli jossa hevonen asuu (stables.id)'' AFTER `porrastetut_discipline_id`,
     ADD KEY `idx_horses_stable` (`stable_id`),
     ADD CONSTRAINT `fk_horses_stable` FOREIGN KEY (`stable_id`) REFERENCES `stables` (`id`) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
