-- Päätalli: tarjoillaan sivuston juuresta (/, /hevoset, /{sivu}), muut tallit /{slug}/-polusta.
-- Tyhjä arvo = ei päätallia (sivuston vakiosivut näyttävät kaikkien tallien tiedot).
-- Päätallin voi vaihtaa adminissa: Ylläpitäjän tiedot → Päätalli.
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES ('main_stable_id', '');
