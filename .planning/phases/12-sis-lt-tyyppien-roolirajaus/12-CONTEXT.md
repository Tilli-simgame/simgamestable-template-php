# Phase 12: Sisältötyyppien roolirajaus - Context

**Gathered:** 2026-07-16
**Status:** Ready for planning

<domain>
## Phase Boundary

Mod voi luoda ja muokata hevosia (+kuvat), varsoja, kilpailuja ja näyttelytuloksia sekä postauksia. Author voi luoda uuden postauksen ja muokata/nähdä VAIN omia postauksiaan, ja voi linkittää olemassa oleviin hevosiin (read-only valinta, ei muokkausoikeutta hevostietoihin). Kumpikaan rooli ei pääse käyttäjähallintaan eikä teema-asetuksiin.

**Tärkeä löydös keskustelun pohjalta:** Phase 10 teki jo TÄYDEN sivukartoituksen (D-01, 10-CONTEXT.md) — `requireRole('admin','mod')` on jo voimassa `horses.php`/`horse_add.php`/`horse_edit.php`/`foals.php`/`foal_add.php`/`foal_edit.php`/`competitions.php`/`showrecords.php`/`photos.php`/`photo_update.php`/`horse_import_vrl.php`:ssä, ja `requireRole('admin','mod','author')` jo `posts.php`:ssä. `users.php`/`settings.php` ja koko `user_*.php`-perhe ovat jo `requireRole('admin')`-tasolla. **MOD-01..05 ja suurin osa MOD-07/AUTHOR-05:sta ovat siis rakenteellisesti jo valmiit ennen Phase 12:n suunnittelua.** Tämä vahvistettiin keskustelussa eksplisiittisesti — käyttäjä ei löytänyt erityistapauksia jotka vaatisivat lisätyötä tällä alueella.

**Phase 12:n todellinen uusi työ rajoittuu postausten omistajuuteen (AUTHOR-02, AUTHOR-04):**
- `posts.author_id`-sarakkeen lisäys migraationa (ei ole olemassa — vahvistettu `schema.sql`:stä)
- Vanhojen postausten taannehtiva täyttö
- Uusien postausten author_id-asetus
- Author-roolin omistajuussuodatus `posts.php`:ssä (yksi tiedosto joka hoitaa listan+lisäyksen+muokkauksen — ei erillisiä `post_add.php`/`post_edit.php`-tiedostoja kuten `horses.php`-perheellä)
- Suora-URL-muokkausyrityksen esto muiden postauksiin (SC3: ohjaa "Ei käyttöoikeutta" -näkymään, `admin/ei-oikeutta.php`, sama malli kuin Phase 10:ssä)

**Tämä vaihe EI rakenna:** poisto-hyväksyntätyönkulkua (MOD-06, AUTHOR-03 → Phase 13, riippuvat `pending_deletions`-skeemasta), mod:n uutta roolisuojauskoodia hevosille/varsoille/kilpailuille/näyttelytuloksille/kuville (jo olemassa Phase 10:stä).

</domain>

<decisions>
## Implementation Decisions

### Vanhojen postausten author_id (taannehtiva täyttö)
- **D-01:** Migraatio lisää `posts.author_id`-sarakkeen ja backfillaa KAIKKI olemassa olevat postaukset alkuperäiselle `admin`-tunnukselle (`UPDATE posts SET author_id = (SELECT id FROM admin_users WHERE username = 'admin')`), ei jätetä NULL:ksi.
- Perustelu: jokaisella postauksella on aina jokin tekijä — omistajuustarkistukset (`author_id = :current_user_id`) toimivat suoraan kaikkialla ilman erillistä NULL-erikoiskäsittelyä (`OR author_id IS NULL`) missään kyselyssä.

### Uusien postausten author_id-käytäntö
- **D-02:** JOKAINEN uusi postaus — riippumatta luojan roolista (admin/mod/author) — saa `author_id`:n = `$_SESSION['admin_id']`. Ei roolikohtaista haaraa INSERT-logiikkaan.
- Perustelu: yhdenmukainen data, ei muuta admin/mod:n oikeuksia mihinkään (he eivät ole omistajuusrajattuja) — omistajuussuodatus koskee vain author-roolin näkymää/muokkausta.

### Authorin postauslistan laajuus
- **D-03:** Author-rooli näkee `posts.php`-listassa VAIN omat postauksensa (`WHERE author_id = :current_user_id` kun `currentRole() === 'author'`). Author ei näe edes että muita postauksia on olemassa.
- Perustelu: yksinkertaisin toteutus — ei tarvita "Muokkaa"-napin piilotus/esto-logiikkaa rivikohtaisesti listassa, koska muiden postausten rivejä ei koskaan renderöidä authorille.
- **Seuraus suoralle URL-yritykselle:** koska lista on jo suodatettu, "Ei käyttöoikeutta" -esto (SC3) tarvitaan vain `action=edit&id=X`-suoran osoitteen varalle (author kirjoittaa/arvaa toisen postauksen ID:n suoraan URL:iin) — ei listan renderöinnissä.

### Mod/author-rajauksen kattavuus
- **D-04:** Ei erityistapauksia löytynyt keskustelussa — Phase 10:n sivutason rajaus (hevoset/varsat/kilpailut/näyttelytulokset/kuvat/VRL-tuonti = admin+mod; users/settings = admin-only) hyväksyttiin sellaisenaan riittäväksi. Phase 12:n suunnittelun tulee silti sisältää lyhyt verifiointipassi (grep `requireRole`) varmistamaan ettei mikään ole muuttunut Phase 10/11:n jälkeen, mutta uutta koodia ei odoteta tälle alueelle.

### Claude's Discretion
- `requireOwnResourceOrAdmin()`-tyylisen apufunktion tarkka signatuuri `helpers.php`:ssa (vai inline-tarkistus suoraan `posts.php`:ssä) — tutkimus (`research/SUMMARY.md`) ehdotti nimeä, mutta toteutustapa on suunnittelijan päätettävissä.
- POST-käsittelijän (tallennus) puolen omistajuustarkistus (defense-in-depth CSRF:n lisäksi, IDOR-suojaus myös crafted-POST-pyynnöille, ei vain GET-suoralle URL:lle) — tekninen turvallisuusyksityiskohta, tutkimus jo tunnisti tämän kriittiseksi pitfalliksi (Pitfall 2).
- Migraatiotiedoston tarkka nimi/rakenne (esim. `database/migrate_posts_author.sql`) — seuraa olemassa olevaa `migrate_roles.sql`/`migrate_theme.sql`-konventiota (kommenttiheader + phpMyAdmin-ohje + eksplisiittinen `UPDATE`).
- Virheviestien tarkka sanamuoto "Ei käyttöoikeutta" -uudelleenohjauksessa (sama sivu/malli kuin Phase 10:ssä, ei uutta viestiä).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Vaatimukset ja roadmap
- `.planning/ROADMAP.md` §"Phase 12: Sisältötyyppien roolirajaus" — goal, success criteria, requirements-lista (MOD-01..05, MOD-07, AUTHOR-01, AUTHOR-02, AUTHOR-04, AUTHOR-05).
- `.planning/REQUIREMENTS.md` — MOD-01..07 (rivit 31-37), AUTHOR-01..05 (rivit 49-53); jäljitettävyystaulukko (rivi 117) selittää MIKSI MOD-06/AUTHOR-03 on siirretty Phase 13:een (riippuvat `pending_deletions`-skeemasta).
- `.planning/PROJECT.md` §"Current Milestone: v1.2 Käyttäjäroolit" — roolien lopulliset oikeuskuvaukset.
- `.planning/research/SUMMARY.md` rivit 79-82, 100, 120 — Phase 3 (=tämä Phase 12) -osio: suositeltu arkkitehtuuri (`requireOwnResourceOrAdmin()`), Pitfall 2 (IDOR), avoin backfill-kysymys (nyt ratkaistu D-01:ssä).

### Phase 10/11 -perusta (laajennettava, ei korvattava)
- `.planning/phases/10-roolit-ja-autentikaation-perusta/10-CONTEXT.md` — D-01/D-02/D-03: täysi sivukartoitus jo tehty, mod:n create/edit-pääsy sisältösivuille jo myönnetty, poisto-endpointit pysyvät admin-only Phase 13:aan asti.
- `.planning/phases/11-k-ytt-j-hallinta/11-CONTEXT.md` — käyttäjähallinnan admin-only-rajaus, ei relevantti muutoin tälle vaiheelle paitsi vahvistuksena ettei `admin_users`-tauluun kosketa tässä vaiheessa.
- `public/src/includes/helpers.php` — `requireRole()` (rivi 84), `currentRole()` (rivi 67), `isAdmin()` (rivi 74), `$_SESSION['admin_id']` (käytetään ownership-tarkistuksiin ja uusien postausten author_id-asetukseen).
- `public/admin/ei-oikeutta.php` — olemassa oleva "Ei käyttöoikeutta" -sivu (Phase 10, D-04/D-05) — uudelleenkäytetään AUTHOR-02:n suoran-URL-eston kohteena.
- `database/schema.sql` rivit 261-270 — `posts`-taulun nykyinen rakenne (ei vielä `author_id`-saraketta, vahvistettu greppäämällä koko `database/`-kansio).
- `database/migrate_roles.sql` — edellisen vaiheen migraatiokonventio (kommenttiheader, phpMyAdmin-ohje, eksplisiittinen `UPDATE`) jota uusi `posts.author_id`-migraatio seuraa.

### Postausten nykyinen toteutus
- `public/admin/posts.php` — koko postausten list+lisäys+muokkaus yhdessä tiedostossa (rivit 1-408): POST-käsittelijä (rivit 9-61, INSERT/UPDATE), GET-näkymälogiikka (rivit 63-96, `action=edit&id=X`), listakysely (rivi 105, ei vielä omistajuussuodatusta), hevoslinkitys-widget (rivit 107-131, 254-407 — säilyy sellaisenaan, jo roolineutraali).
- `public/admin/post_delete.php` — pysyy `requireRole('admin')`-tasolla (ei muutosta, AUTHOR-03 on Phase 13:ssa).

**No external specs beyond the above** — vaatimukset on kokonaan lukittu ROADMAP.md/REQUIREMENTS.md:ssä, tässä keskustelussa tehdyt päätökset täydentävät niitä.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `requireRole('admin','mod')` / `requireRole('admin','mod','author')` — jo käytössä kaikilla relevanteilla sisältösivuilla (ks. domain-osio), ei muutostarvetta.
- `currentRole()`/`$_SESSION['admin_id']` (`helpers.php`) — suoraan käytettävissä `posts.php`:n omistajuussuodatukseen ja uusien postausten author_id-asetukseen.
- `admin/ei-oikeutta.php` — uudelleenkäytetään suoran-URL-muokkausyrityksen kohteena.
- `database/migrate_roles.sql` -konventio — migraatiotiedoston rakennemalli.

### Established Patterns
- List+add+edit yhdessä tiedostossa `$_GET['action']`-parametrilla (`posts.php`) EI seuraa muun koodikannan list+`_add.php`+`_edit.php`+`_delete.php`-erillistiedostokonventiota (`horses.php`/`contacts.php`-perheet) — tämä on postausten oma, jo olemassa oleva rakenne josta Phase 12 ei poikkea.
- Sivutason roolisuojaus tapahtuu tiedoston alussa (`requireRole(...)` heti `db.php`-includen jälkeen) — sama paikka jota mahdollinen inline-omistajuustarkistus `posts.php`:n `action=edit`-haarassa seuraisi.

### Integration Points
- `posts.php`:n listakysely (rivi 105) ja `action=edit`-haara (rivit 70-87) ovat ainoat kaksi kohtaa jotka tarvitsevat roolitietoisen `author_id`-suodatuksen/tarkistuksen.
- POST-käsittelijän INSERT-lause (rivi 44) tarvitsee `author_id`-sarakkeen lisäyksen (D-02).

</code_context>

<specifics>
## Specific Ideas

Ei erillisiä ulkoasu-/tekstitoiveita — postausten hallinnan oletetaan säilyvän visuaalisesti ennallaan. Ainoa eksplisiittinen toiminnallinen muutos on omistajuussuodatus (D-03) ja "Ei käyttöoikeutta" -uudelleenohjaus suoralla URL:lla (jo olemassa oleva sivu, ei uutta suunnittelua).

</specifics>

<deferred>
## Deferred Ideas

Ei uusia scope-ehdotuksia noussut keskustelun aikana. Seuraaviin vaiheisiin jo roadmapissa kuuluvat asiat (ei toistettu tässä):
- Poisto-hyväksyntätyönkulku (`pending_deletions`, MOD-06, AUTHOR-03, DEL-01..05) → Phase 13.

### Reviewed Todos (not folded)
None — `todo.match-phase` ei löytänyt osumia (todo_count: 0).

</deferred>

---

*Phase: 12-Sisältötyyppien roolirajaus*
*Context gathered: 2026-07-16*
