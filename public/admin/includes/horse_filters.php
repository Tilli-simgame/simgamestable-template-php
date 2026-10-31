<?php
/**
 * Hevoslistojen haku-, suodatus- ja lajittelutyökalut (admin).
 *
 * Käyttö:
 *   $opts = horseFilterOptions($rows);               // kaikki suodatettavat rivit
 *   renderHorseFilterToolbar($opts);                  // haku + rotu + painotus + sukupuoli
 *   <div class="compact-list" data-hf-list ...>       // yksi tai useampi lista
 *     <div class="compact-list-row" <?= horseFilterRowAttrs($h) ?>> ...
 *   <script src=".../assets/js/admin-horse-filter.js"></script>
 *
 * Rivien on sisällettävä: name, gender, breed_name, (call_name), (discipline_list), (stable_id).
 * Tallisuodatin näytetään, kun horseFilterOptions() saa tallilistan.
 */

// Rotuperheet: tunnistetaan rodun nimestä. Ryhmä näytetään vain, jos siihen osuu ≥ 2 listan rotua.
function horseBreedFamilyRules(): array {
    return [
        'welsh'         => ['Welsh (kaikki)',  fn($n) => str_starts_with($n, 'welsh')],
        'shetland'      => ['Shetlanninponit', fn($n) => str_contains($n, 'shetlanninponi')],
        'suomenhevonen' => ['Suomenhevoset',   fn($n) => str_starts_with($n, 'suomenhevonen') || str_starts_with($n, 'suomenpienhevonen')],
        'ratsuponi'     => ['Ratsuponit',      fn($n) => str_ends_with($n, 'ratsuponi')],
        'puoliverinen'  => ['Puoliveriset',    fn($n) => str_contains($n, 'puoliverinen')],
        'taysiverinen'  => ['Täysiveriset',    fn($n) => str_contains($n, 'täysiverinen')],
    ];
}

/**
 * Laskee suodatinvalinnat — vain ne rodut/painotukset, joita riveillä oikeasti on.
 * $stables: [['id' => …, 'name' => …], …] — tallit näytetään aina (myös tyhjät), jotta
 * tallisivun linkki ?talli=<id> toimii.
 */
function horseFilterOptions(array $rows, array $stables = []): array {
    $breeds = [];
    $disciplines = [];
    $genders = [];
    $noBreed = 0;
    $stableCounts = [];
    $noStable = 0;
    foreach ($rows as $h) {
        if (!empty($h['stable_id'])) {
            $stableCounts[(int)$h['stable_id']] = ($stableCounts[(int)$h['stable_id']] ?? 0) + 1;
        } else {
            $noStable++;
        }
        if (!empty($h['breed_name'])) {
            $breeds[$h['breed_name']] = ($breeds[$h['breed_name']] ?? 0) + 1;
        } else {
            $noBreed++;
        }
        foreach ($h['discipline_list'] ?? [] as $dn) {
            $disciplines[$dn] = ($disciplines[$dn] ?? 0) + 1;
        }
        $g = mb_strtolower($h['gender']);
        $genders[$g] = ($genders[$g] ?? 0) + 1;
    }
    $collator = new Collator('fi_FI');
    uksort($breeds, [$collator, 'compare']);
    uksort($disciplines, [$collator, 'compare']);

    $families = [];
    foreach (horseBreedFamilyRules() as $key => [$label, $match]) {
        $members = array_values(array_filter(array_keys($breeds), fn($b) => $match(mb_strtolower($b))));
        if (count($members) >= 2) {
            $families[$key] = [$label, $members, array_sum(array_map(fn($b) => $breeds[$b], $members))];
        }
    }

    return [
        'total'       => count($rows),
        'breeds'      => $breeds,
        'no_breed'    => $noBreed,
        'families'    => $families,
        'disciplines' => $disciplines,
        'genders'     => $genders,
        'stables'     => array_map(fn($s) => [(int)$s['id'], $s['name'], $stableCounts[(int)$s['id']] ?? 0], $stables),
        'no_stable'   => $noStable,
    ];
}

/**
 * Rivin data-attribuutit suodatusta ja lajittelua varten.
 * $extra: lisälajitteluavaimet, esim. ['birth' => '2021-01-20', 'vh' => 'VH…'].
 */
function horseFilterRowAttrs(array $h, array $extra = []): string {
    $attrs = [
        'id'     => (int)$h['id'],
        'name'   => $h['name'],
        'call'   => $h['call_name'] ?? '',
        'breed'  => $h['breed_name'] ?? '',
        'disc'   => implode('|', $h['discipline_list'] ?? []),
        'gender' => mb_strtolower($h['gender']),
        'stable' => $h['stable_id'] ?? '',
    ] + $extra;
    $out = [];
    foreach ($attrs as $k => $v) {
        $out[] = 'data-' . $k . '="' . e((string)($v ?? '')) . '"';
    }
    return implode(' ', $out);
}

/**
 * Hakukenttä, rotu- ja painotusvalinnat sekä sukupuolipainikkeet.
 * Painotusvalinta näytetään vain, jos jollain rivillä on painotus.
 */
function renderHorseFilterToolbar(array $opts, string $placeholder = 'Nimi tai kutsumanimi…'): void {
    $ucfirst = fn($s) => mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    $g = $opts['genders'];
    ?>
<div data-hf-toolbar>
  <div class="hl-toolbar">
    <div class="form-group hl-search">
      <label for="hl-q">Hae nimellä</label>
      <input type="search" id="hl-q" data-hf-q placeholder="<?= e($placeholder) ?>" autocomplete="off">
      <div class="hl-hint" data-hf-hint></div>
    </div>
    <?php if ($opts['stables']): ?>
    <div class="form-group">
      <label for="hl-stable">Talli</label>
      <select id="hl-stable" data-hf-stable>
        <option value="">Kaikki tallit</option>
        <?php foreach ($opts['stables'] as [$sid, $sname, $count]): ?>
        <option value="<?= $sid ?>"><?= e($sname) ?> (<?= $count ?>)</option>
        <?php endforeach; ?>
        <option value="-">Ei tallia (<?= $opts['no_stable'] ?>)</option>
      </select>
      <div class="hl-hint"></div>
    </div>
    <?php endif; ?>
    <div class="form-group">
      <label for="hl-breed">Rotu</label>
      <select id="hl-breed" data-hf-breed>
        <option value="">Kaikki rodut</option>
        <?php if ($opts['families']): ?>
        <optgroup label="Rotuperheet">
          <?php foreach ($opts['families'] as $key => [$label, $members, $count]): ?>
          <option value="family:<?= e($key) ?>" data-members="<?= e(json_encode($members, JSON_UNESCAPED_UNICODE)) ?>"><?= e($label) ?> (<?= $count ?>)</option>
          <?php endforeach; ?>
        </optgroup>
        <?php endif; ?>
        <optgroup label="Rodut">
          <?php foreach ($opts['breeds'] as $breed => $count): ?>
          <option value="<?= e($breed) ?>"><?= e($breed) ?> (<?= $count ?>)</option>
          <?php endforeach; ?>
        </optgroup>
        <?php if ($opts['no_breed']): ?>
        <option value="-">Rotu puuttuu (<?= $opts['no_breed'] ?>)</option>
        <?php endif; ?>
      </select>
      <div class="hl-hint"></div>
    </div>
    <?php if ($opts['disciplines']): ?>
    <div class="form-group">
      <label for="hl-disc">Painotus</label>
      <select id="hl-disc" data-hf-disc>
        <option value="">Kaikki painotukset</option>
        <?php foreach ($opts['disciplines'] as $disc => $count): ?>
        <option value="<?= e($disc) ?>"><?= e($ucfirst($disc)) ?> (<?= $count ?>)</option>
        <?php endforeach; ?>
        <option value="-">Ei painotusta</option>
      </select>
      <div class="hl-hint"></div>
    </div>
    <?php endif; ?>
  </div>
  <div class="hl-statusbar">
    <div class="filter-bar" role="group" aria-label="Sukupuoli">
      <button type="button" class="filter-btn active" data-gender="">Kaikki (<?= $opts['total'] ?>)</button>
      <button type="button" class="filter-btn" data-gender="tamma">Tammat (<?= $g['tamma'] ?? 0 ?>)</button>
      <button type="button" class="filter-btn" data-gender="ori">Oriit (<?= $g['ori'] ?? 0 ?>)</button>
      <button type="button" class="filter-btn" data-gender="ruuna">Ruunat (<?= $g['ruuna'] ?? 0 ?>)</button>
    </div>
    <div class="hl-count"><span data-hf-count><?= $opts['total'] ?></span> / <?= $opts['total'] ?> hevosta
      <button type="button" class="hl-reset" data-hf-reset hidden>Tyhjennä suodattimet</button></div>
  </div>
</div>
    <?php
}
