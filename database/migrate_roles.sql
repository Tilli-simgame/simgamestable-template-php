-- ============================================================
-- Roolit ja aktiivisuustila — admin_users.role, admin_users.is_active
-- Aja phpMyAdminissa: Import → valitse tämä tiedosto
-- ============================================================

ALTER TABLE `admin_users`
  ADD COLUMN `role` ENUM('admin','mod','author') NOT NULL DEFAULT 'author'
    COMMENT 'admin = kaikki oikeudet, mod = rajattu sisällönhallinta, author = vain omat postaukset'
    AFTER `username`,
  ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1
    COMMENT 'Deaktivoitu tunnus ei voi kirjautua sisään'
    AFTER `role`;

-- Nosta olemassa oleva admin-tunnus eksplisiittisesti admin-rooliin.
UPDATE `admin_users` SET `role` = 'admin' WHERE `username` = 'admin';
