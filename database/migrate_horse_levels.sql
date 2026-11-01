-- ============================================================
-- Migraatio: koulutustasot lajeittain (horse_levels)
-- Korvaa sarakkeet level_ko / level_re / level_ke: jokaisella lajilla oma taso.
-- Siirtää myös kuvauksen rivit "VVJ-taso: Vaikea" (valjakkoajo) ja "ARJ-taso: Show"
-- (askellajiratsastus) ja poistaa ne kuvauksesta.
-- Aja phpMyAdminissa: Import → valitse tämä tiedosto
-- ============================================================

CREATE TABLE IF NOT EXISTS `horse_levels` (
  `horse_id` INT UNSIGNED NOT NULL,
  `discipline_id` INT UNSIGNED NOT NULL,
  `level` VARCHAR(150) NOT NULL COMMENT 'Koulutustaso (vapaa teksti)',
  PRIMARY KEY (`horse_id`, `discipline_id`),
  KEY `fk_hl_discipline` (`discipline_id`),
  CONSTRAINT `fk_hl_horse` FOREIGN KEY (`horse_id`) REFERENCES `horses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hl_discipline` FOREIGN KEY (`discipline_id`) REFERENCES `disciplines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1. Vanhat sarakkeet (2 = kouluratsastus, 1 = esteratsastus, 3 = kenttäratsastus)
INSERT IGNORE INTO `horse_levels` (horse_id, discipline_id, level)
SELECT id, 2, TRIM(level_ko) FROM horses WHERE TRIM(level_ko) <> ''
UNION ALL
SELECT id, 1, TRIM(level_re) FROM horses WHERE TRIM(level_re) <> ''
UNION ALL
SELECT id, 3, TRIM(level_ke) FROM horses WHERE TRIM(level_ke) <> '';

-- 2. Kuvauksen tasorivit (6 = valjakkoajo, 7 = askellajiratsastus)
INSERT IGNORE INTO `horse_levels` (horse_id, discipline_id, level)
SELECT id, 6, TRIM(REGEXP_SUBSTR(description, '(?m)(?<=^VVJ-taso:).*$')) FROM horses
WHERE description REGEXP '(?m)^VVJ-taso:\\s*\\S'
UNION ALL
SELECT id, 7, TRIM(REGEXP_SUBSTR(description, '(?m)(?<=^ARJ-taso:).*$')) FROM horses
WHERE description REGEXP '(?m)^ARJ-taso:\\s*\\S';

UPDATE horses
SET description = NULLIF(TRIM(BOTH '\n' FROM
                    REGEXP_REPLACE(description, '(?m)^(VVJ|ARJ)-taso:.*(\n|$)', '')), '')
WHERE description REGEXP '(?m)^(VVJ|ARJ)-taso:';

-- 3. Poista vanhat sarakkeet
ALTER TABLE `horses`
  DROP COLUMN `level_ko`,
  DROP COLUMN `level_re`,
  DROP COLUMN `level_ke`;
