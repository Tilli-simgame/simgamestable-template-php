# Phase 13: Poisto-hyväksyntätyönkulku - Context

**Gathered:** 2026-07-17
**Status:** Ready for planning
**Mode:** `--auto` — all gray areas auto-resolved with recommended/research-backed defaults, no interactive discussion. Review the decisions below before planning if you want to redirect any of them.

<domain>
## Phase Boundary

Mod-käyttäjän poistopyyntö hevosesta, varsasta, kilpailusta, näyttelytuloksesta tai postauksesta ei poista sisältöä lopullisesti heti vaan pehmeästi piilottaa sen (näkyy heti pois julkiselta sivustolta ja admin-listoilta) ja luo odottavan hyväksyntärivin yhteiseen `pending_deletions`-jonoon. Admin näkee yhdestä näkymästä kaikki viisi sisältötyyppiä ja voi hyväksyä (pysyy piilossa) tai hylätä (palautuu normaalisti näkyväksi) kunkin pyynnön. Admin-etusivulla näkyy odottavien pyyntöjen määrä laskurina. Author-käyttäjä saa poistaa omat postauksensa välittömästi ilman hyväksyntää (ei pending-riviä). Admin itse voi edelleen poistaa mitä tahansa suoraan ilman hyväksyntäkiertoa (roolihierarkian huipulla, ei tarvitse hyväksyntää itseltään).

**Tämä vaihe EI rakenna:** roolikohtaista sisältörajausta (jo valmis Phase 12:sta), kuvien (`horse_photos`) poisto-hyväksyntää (`photo_delete.php` pysyy admin-only, ROADMAP:n viisi sisältötyyppiä eivät sisällä kuvia), mod-puolen ilmoitusnäkymää hylätyille pyynnöille (SC3 vaatii vain että sisältö palautuu näkyväksi — ei notifikaatiovaatimusta), kovaa poistoa (`DELETE`-lausetta) millekään näistä viidestä taulusta.

</domain>

<decisions>
## Implementation Decisions

### Skeema: pending_deletions-jono ja soft-delete-sarakkeet
- **D-01 (auto-selected, research-backed):** Yksi jaettu polymorfinen `pending_deletions`-taulu (`entity_type` ENUM('horse','foal','competition','showrecord','post'), `entity_id`, `requested_by`, `requested_at`, `status` ENUM('pending','approved','rejected'), `reviewed_by`, `reviewed_at`) — EI viittä erillistä status-saraketta per sisältötaulu. Tutkimus (`research/SUMMARY.md` rivi 20) suositteli tätä eksplisiittisesti viiden lähes-identtisen sarakkeen sijaan.
- **D-02 (auto-selected):** `is_deleted TINYINT(1) NOT NULL DEFAULT 0` + `deleted_at TIMESTAMP NULL` lisätään `foals`/`competitions`/`showrecords`/`posts`-tauluihin (`horses`-taulussa on jo nämä sarakkeet Phase 1:stä lähtien — `database/schema.sql` rivit 112-113 — käytetään mallina). Uusi migraatio seuraa `migrate_roles.sql`/`migrate_posts_author.sql`-konventiota (kommenttiheader + phpMyAdmin-ohje + additiiviset ALTER-lauseet).
- **D-03 (auto-selected):** Duplikaattiesto (DEL-05) toteutetaan PHP-tason check-then-insert-tarkistuksena (`SELECT ... WHERE entity_type=? AND entity_id=? AND status='pending'` ennen INSERTiä) EIKÄ tietokannan UNIQUE-rajoitteena. Tutkimus (rivi 122) huomauttaa ettei Altervistan tarkka MySQL/MariaDB-versio ole varmistettu — PHP-tason tarkistus kiertää tämän kokonaan.

### Poisto-käyttäytyminen roolin mukaan
- **D-04 (auto-selected, research-backed):** Mod:n poistopyyntö = välitön soft-delete (`is_deleted=1`, piiloutuu heti julkiselta sivustolta ja admin-listoilta) + uusi `pending_deletions`-rivi (`status='pending'`) admin-hyväksyntää varten. Admin:n oma poisto = suora soft-delete IlMAN pending-riviä (admin ei tarvitse hyväksyntää itseltään). Author:n oman postauksen poisto = välitön soft-delete IlMAN pending-riviä (AUTHOR-03).
- **D-05 (auto-selected):** Hyväksyntä (DEL-02) = ei muuta mitään sisällössä (se on jo `is_deleted=1`) — vain `pending_deletions.status` päivittyy `'approved'`:ksi + `reviewed_by`/`reviewed_at` täytetään. Hylkäys (DEL-03) = sisällön `is_deleted` palautetaan `0`:ksi (+ `deleted_at` NULL) JA `pending_deletions.status` päivittyy `'rejected'`:ksi (rivi säilyy auditointia varten, EI poisteta — tutkimuksen Pitfall 12).

### Admin-hyväksyntänäkymä
- **D-06 (auto-selected, mirrors Phase 11 D-01):** Yksi yhtenäinen `<table>`-näkymä KAIKILLE viidelle sisältötyypille uudessa `admin/deletions.php`:ssä — sarakkeet: Tyyppi (hevonen/varsa/kilpailu/näyttelytulos/postaus), Kohde (nimi/otsikko linkkinä kohteen muokkaussivulle), Pyytäjä (mod:n käyttäjänimi), Pyydetty (aikaleima), Hyväksy/Hylkää-napit per rivi. EI erillisiä tabeja/osioita per sisältötyyppi — sama perustelu kuin Phase 11:ssä (D-01): yksinkertaisin toteutus, pieni volyymi yhden tallin skaalassa.
- **D-07 (auto-selected):** Laskuri (DEL-04) näytetään admin-etusivulla (`admin/index.php`) uutena stat-korttina olemassa olevassa `.admin-stat-row`/`.admin-stat-card`-rivissä (rivit 25-44), ei erillistä nav-badgea. Seuraa ROADMAP:n kirjaimellista sanamuotoa ("admin-etusivulla näkyy... laskurina") ja pitää scope-alueen minimaalisena.
- **D-08 (auto-selected):** Uusi nav-linkki "Poistopyynnöt" `admin_header.php`:n "Sivusto"-osioon (rivit 334-343, `users.php`/`settings.php`-linkkien vieressä), näkyy vain adminille (`isAdmin()`-ehto, sama malli kuin `settings.php`:lla rivillä 340).

### Claude's Discretion
- Hyväksy/hylkää-napit: erilliset pienet inline-POST-lomakkeet listasivulla (`<form method="post" action=".../deletion_approve.php">` + hidden `csrf_token`+`id`) vs. yksi käsittelijä molemmille toiminnoille `action`-parametrilla — noudattaa olemassa olevaa `_delete.php`-inline-lomake-konventiota (`contact_delete.php`, `horse_delete.php`).
- `entity_type`-arvojen tarkka whitelist-toteutus (esim. `match()`-lauseke joka mappaa `entity_type` → taulunimeen/nimikkeeseen/muokkaussivun URL:iin) — tutkimus mainitsi tämän kriittisenä pitfallina (whitelist-pohjainen entity_type-to-table-lookup, ei dynaamista taulunimen rakennusta käyttäjän syötteestä).
- Jokaisen viiden `_delete.php`-käsittelijän tarkka refaktorointitapa (yhteinen `insertPendingDeletion()`-apufunktio `helpers.php`:hen tutkimuksen suosituksen mukaisesti, rivi 86) vs. duplikoitu logiikka per tiedosto.
- Julkisen sivuston ja admin-listojen `is_deleted = 0`-suodatuksen tarkka lisäyskohta jokaiseen neljään uuteen tauluun kohdistuvaan kyselyyn (audit-passi, tutkimuksen suosittelema, rivi 86) — kaikki löydettävät kyselyt on käytävä läpi.
- Hyväksyntä/hylkäysnappien tarkka sanamuoto ja `.btn`/`.btn-sm`-tyylitys (uudelleenkäytä olemassa olevia CSS-luokkia).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Vaatimukset ja roadmap
- `.planning/ROADMAP.md` §"Phase 13: Poisto-hyväksyntätyönkulku" — goal, success criteria (SC1-SC5), requirements-lista (MOD-06, AUTHOR-03, DEL-01..05).
- `.planning/REQUIREMENTS.md` rivit 36, 41-45, 51 — MOD-06, DEL-01..05, AUTHOR-03; jäljitettävyystaulukko rivi 117 selittää MIKSI nämä on ryhmitelty Phase 13:een (riippuvat `pending_deletions`-skeemasta, poistohaarautuminen mod/admin/author on yksi looginen kokonaisuus).
- `.planning/PROJECT.md` §"Current Milestone: v1.2 Käyttäjäroolit" — roolien lopulliset oikeuskuvaukset.
- `.planning/research/SUMMARY.md` rivit 10-20, 62, 66, 84-99, 122 — "Phase 4: Delete-Approval Workflow" -osio: suositeltu skeema (`pending_deletions`-jono viiden erillisen status-sarakkeen sijaan), toteutusjärjestys, Pitfall 8 (ei paikkaa pending-tilalle → ratkaistu jaetulla jonotaululla), Pitfall 10 (moniselitteinen näkyvyys → ratkaistu välittömällä ja peruutettavalla soft-deletellä), Pitfall 11 (duplikaattipyynnöt → check-then-insert-guard), Pitfall 12 (hylätyt pyynnöt eivät saa kadota, vain vaihtaa tilaa).

### Phase 10/11/12 -perusta (laajennettava, ei korvattava)
- `.planning/phases/12-sis-lt-tyyppien-roolirajaus/12-CONTEXT.md` + `12-SECURITY.md` — sisältötyyppien roolirajaus jo valmis; `requireRole('admin','mod')` jo voimassa kaikilla viidellä relevantilla listasivulla (`horses.php`/`foals.php`/`competitions.php`/`showrecords.php`/`posts.php`), `requireOwnResourceOrAdmin()`-malli `helpers.php`:ssä johon uusi omistajuuslogiikka (author-oman-postauksen-poisto) voi nojata.
- `.planning/phases/10-roolit-ja-autentikaation-perusta/10-CONTEXT.md` — `requireRole()`/`currentRole()`/`isAdmin()`-perusta, `admin/ei-oikeutta.php`-uudelleenohjausmalli.
- `.planning/phases/11-k-ytt-j-hallinta/11-CONTEXT.md` D-01 — taulukkomallinen listanäkymä (ei compact-list), toiminnot suoraan näkyvissä joka rivillä ilman klikkausta auki — sama malli valittu tässäkin (D-06).
- `public/src/includes/helpers.php` — `requireRole()` (rivi 84), `currentRole()` (rivi 67), `isAdmin()` (rivi 74), `requireOwnResourceOrAdmin()` (Phase 12), `$_SESSION['admin_id']`.
- `database/migrate_roles.sql` + `database/migrate_posts_author.sql` — migraatiokonventio (kommenttiheader, phpMyAdmin-ohje, additiiviset lauseet) jota uusi migraatio seuraa.

### Nykyinen soft-delete-esimerkki ja poisto-endpointit
- `database/schema.sql` rivit 76-136 (`horses`-taulu, erityisesti rivit 111-113, 121) — ainoa nykyinen `is_deleted`/`deleted_at`-esimerkki, malli neljälle uudelle taululle.
- `public/admin/horse_delete.php` — nykyinen admin-only välitön-soft-delete-malli (`requireRole('admin')`, CSRF, `UPDATE ... SET is_deleted=1, deleted_at=NOW()`) — laajennettava mod-haaralla + pending-rivin luonnilla.
- `public/admin/post_delete.php`, `public/admin/contact_delete.php`, `public/admin/photo_delete.php` — muut nykyiset poisto-käsittelijät samalla inline-POST-lomake-mallilla. **Huom:** `photo_delete.php` EI kuulu tämän vaiheen scopeen (ei ROADMAP:n viidessä sisältötyypissä).
- `public/admin/index.php` rivit 1-45 — admin-etusivun stat-korttirivi (`.admin-stat-row`/`.admin-stat-card`), johon DEL-04-laskuri lisätään.
- `public/admin/includes/admin_header.php` rivit 290-344 — nav-rakenne, "Sivusto"-osio (rivit 334-343) johon uusi "Poistopyynnöt"-linkki lisätään admin-only-ehdolla.

**No external specs beyond the above** — vaatimukset on kokonaan lukittu ROADMAP.md/REQUIREMENTS.md:ssä ja research/SUMMARY.md:ssä; tässä (auto-mode) tehdyt päätökset noudattavat tutkimuksen suosituksia sellaisenaan.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `requireRole('admin','mod')` — jo voimassa kaikilla viidellä content-listasivulla (Phase 10/12), laajenee kattamaan myös näiden sivujen `_delete.php`-käsittelijät (nykyään `requireRole('admin')`-only).
- `requireOwnResourceOrAdmin()` (`helpers.php`, Phase 12) — malli jota author-oman-postauksen-poisto-logiikka voi seurata (vaikka author-poisto ei vaadi hyväksyntää, omistajuustarkistus on silti tarpeen ettei author voi poistaa muiden postauksia).
- `.admin-stat-row`/`.admin-stat-card`-CSS (`admin/index.php`) — uudelleenkäytetään DEL-04-laskurikortille.
- `.admin-nav-item`-CSS + `isAdmin()`-ehtomalli (`admin_header.php` rivi 340, `settings.php`-linkki) — uudelleenkäytetään "Poistopyynnöt"-nav-linkille.
- `generate_csrf_token()`/`validate_csrf_token()` — kaikki uudet POST-toiminnot (hyväksy, hylkää, mod:n poistopyyntö).

### Established Patterns
- Inline-POST-lomake + `confirm()`-onclick-poistomalli (`horse_delete.php`, `contact_delete.php`) — sama malli soveltuu hyväksy/hylkää-napeille (ei tarvitse `confirm()`:ia näille, koska molemmat ovat peruutettavissa/ei-tuhoisia).
- `UPDATE ... SET is_deleted=1, deleted_at=NOW() WHERE id=:id AND is_deleted=0` -ehto (`horse_delete.php`) estää tuplapäivityksen — sama WHERE-ehtomalli laajenee neljään uuteen tauluun.
- Taulukkomallinen admin-listanäkymä ilman klikkausta-auki (Phase 11 D-01, `users.php`) — sama malli `admin/deletions.php`:lle.

### Integration Points
- Viisi `_delete.php`-käsittelijää (`horse_delete.php` + neljä uutta/muokattua vastinetta `foal_delete.php`/`competition_delete.php`/`showrecord_delete.php`/`post_delete.php`) tarvitsevat roolihaarautuvan logiikan (mod → pending-rivi, admin → suora, author-oma-postaus → suora).
- Jokaisen viiden sisältötyypin listakysely (`horses.php`, `foals.php`, `competitions.php`, `showrecords.php`, `posts.php`) ja julkisen sivuston vastaavat kyselyt tarvitsevat `WHERE is_deleted = 0` -suodatuksen niille neljälle taululle joissa sitä ei vielä ole (`horses.php` on jo suodatettu Phase 1:stä).
- Uusi `admin/deletions.php` + `deletion_approve.php`/`deletion_reject.php` (tai yksi yhdistetty käsittelijä) tarvitsevat `pending_deletions`-taulun JOIN-kyselyt kaikkiin viiteen sisältötauluun (whitelist-pohjainen entity_type-to-table-mappaus, ei dynaamista SQL:ää käyttäjän syötteestä).

</code_context>

<specifics>
## Specific Ideas

Ei käyttäjän antamia visuaalisia/tekstitoiveita — kaikki päätökset tässä on auto-valittu tutkimuksen suositusten ja olemassa olevien Phase 10/11/12-konventioiden perusteella (`--auto`-tila, ei interaktiivista keskustelua käyty). Jos jokin päätös ei vastaa toivottua suuntaa, muokkaa tätä tiedostoa ennen `/gsd-plan-phase 13` -ajoa, tai aja `/gsd-discuss-phase 13` uudelleen ilman `--auto`-lippua käydäksesi keskustelun manuaalisesti.

</specifics>

<deferred>
## Deferred Ideas

Ei uusia scope-ehdotuksia noussut (auto-tila, ei käyttäjän syötettä). Seuraavat asiat on tietoisesti rajattu tämän vaiheen ulkopuolelle:
- Kuvien (`horse_photos`) poisto-hyväksyntä — `photo_delete.php` pysyy admin-only, ei ROADMAP:n viidessä sisältötyypissä.
- Mod-puolen ilmoitus-/historianäkymä hylätyille pyynnöille — ei roadmapin success-kriteereissä (SC3 vaatii vain että sisältö palautuu näkyväksi).
- Nav-badge laskurille admin-etusivun stat-kortin lisäksi — pidetty roadmapin kirjaimellisessa scopessa.

### Reviewed Todos (not folded)
None — `todo.match-phase` ei löytänyt osumia (todo_count: 0).

</deferred>

---

*Phase: 13-Poisto-hyväksyntätyönkulku*
*Context gathered: 2026-07-17*
