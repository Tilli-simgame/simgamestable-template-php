-- ============================================================
-- Migraatio: näyttelyarvonimet description-kentästä quality_reviews-tauluun
-- Jäljellä olevat Saavutukset-rivit: "01/2026 VIR MVA Ch", "10/2022 VSN National Champion",
-- "02/2025 Champion". Jaokseksi tulee arvonimen ensimmäinen sana (VIR, VSN, Champion).
-- Siirron jälkeen tyhjä "Saavutukset:"-otsikko poistetaan kuvauksesta.
-- Aja migrate_quality_reviews_from_description.sql ensin.
-- Aja phpMyAdminissa: Import → valitse tämä tiedosto
-- ============================================================

SET SESSION group_concat_max_len = 1000000;

-- 1. Pilko kuvaukset riveiksi
DROP TABLE IF EXISTS `_qr_lines`;
CREATE TABLE `_qr_lines` AS
WITH RECURSIVE l AS (
  SELECT id AS horse_id, 1 AS n,
         SUBSTRING_INDEX(description, '\n', 1) AS line,
         IF(LOCATE('\n', description) > 0, SUBSTRING(description, LOCATE('\n', description) + 1), NULL) AS rest
  FROM horses
  WHERE description REGEXP '(?m)^[0-9]{2}/[0-9]{2,4} '
  UNION ALL
  SELECT horse_id, n + 1,
         SUBSTRING_INDEX(rest, '\n', 1),
         IF(LOCATE('\n', rest) > 0, SUBSTRING(rest, LOCATE('\n', rest) + 1), NULL)
  FROM l WHERE rest IS NOT NULL
)
SELECT horse_id, n, line, TRIM(line) AS t FROM l;

-- 2. Päivätyt arvonimirivit
DROP TABLE IF EXISTS `_qr_items`;
CREATE TABLE `_qr_items` AS
SELECT horse_id, n,
       -- Virheellinen kuukausi → ei päivämäärää
       IF(mm BETWEEN '01' AND '12',
          STR_TO_DATE(CONCAT(IF(LENGTH(y) = 2, CONCAT('20', y), y), '-', mm, '-01'), '%Y-%m-%d'),
          NULL) AS review_date,
       LEFT(REGEXP_REPLACE(rest, ' [0-9]+(,[0-9]+)?p\\.?$', ''), 50) AS title,
       REPLACE(REGEXP_SUBSTR(rest, '[0-9]+(,[0-9]+)?(?=p\\.?$)'), ',', '.') AS points
FROM (
  SELECT horse_id, n,
         LEFT(t, 2) AS mm,
         SUBSTRING_INDEX(SUBSTRING_INDEX(t, ' ', 1), '/', -1) AS y,
         SUBSTRING(t, LOCATE(' ', t) + 1) AS rest
  FROM `_qr_lines`
  WHERE t REGEXP '^[0-9]{2}/[0-9]{2,4} '
) d;

-- 3. Siirrä laatuarvostelut-tauluun
INSERT INTO `quality_reviews` (horse_id, review_date, organizer, placement, points)
SELECT horse_id, review_date, SUBSTRING_INDEX(title, ' ', 1), title, points
FROM `_qr_items`
ORDER BY horse_id, n;

-- 4. Poista siirretyt rivit ja tyhjäksi jäänyt Saavutukset-otsikko
UPDATE horses h
JOIN (SELECT horse_id FROM `_qr_lines` GROUP BY horse_id) a ON a.horse_id = h.id
LEFT JOIN (
  SELECT l.horse_id, GROUP_CONCAT(l.line ORDER BY l.n SEPARATOR '\n') AS description
  FROM `_qr_lines` l
  LEFT JOIN `_qr_items` i ON i.horse_id = l.horse_id AND i.n = l.n
  WHERE i.horse_id IS NULL AND l.t <> 'Saavutukset:'
  GROUP BY l.horse_id
) k ON k.horse_id = h.id
SET h.description = NULLIF(TRIM(BOTH '\n' FROM COALESCE(k.description, '')), '');

-- 5. Päivämäärättömät Saavutukset-rivit (esim. "ktk-kelpoinen")
INSERT INTO `quality_reviews` (horse_id, review_date, organizer, placement)
SELECT id, NULL, UPPER(REGEXP_SUBSTR(r, '^[A-Za-z]+')), LEFT(r, 50)
FROM (SELECT id, TRIM(SUBSTRING_INDEX(SUBSTRING(description, LOCATE('Saavutukset:\n', description) + 13), '\n', 1)) AS r
      FROM horses WHERE description REGEXP '(?m)^Saavutukset:\n[^\n]+$') s
WHERE r <> '';

UPDATE horses
SET description = NULLIF(TRIM(BOTH '\n' FROM REGEXP_REPLACE(description, '(?m)^Saavutukset:\n[^\n]+$', '')), '')
WHERE description REGEXP '(?m)^Saavutukset:\n[^\n]+$';

DROP TABLE IF EXISTS `_qr_items`;
DROP TABLE IF EXISTS `_qr_lines`;
