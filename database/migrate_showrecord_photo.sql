-- ============================================================
-- Migraatio: Lisää photo_id-sarake showrecords-tauluun
-- (Kuva jolla näyttelytulos on saatu)
-- Aja vain jos showrecords-taulu on jo olemassa ilman photo_id-saraketta.
-- Aja phpMyAdminissa: Import → valitse tämä tiedosto
-- ============================================================

ALTER TABLE `showrecords`
  ADD COLUMN `photo_id` INT UNSIGNED DEFAULT NULL COMMENT 'Kuva jolla tulos saatu (horse_photos.id)'
    AFTER `judge_contact_id`,
  ADD KEY `idx_showrecords_photo` (`photo_id`),
  ADD CONSTRAINT `fk_showrecords_photo` FOREIGN KEY (`photo_id`) REFERENCES `horse_photos` (`id`) ON DELETE SET NULL;
