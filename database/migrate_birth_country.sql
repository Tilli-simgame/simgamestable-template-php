-- ============================================================
-- Migraatio: hevosen syntymämaa omaan sarakkeeseen
-- Tuonti tallensi syntymämaan description-kenttään riville "Syntymämaa: X".
-- Siirretään arvo birth_country-sarakkeeseen ja poistetaan rivi kuvauksesta.
-- Aja phpMyAdminissa: Import → valitse tämä tiedosto
-- ============================================================

ALTER TABLE `horses`
  ADD COLUMN `birth_country` VARCHAR(100) DEFAULT NULL COMMENT 'Syntymämaa' AFTER `aging_system`;

UPDATE `horses`
SET `birth_country` = NULLIF(TRIM(REGEXP_SUBSTR(`description`, '(?m)(?<=^Syntymämaa:).*$')), ''),
    `description`   = NULLIF(TRIM(BOTH '\n' FROM
                        REGEXP_REPLACE(`description`, '(?m)^Syntymämaa:.*(\n|$)', '')), '')
WHERE `description` REGEXP '(?m)^Syntymämaa:';
