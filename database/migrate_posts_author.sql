-- ============================================================
-- Postausten omistajuus — posts.author_id
-- Lisää author_id-sarakkeen postausten omistajuuden perustaksi (AUTHOR-02, AUTHOR-04)
-- ja backfillaa kaikki olemassa olevat postaukset admin-tunnukselle (D-01).
-- Aja phpMyAdminissa: Import → valitse tämä tiedosto
-- ============================================================

ALTER TABLE `posts`
  ADD COLUMN `author_id` INT UNSIGNED DEFAULT NULL
    COMMENT 'Postauksen tekijä (admin_users.id)'
    AFTER `content`,
  ADD CONSTRAINT `fk_posts_author` FOREIGN KEY (`author_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

-- Backfillaa kaikki olemassa olevat postaukset admin-tunnukselle (D-01).
UPDATE `posts` SET `author_id` = (SELECT `id` FROM `admin_users` WHERE `username` = 'admin') WHERE `author_id` IS NULL;
