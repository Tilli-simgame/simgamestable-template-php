-- ============================================================
-- Migraatio: kilpailun arvokilpailu-merkintä (competitions.is_championship)
-- Arvokilpailun nimi (esim. KRJ-Cup) kirjoitetaan Huom-kenttään; hevossivu näyttää
-- arvokilpailut legacy-sivujen tapaan omana "Menestys arvokilpailuissa" -osionaan.
-- Tuonti (migrate_tiikeriluola_competitions.sql) tallensi Huom-tekstin vain legacy-sivujen
-- arvokilpailu-/cup-riveille, joten ne merkitään arvokilpailuiksi.
-- Aja phpMyAdminissa: Import → valitse tämä tiedosto
-- ============================================================

ALTER TABLE `competitions`
  ADD COLUMN `is_championship` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Arvokilpailu (nimi Huom-kentässä)' AFTER `notes`;

UPDATE `competitions` SET `is_championship` = 1 WHERE `notes` IS NOT NULL;
