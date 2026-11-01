-- ============================================================
-- Migraatio: laatuarvostelut, varsapalkinnot ja jälkeläisarvostelut
-- description-kentästä quality_reviews-tauluun
-- Tuonti tallensi ne kuvaukseen riveinä:
--   "05/2026 KTK-III 64p."   (myös lyhyt vuosi: "03/21 VV-II 32p.")
--   "Varsapalkinnot: EV-II, KV-I"   (ei päivämäärää)
-- Näyttelytittelit (VIR MVA Ch, Champion, VSN ...) jäävät kuvaukseen.
-- Päivämääräksi tulee kuukauden 1. päivä. Varsapalkintolistan palkinto
-- ohitetaan, jos sama palkinto on hevosella jo päivättynä rivinä.
-- Aja migrate_quality_reviews.sql ensin.
-- Aja phpMyAdminissa: Import → valitse tämä tiedosto
-- ============================================================

-- Varsapalkintolistoilla ei ole päivämäärää
ALTER TABLE `quality_reviews`
  MODIFY `review_date` DATE DEFAULT NULL;

SET SESSION group_concat_max_len = 1000000;

-- 1. Pilko kuvaukset riveiksi
DROP TABLE IF EXISTS `_qr_lines`;
CREATE TABLE `_qr_lines` AS
WITH RECURSIVE l AS (
  SELECT id AS horse_id, 1 AS n,
         SUBSTRING_INDEX(description, '\n', 1) AS line,
         IF(LOCATE('\n', description) > 0, SUBSTRING(description, LOCATE('\n', description) + 1), NULL) AS rest
  FROM horses
  WHERE description REGEXP '(?m)^([0-9]{2}/[0-9]{2,4} |Varsapalkinnot:)'
  UNION ALL
  SELECT horse_id, n + 1,
         SUBSTRING_INDEX(rest, '\n', 1),
         IF(LOCATE('\n', rest) > 0, SUBSTRING(rest, LOCATE('\n', rest) + 1), NULL)
  FROM l WHERE rest IS NOT NULL
)
SELECT horse_id, n, line, TRIM(line) AS t FROM l;

-- 2. Päivätyt rivit (ei näyttelytittelejä)
DROP TABLE IF EXISTS `_qr_items`;
CREATE TABLE `_qr_items` (
  `horse_id` INT UNSIGNED NOT NULL,
  `n` INT NOT NULL,
  `k` INT NOT NULL,
  `review_date` DATE DEFAULT NULL,
  `title` VARCHAR(50) NOT NULL,
  `points` DECIMAL(8,2) DEFAULT NULL
);

INSERT INTO `_qr_items` (horse_id, n, k, review_date, title, points)
SELECT horse_id, n, 0,
       -- Virheellinen kuukausi (esim. "31/2022") → ei päivämäärää
       IF(mm BETWEEN '01' AND '12',
          STR_TO_DATE(CONCAT(IF(LENGTH(y) = 2, CONCAT('20', y), y), '-', mm, '-01'), '%Y-%m-%d'),
          NULL),
       -- Pisteet "64p." (lähteessä joskus ilman loppupistettä: "35,4p")
       REGEXP_REPLACE(rest, ' [0-9]+(,[0-9]+)?p\\.?$', ''),
       REPLACE(REGEXP_SUBSTR(rest, '[0-9]+(,[0-9]+)?(?=p\\.?$)'), ',', '.')
FROM (
  SELECT horse_id, n,
         LEFT(t, 2) AS mm,
         SUBSTRING_INDEX(SUBSTRING_INDEX(t, ' ', 1), '/', -1) AS y,
         SUBSTRING(t, LOCATE(' ', t) + 1) AS rest
  FROM `_qr_lines`
  WHERE t REGEXP '^[0-9]{2}/[0-9]{2,4} '
) d
WHERE rest NOT REGEXP '^(VIR |Champion|VSN )';

-- 3. Varsapalkintolistat: "Varsapalkinnot: EV-II, KV-I" → yksi rivi per palkinto
INSERT INTO `_qr_items` (horse_id, n, k, review_date, title, points)
WITH RECURSIVE v AS (
  SELECT horse_id, n, 1 AS k,
         TRIM(SUBSTRING_INDEX(list, ',', 1)) AS title,
         IF(LOCATE(',', list) > 0, SUBSTRING(list, LOCATE(',', list) + 1), NULL) AS rest
  FROM (SELECT horse_id, n, TRIM(SUBSTRING(t, LENGTH('Varsapalkinnot:') + 1)) AS list
        FROM `_qr_lines` WHERE t LIKE 'Varsapalkinnot:%') s
  UNION ALL
  SELECT horse_id, n, k + 1,
         TRIM(SUBSTRING_INDEX(rest, ',', 1)),
         IF(LOCATE(',', rest) > 0, SUBSTRING(rest, LOCATE(',', rest) + 1), NULL)
  FROM v WHERE rest IS NOT NULL
)
SELECT horse_id, n, k, NULL, title, NULL FROM v WHERE title <> '';

DELETE i FROM `_qr_items` i
JOIN (SELECT DISTINCT horse_id, title FROM `_qr_items` WHERE review_date IS NOT NULL) dated
  ON dated.horse_id = i.horse_id AND dated.title = i.title
WHERE i.k > 0;

-- 4. Siirrä laatuarvostelut-tauluun (jaos = tuloksen alkukirjaimet: KTK-III → KTK)
INSERT INTO `quality_reviews` (horse_id, review_date, organizer, placement, points)
SELECT horse_id, review_date, REGEXP_SUBSTR(title, '^[A-Z]+'), title, points
FROM `_qr_items`
ORDER BY horse_id, n, k;

-- 5. Poista siirretyt rivit kuvauksesta. Saavutukset-otsikko jää vain,
--    jos sen alle jää näyttelytittelirivejä.
DROP TABLE IF EXISTS `_qr_keep`;
CREATE TABLE `_qr_keep` AS
SELECT l.horse_id, l.n, l.line
FROM `_qr_lines` l
WHERE l.t NOT LIKE 'Varsapalkinnot:%'
  AND NOT EXISTS (SELECT 1 FROM `_qr_items` i WHERE i.horse_id = l.horse_id AND i.n = l.n AND i.k = 0)
  AND NOT (l.t = 'Saavutukset:' AND NOT EXISTS (
        SELECT 1 FROM `_qr_lines` x
        WHERE x.horse_id = l.horse_id AND x.t REGEXP '^[0-9]{2}/[0-9]{2,4} (VIR |Champion|VSN )'));

UPDATE horses h
JOIN (SELECT horse_id FROM `_qr_lines` GROUP BY horse_id) a ON a.horse_id = h.id
LEFT JOIN (
  SELECT horse_id, GROUP_CONCAT(line ORDER BY n SEPARATOR '\n') AS description
  FROM `_qr_keep` GROUP BY horse_id
) k ON k.horse_id = h.id
SET h.description = NULLIF(TRIM(BOTH '\n' FROM COALESCE(k.description, '')), '');

DROP TABLE IF EXISTS `_qr_keep`;
DROP TABLE IF EXISTS `_qr_items`;
DROP TABLE IF EXISTS `_qr_lines`;
