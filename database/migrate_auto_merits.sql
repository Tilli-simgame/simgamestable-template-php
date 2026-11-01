-- ============================================================
-- Migraatio: meriitit ja varsapalkinnot lasketaan laatuarvosteluista
-- Hevossivu näyttää horses.merits / foal_awards -arvon, jos se on täytetty,
-- muuten rivi lasketaan quality_reviews-taulusta (computeMerits() helpers.php:ssä).
-- Tyhjennetään tallennettu arvo hevosilta, joilla on vastaavat laatuarvostelut,
-- jotta rivi päivittyy automaattisesti uusien tulosten myötä.
-- Hevoset ilman tuloksia (esim. suvun hevoset) pitävät tallennetun arvon.
-- Aja migrate_quality_reviews_from_description.sql ja migrate_show_titles_from_description.sql ensin.
-- Aja phpMyAdminissa: Import → valitse tämä tiedosto
-- ============================================================

-- Meriitit: hevosella on muita kuin varsapalkinto- tai jälkeläisarvosteluja
UPDATE `horses` SET `merits` = NULL
WHERE `id` IN (SELECT `horse_id` FROM `quality_reviews`
               WHERE `is_deleted` = 0
                 AND `organizer` NOT IN ('EV', 'KV', 'KEV', 'VV', 'VAR', 'SV', 'AV', 'VRKK', 'SHEV', 'NWA')
                 AND `placement` NOT LIKE '%Jälk%');

-- Varsapalkinnot: hevosella on varsapalkintoja
UPDATE `horses` SET `foal_awards` = NULL
WHERE `id` IN (SELECT `horse_id` FROM `quality_reviews`
               WHERE `is_deleted` = 0
                 AND `organizer` IN ('EV', 'KV', 'KEV', 'VV', 'VAR', 'SV', 'AV', 'VRKK', 'SHEV'));
