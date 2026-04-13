<?php
// form_prof_recap.php — Récapitulatif des saisies (heures par cours + km global + vue globale éditable)

declare(strict_types=1);

// -----------------------------------------------------------------------------
// Bootstrap portail prof : session + config + libs communes
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page();

// -----------------------------------------------------------------------------
// ENV DEV / PROD
// -----------------------------------------------------------------------------
$env   = $config['env'] ?? 'DEV';
$isDev = ($env === 'DEV');

if ($isDev) {
  ini_set('display_errors', '1');
  error_reporting(E_ALL);
} else {
  ini_set('display_errors', '0');
  ini_set('log_errors', '1');
  error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
}

// -----------------------------------------------------------------------------
// Session minimale
// -----------------------------------------------------------------------------
if (empty($_SESSION['display']) && empty($_SESSION['firstname'])) {
  header('Location: portail_prof.php');
  exit;
}

// -----------------------------------------------------------------------------
// Connexion SQL
// -----------------------------------------------------------------------------
require_once $config['base_path'] . '/app/config/db_config.php';
$conn = db();
if (!$conn) die("❌ Erreur connexion SQL");

// -----------------------------------------------------------------------------
// Garde centrale (prof/admin)
// -----------------------------------------------------------------------------
audra_guard_prof_page($conn);

// -----------------------------------------------------------------------------
// Portail global : fenêtre d’ouverture/fermeture (legacy)
// -----------------------------------------------------------------------------
$isAdmin        = !empty($_SESSION['admin']) && $_SESSION['admin'];
$modeCorrection = !empty($_SESSION['mode_correction']) && $_SESSION['mode_correction'];

$tz    = new DateTimeZone('Europe/Paris');
$today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');

$isOpen = false;
$sql = "SELECT TOP 1 ouverture, fermeture, etat FROM dbo.AudraWeb_Regles_Periodiques ORDER BY id DESC";
$stmt = sqlsrv_query($conn, $sql);
$rule = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
if ($stmt) sqlsrv_free_stmt($stmt);

if ($rule && isset($rule['etat']) && strtoupper(trim((string)$rule['etat'])) === 'OUVERT') {
  $open  = ($rule['ouverture'] instanceof DateTime) ? $rule['ouverture']->format('Y-m-d') : null;
  $close = ($rule['fermeture'] instanceof DateTime) ? $rule['fermeture']->format('Y-m-d') : null;
  if ($open && $close && $today >= $open && $today <= $close) $isOpen = true;
}

if (!$isAdmin && !$modeCorrection && !$isOpen) {
  header('Location: saisie_fermee.php');
  exit;
}

// DEBUG
if ($isDev) {
  echo "<!-- DEBUG prof_code=" . htmlspecialchars($_SESSION['prof_code'] ?? '') . " -->";
}

// -----------------------------------------------------------------------------
// Contexte prof / période
// -----------------------------------------------------------------------------
$prof = (string)($_SESSION['display'] ?? '');
$annee = (int)($_SESSION['annee'] ?? (int)date('Y'));
$mois  = (int)($_SESSION['mois']  ?? (int)date('n'));

// Admin : accepte override URL
if ($isAdmin) {
  if (isset($_GET['annee'])) $annee = (int)$_GET['annee'];
  if (isset($_GET['mois']))  $mois  = (int)$_GET['mois'];
  if (isset($_GET['prof']) && $_GET['prof'] !== '') $prof = (string)$_GET['prof'];
  if (isset($_GET['code']) && $_GET['code'] !== '') $_SESSION['prof_code'] = (string)$_GET['code'];
}

$profId = (isset($_SESSION['prof_code']) && $_SESSION['prof_code'] !== '')
  ? strtoupper(trim((string)$_SESSION['prof_code']))
  : '';
$isAdminView = isset($_GET['admin']) && (int)$_GET['admin'] === 1;

$retourControleUrl = "/audra_portail_prod/admin_controle_saisie.php?prof=" . urlencode((string)$prof)
                   . "&code=" . urlencode((string)$profId)
                   . "&annee=" . (int)$annee
                   . "&mois=" . (int)$mois;
// -----------------------------------------------------------------------------
// Statut prof (TNS / salarié) (laissé en place, utile ailleurs)
// -----------------------------------------------------------------------------
$statutProf = '';
if ($profId !== '') {
  $__st = sqlsrv_query($conn, "SELECT TOP 1 xx_Statut_prof FROM dbo.Colleague WHERE Id = ?", [$profId]);
  if ($__st && ($__r = sqlsrv_fetch_array($__st, SQLSRV_FETCH_ASSOC))) $statutProf = (string)($__r['xx_Statut_prof'] ?? '');
  if ($__st) sqlsrv_free_stmt($__st);
}
$isSalarie = (stripos($statutProf, 'salari') !== false);
$isTNS     = !$isSalarie;

// -----------------------------------------------------------------------------
// 1) Heures par cours (tableau du haut)
// -----------------------------------------------------------------------------
if ($profId !== '') {
  $sqlHeures = "
    SELECT s.id_cours, SUM(CAST(s.duree AS FLOAT)) AS heures
    FROM dbo.AudraWeb_Saisie_Heures_Web s
    WHERE YEAR(s.date_seance)=? AND MONTH(s.date_seance)=?
      AND s.id_prof=?
      AND ISNULL(s.annule_admin,0)=0
    GROUP BY s.id_cours
    ORDER BY s.id_cours
  ";
  $paramsHeures = [$annee, $mois, $profId];
} else {
  $sqlHeures = "
    SELECT s.id_cours, SUM(CAST(s.duree AS FLOAT)) AS heures
    FROM dbo.AudraWeb_Saisie_Heures_Web s
    WHERE YEAR(s.date_seance)=? AND MONTH(s.date_seance)=?
      AND UPPER(LTRIM(RTRIM(s.nom_formateur)))=UPPER(?)
      AND ISNULL(s.annule_admin,0)=0
    GROUP BY s.id_cours
    ORDER BY s.id_cours
  ";
  $paramsHeures = [$annee, $mois, $prof];
}

$stmt = sqlsrv_query($conn, $sqlHeures, $paramsHeures);
$cours = [];
$totalHeures = 0.0;
if ($stmt) {
  while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $cours[] = $row;
    $totalHeures += (float)($row['heures'] ?? 0);
  }
  sqlsrv_free_stmt($stmt);
}

$_SESSION['declared_hours'] = $totalHeures;

// -----------------------------------------------------------------------------
// 1.b) Détail séances + extras (mode/km)
// -----------------------------------------------------------------------------
if ($profId !== '') {
  $sqlDetail = "
    SELECT
      s.id,
      s.id_cours,
      s.date_seance,
      s.heure_debut,
      s.heure_fin,
      s.duree,
      s.confirme_admin,
      COALESCE(x.mode, 'PRESENTIEL') AS mode,
      COALESCE(x.km, 0) AS km
    FROM dbo.AudraWeb_Saisie_Heures_Web s
    LEFT JOIN dbo.AudraWeb_Seance_Extras x ON x.id_seance = s.id
    WHERE YEAR(s.date_seance)=? AND MONTH(s.date_seance)=?
      AND s.id_prof=?
      AND ISNULL(s.annule_admin,0)=0
    ORDER BY s.id_cours, s.date_seance, s.heure_debut, s.id
  ";
  $paramsDetail = [$annee, $mois, $profId];
} else {
  $sqlDetail = "
    SELECT
      s.id,
      s.id_cours,
      s.date_seance,
      s.heure_debut,
      s.heure_fin,
      s.duree,
      s.confirme_admin,
      COALESCE(x.mode, 'PRESENTIEL') AS mode,
      COALESCE(x.km, 0) AS km
    FROM dbo.AudraWeb_Saisie_Heures_Web s
    LEFT JOIN dbo.AudraWeb_Seance_Extras x ON x.id_seance = s.id
    WHERE YEAR(s.date_seance)=? AND MONTH(s.date_seance)=?
      AND UPPER(LTRIM(RTRIM(s.nom_formateur)))=UPPER(?)
      AND ISNULL(s.annule_admin,0)=0
    ORDER BY s.id_cours, s.date_seance, s.heure_debut, s.id
  ";
  $paramsDetail = [$annee, $mois, $prof];
}

$seancesDetail = [];
$totalDetailHeures = 0.0;
$kmSeancesTotal = 0.0;

$stDetail = sqlsrv_query($conn, $sqlDetail, $paramsDetail);
if ($stDetail) {
  while ($r = sqlsrv_fetch_array($stDetail, SQLSRV_FETCH_ASSOC)) {

    $d = $r['date_seance'];
    $dateAff = ($d instanceof DateTimeInterface) ? $d->format('d/m/Y') : date('d/m/Y', strtotime((string)$d));

    $hd = $r['heure_debut'];
    $hf = $r['heure_fin'];
    $debut = ($hd instanceof DateTimeInterface) ? $hd->format('H:i') : substr(str_replace('.',':',(string)$hd),0,5);
    $fin   = ($hf instanceof DateTimeInterface) ? $hf->format('H:i') : substr(str_replace('.',':',(string)$hf),0,5);

    $h = (float)($r['duree'] ?? 0);
    $totalDetailHeures += $h;

    $kmRow = (float)($r['km'] ?? 0);
    if ($kmRow < 0) $kmRow = 0;
    $kmSeancesTotal += $kmRow;

    $seancesDetail[] = [
      'id'       => (int)($r['id'] ?? 0),
      'id_cours' => (string)($r['id_cours'] ?? ''),
      'date'     => $dateAff,
      'debut'    => $debut,
      'fin'      => $fin,
      'duree'    => $h,
      'lock'     => !empty($r['confirme_admin']),
      'mode'     => (string)($r['mode'] ?? 'PRESENTIEL'),
      'km'       => $kmRow,
    ];
  }
  sqlsrv_free_stmt($stDetail);
}

// -----------------------------------------------------------------------------
// 2) KM global (meta) — pour compat admin (affichage du haut)
// -----------------------------------------------------------------------------
$kmTotal = 0.0;
if ($profId !== '') {
  $stmtKm = sqlsrv_query($conn,
    "SELECT TOP 1 km FROM dbo.AudraWeb_Saisie_Meta_Web WHERE id_prof=? AND annee=? AND mois=? ORDER BY horodatage DESC",
    [$profId, $annee, $mois]
  );
} else {
  $stmtKm = sqlsrv_query($conn,
    "SELECT TOP 1 km FROM dbo.AudraWeb_Saisie_Meta_Web WHERE UPPER(nom_formateur)=UPPER(?) AND annee=? AND mois=? ORDER BY horodatage DESC",
    [$prof, $annee, $mois]
  );
}
if ($stmtKm && ($rowKm = sqlsrv_fetch_array($stmtKm, SQLSRV_FETCH_ASSOC))) {
  $kmTotal = (float)($rowKm['km'] ?? 0);
  sqlsrv_free_stmt($stmtKm);
}

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------
function toISO(string $fr): string {
  $fr = trim($fr);
  $p = explode('/', $fr);
  if (count($p) !== 3) return '';
  return $p[2].'-'.str_pad($p[1],2,'0',STR_PAD_LEFT).'-'.str_pad($p[0],2,'0',STR_PAD_LEFT);
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Récapitulatif des saisies</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{font-family:sans-serif;margin:0;background:#f9fafb;color:#111}
  h1{font-size:20px;margin-bottom:12px}
  table{width:100%;border-collapse:collapse;margin-bottom:20px}
  th,td{border:1px solid #ddd;padding:8px;text-align:center}
  th{background:#f3f4f6}
  tfoot td{font-weight:bold}
  .summary{margin:15px 0;font-size:15px;font-weight:600}
  .actions{display:flex;justify-content:space-between;gap:10px;margin-top:20px}
  .btn{padding:8px 14px;border:none;border-radius:6px;cursor:pointer}
  .btn-back{background:#e5e7eb}
  .btn-next{background:#1d4ed8;color:#fff}
  .banner{padding:10px 15px;border-radius:6px;margin-bottom:20px;font-weight:600}
  .prof-mode{background:#d1fae5;color:#065f46;}
  .admin-mode{background:#fde68a;color:#92400e;}
  .correction-mode{background:#fef3c7;color:#92400e;}
  .table-detail caption{font-weight:700;text-align:left;margin:8px 0}
  .filter-line{display:flex;align-items:center;gap:8px;margin:10px 0}
  .row-locked{opacity:.7}
  .btn-mini{padding:4px 8px;border-radius:6px;border:1px solid #ddd;background:#f8fafc;cursor:pointer}
  .btn-mini:hover{background:#eef2ff}
  .input-td{width:110px}
  .saved-ok{outline:2px solid #22c55e; outline-offset:-2px;}
</style>
</head>
<body>

<div class="banner <?= $isAdmin ? 'admin-mode' : ($modeCorrection ? 'correction-mode' : 'prof-mode') ?>">
  <?php if ($isAdmin): ?>
    🔑 Mode Contrôle Admin — Espace de <?= htmlspecialchars($prof) ?> — <?= htmlspecialchars((string)$mois) ?>/<?= (int)$annee ?>
  <?php elseif ($modeCorrection): ?>
    ✏️ Mode Correction — <?= htmlspecialchars($prof) ?> — <?= htmlspecialchars((string)$mois) ?>/<?= (int)$annee ?>
  <?php else: ?>
    👤 Mode Professeur — Espace de <?= htmlspecialchars($prof) ?> — <?= htmlspecialchars((string)$mois) ?>/<?= (int)$annee ?>
  <?php endif; ?>
</div>

<div class="container" style="padding:20px">

  <?php if ($isAdminView): ?>
    <div style="margin-bottom:12px;">
      <a href="<?= htmlspecialchars($retourControleUrl, ENT_QUOTES, 'UTF-8') ?>"
         class="btn btn-back"
         style="display:inline-block;text-decoration:none;">
        ⬅ Retour contrôle
      </a>
    </div>
  <?php endif; ?>

  <h1>Récapitulatif des saisies</h1>

  <table>
    <thead>
      <tr><th>Cours</th><th>Heures</th></tr>
    </thead>
    <tbody>
      <?php if (empty($cours)): ?>
        <tr><td colspan="2">Aucune saisie enregistrée pour ce mois.</td></tr>
      <?php else: ?>
        <?php foreach ($cours as $c): ?>
          <tr>
            <td><?= htmlspecialchars((string)$c['id_cours']) ?></td>
            <td><?= number_format((float)($c['heures'] ?? 0), 1, ',', ' ') ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <td>Total du mois</td>
        <td><?= number_format((float)$totalHeures, 1, ',', ' ') ?></td>
      </tr>
    </tfoot>
  </table>

  <div class="summary">
    Kilomètres déclarés du mois : <span id="km-month"><?= number_format((float)$kmTotal, 1, ',', ' ') ?></span> km
  </div>

  <h2 style="margin-top:24px">Vue globale des séances (tous les cours) -  Vous pouvez encore modifier vos cours dans le tableau ci dessous</h2>
  <div id="recapMsg" style="display:none; margin:10px 0 14px 0; padding:12px 14px; background:#fee2e2; border:1px solid #dc2626; color:#991b1b; border-radius:8px; font-weight:600;"></div>

  <?php if (empty($seancesDetail)): ?>
    <div class="summary">Aucune séance saisie pour ce mois.</div>
  <?php else: ?>

  <div class="filter-line" style="justify-content:space-between;flex-wrap:wrap;">
    <div>
      <label for="filtreCours"><b>Filtrer par cours :</b></label>
      <select id="filtreCours">
        <option value="">Tous les cours</option>
        <?php
          $dejavu = [];
          foreach ($seancesDetail as $s) {
            $cid = (string)$s['id_cours'];
            if (!isset($dejavu[$cid])) {
              $dejavu[$cid] = true;
              echo '<option value="'.htmlspecialchars($cid, ENT_QUOTES).'">'.htmlspecialchars($cid)."</option>";
            }
          }
        ?>
      </select>
    </div>

    <form action="/audra_portail_prod/actions/export_seances_csv.php" method="get" target="_blank" style="margin-left:auto;display:flex;align-items:center;gap:10px">
      <input type="hidden" name="annee" value="<?= (int)$annee ?>">
      <input type="hidden" name="mois"  value="<?= (int)$mois ?>">
      <?php if (!empty($profId)): ?>
        <input type="hidden" name="id_prof" value="<?= htmlspecialchars($profId, ENT_QUOTES, 'UTF-8') ?>">
      <?php endif; ?>
      <button type="submit" class="btn btn-next">⬇ Exporter en CSV</button>
      <small style="color:#6b7280">Excel FR ; séparateur « ; », décimales « , »</small>
    </form>
  </div>

  <table id="table-detail" class="table-detail">
    <caption>N° cours — Date — Horaire — Durée — Mode — KM — Actions</caption>
    <thead>
      <tr>
        <th>N° cours</th>
        <th>Date</th>
        <th>Début</th>
        <th>Fin</th>
        <th>Durée (h)</th>
        <th>Mode</th>
        <th>KM</th>
        <th style="width:220px">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($seancesDetail as $row):
      $id = (int)$row['id'];
      $cid = (string)$row['id_cours'];
      $isLocked = !empty($row['lock']);
      $canEdit = $isAdmin || !$isLocked;
      $mode = strtoupper((string)($row['mode'] ?? 'PRESENTIEL'));
      $km   = (float)($row['km'] ?? 0);
    ?>
      <tr data-id="<?= (int)$id ?>" data-cours="<?= htmlspecialchars($cid, ENT_QUOTES) ?>" class="<?= ($isLocked && !$isAdmin) ? 'row-locked' : '' ?>">
        <td><?= htmlspecialchars($cid) ?><?php if ($isLocked): ?> <span title="Validée admin">🔒</span><?php endif; ?></td>

        <td class="td-date">
          <input class="input-td se-date" type="date" data-id="<?= (int)$id ?>"
                 value="<?= htmlspecialchars(toISO((string)$row['date']), ENT_QUOTES, 'UTF-8') ?>"
                 <?= (!$canEdit ? 'disabled' : '') ?>>
        </td>

        <td class="td-debut">
          <input class="input-td se-debut" type="time" data-id="<?= (int)$id ?>"
                 value="<?= htmlspecialchars((string)$row['debut'], ENT_QUOTES, 'UTF-8') ?>"
                 <?= (!$canEdit ? 'disabled' : '') ?>>
        </td>

        <td class="td-fin">
  <input class="input-td se-fin" type="text" inputmode="numeric" placeholder="HH:MM" data-id="<?= (int)$id ?>"
         value="<?= htmlspecialchars((string)$row['fin'], ENT_QUOTES, 'UTF-8') ?>"
         <?= (!$canEdit ? 'disabled' : '') ?>>
</td>

        <td class="td-duree"><?= number_format((float)$row['duree'], 2, ',', ' ') ?></td>

        <td class="td-mode">
          <select class="se-extra-mode" data-id="<?= (int)$id ?>" <?= (!$canEdit ? 'disabled' : '') ?>>
            <option value="PRESENTIEL" <?= ($mode === 'PRESENTIEL') ? 'selected' : '' ?>>PRESENTIEL</option>
            <option value="VISIO"      <?= ($mode === 'VISIO') ? 'selected' : '' ?>>VISIO</option>
          </select>
        </td>

        <td class="td-km">
          <input type="text" inputmode="decimal"
                 class="se-extra-km input-td"
                 data-id="<?= (int)$id ?>"
                 value="<?= htmlspecialchars(number_format($km, 1, ',', ''), ENT_QUOTES, 'UTF-8') ?>"
                 <?= (!$canEdit ? 'disabled' : '') ?>>
        </td>

        <td>
          <?php if ($canEdit): ?>
            <button class="btn-mini" onclick="delRow(<?= (int)$id ?>)">🗑 Supprimer</button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="6" style="text-align:right;font-weight:bold">Total heures (détail)</td>
        <td id="hours-detail-total" style="font-weight:bold"><?= number_format((float)$totalDetailHeures, 2, ',', ' ') ?></td>
        <td></td>
      </tr>
      <tr>
        <td colspan="6" style="text-align:right;font-weight:bold">Total KM (séances)</td>
        <td id="km-seances-total" style="font-weight:bold"><?= number_format((float)$kmSeancesTotal, 1, ',', ' ') ?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>

  <div id="recapDirtyBox" style="display:none; margin:16px 0; padding:14px 16px; background:#fef3c7; border:2px solid #f59e0b; border-radius:10px;">
  <div style="font-weight:800; font-size:18px; color:#92400e; margin-bottom:10px; text-transform:uppercase;">
    ⚠️ Vous avez modifié le récapitulatif
  </div>

  <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
    <button type="button" class="btn btn-next" id="btnRefreshTotals">✅ Enregistrer vos modifications</button>
    <span style="color:#78350f; font-size:14px;">
      Enregistrez vos modifications avant de passer aux justificatifs.
    </span>
  </div>
</div>

<script>
  const AUDRA_ACTIONS = "/audra_portail_prod/actions";
  const META_ANNEE = <?= (int)$annee ?>;
  const META_MOIS  = <?= (int)$mois ?>;
  const META_CODE  = <?= json_encode((string)($profId !== '' ? $profId : ($_SESSION['prof_code'] ?? ''))) ?>;
</script>

<script>
(function(){

  function getBtnNextJustifs() {
    return document.getElementById('btnNextJustifs');
  }

  function getDirtyBox() {
    return document.getElementById('recapDirtyBox');
  }

  function setNextJustifsEnabled(enabled) {
    const btnNextJustifs = getBtnNextJustifs();
    if (!btnNextJustifs) return;
    btnNextJustifs.disabled = !enabled;
    btnNextJustifs.style.opacity = enabled ? '1' : '0.5';
    btnNextJustifs.style.cursor  = enabled ? 'pointer' : 'not-allowed';
  }

  function setDirtyBannerVisible(visible) {
    const box = getDirtyBox();
    if (!box) return;
    box.style.display = visible ? '' : 'none';
  }

  // Au chargement : bouton suivant actif, bandeau caché
  setNextJustifsEnabled(true);
  setDirtyBannerVisible(false);

  function markRecapDirty() {
    setNextJustifsEnabled(false);
    setDirtyBannerVisible(true);
  }
  
    function showRecapMsg(msg) {
    const box = document.getElementById('recapMsg');
    if (!box) return;
    box.textContent = msg || '';
    box.style.display = msg ? '' : 'none';
  }

  function clearRecapMsg() {
    showRecapMsg('');
  }

  // --------------------------------------------------------------------------
  // Filtre cours
  // --------------------------------------------------------------------------
  const sel = document.getElementById('filtreCours');
  if (sel) {
    sel.addEventListener('change', function(){
      const v = this.value;
      document.querySelectorAll('#table-detail tbody tr').forEach(tr => {
        tr.style.display = (!v || tr.dataset.cours === v) ? '' : 'none';
      });
    });
  }

  // --------------------------------------------------------------------------
  // Helpers nombres FR
  // --------------------------------------------------------------------------
  function parseNumFR(s){
    let v = String(s ?? '').trim();
    if (v === '') return 0;
    v = v.replace(',', '.');
    const n = parseFloat(v);
    return (isNaN(n) || !isFinite(n) || n < 0) ? 0 : n;
  }

  function fmtFR(n, dec){
    const f = (Math.round(n * Math.pow(10, dec)) / Math.pow(10, dec)).toFixed(dec);
    return f.replace('.', ',');
  }

  function formatKmInput(inp){
    const n = parseNumFR(inp.value || '0');
    inp.value = fmtFR(n, 1);
  }

  function computeKmTotalFromInputs(){
    let tot = 0;
    document.querySelectorAll('.se-extra-km').forEach(inp => {
      const id = inp.getAttribute('data-id');
      const selMode = document.querySelector('.se-extra-mode[data-id="'+id+'"]');
      const mode = (selMode && selMode.value) ? selMode.value.toUpperCase() : 'PRESENTIEL';
      let v = parseNumFR(inp.value || '0');
      if (mode === 'VISIO') v = 0;
      tot += v;
    });
    return Math.round(tot * 10) / 10;
  }

  function computeHoursTotalFromCells(){
    let tot = 0;
    document.querySelectorAll('#table-detail tbody tr').forEach(tr => {
      const td = tr.querySelector('.td-duree');
      if (!td) return;
      tot += parseNumFR(td.textContent);
    });
    return Math.round(tot * 100) / 100;
  }

  function updateTotalsUI(){
    const kmTot = computeKmTotalFromInputs();
    const hTot  = computeHoursTotalFromCells();

    const elKmMonth = document.getElementById('km-month');
    if (elKmMonth) elKmMonth.textContent = fmtFR(kmTot, 1);

    const elKm = document.getElementById('km-seances-total');
    if (elKm) elKm.textContent = fmtFR(kmTot, 1);

    const elH = document.getElementById('hours-detail-total');
    if (elH) elH.textContent = fmtFR(hTot, 2);
  }

  // --------------------------------------------------------------------------
  // Autosave (blur/change) — debounce
  // --------------------------------------------------------------------------
  const _rowTimers = new Map();
  const _rowSaving = new Set();
  let _lastSaveError = '';

  function scheduleSave(id){
    id = parseInt(id || '0', 10);
    if (!id) return;

    if (_rowTimers.has(id)) clearTimeout(_rowTimers.get(id));

    const t = setTimeout(() => {
      _rowTimers.delete(id);
      saveRowAuto(id);
    }, 450);

    _rowTimers.set(id, t);
  }

  async function syncMetaKmFromInputs(){
    const kmTot = computeKmTotalFromInputs();
    const fd = new FormData();
    fd.append('id_prof', META_CODE);
    fd.append('prof_code', META_CODE);
    fd.append('annee', String(META_ANNEE));
    fd.append('mois',  String(META_MOIS));
    fd.append('km', String(kmTot));
    fd.append('km_total', String(kmTot));
    fd.append('as_json', '1');

    await fetch(AUDRA_ACTIONS + '/save_meta.php', {
      method: 'POST',
      body: fd,
      credentials: 'include',
      cache: 'no-store',
      headers: {'X-Requested-With':'XMLHttpRequest'}
    });
  }

  async function saveRowAuto(id){
    id = parseInt(id || '0', 10);
    if (!id) return;
    if (_rowSaving.has(id)) return;
    _rowSaving.add(id);

    const tr = document.querySelector('tr[data-id="'+id+'"]');
    if (!tr) {
      _rowSaving.delete(id);
      return;
    }

    try {
      const cid = tr.dataset.cours || '';

      const iDate   = tr.querySelector('.se-date[data-id="'+id+'"]');
      const iDeb    = tr.querySelector('.se-debut[data-id="'+id+'"]');
      const iFin    = tr.querySelector('.se-fin[data-id="'+id+'"]');
      const selMode = tr.querySelector('.se-extra-mode[data-id="'+id+'"]');
      const inpKm   = tr.querySelector('.se-extra-km[data-id="'+id+'"]');

      if (!iDate || !iDeb || !iFin || !selMode || !inpKm) return;

      const iso = (iDate.value || '').trim();
      const deb = (iDeb.value || '').trim();
      const fin = (iFin.value || '').trim();
      if (!iso || !deb || !fin) return;

      const p  = iso.split('-');
      const fr = (p.length === 3) ? (p[2].padStart(2,'0') + '/' + p[1].padStart(2,'0') + '/' + p[0]) : '';

      const mode = (selMode.value || 'PRESENTIEL').toUpperCase();

      formatKmInput(inpKm);
      let km = parseNumFR(inpKm.value || '0');
      if (mode === 'VISIO') km = 0;

      // 1) save_saisie (date/heure)
      const fd1 = new FormData();
      fd1.append('id', String(id));
      fd1.append('id_cours', String(cid));
      fd1.append('date', fr);
      fd1.append('debut', deb);
      fd1.append('fin', fin);

      const r1 = await fetch(AUDRA_ACTIONS + '/save_saisie.php', {
        method: 'POST',
        body: fd1,
        credentials: 'include',
        cache: 'no-store'
      });
            const j1 = await r1.json().catch(() => null);
if (!(j1 && j1.success)) {
  const msg = (j1 && j1.error) ? j1.error : 'Enregistrement impossible.';
  _lastSaveError = msg;
  showRecapMsg(msg);
  console.warn('save_saisie failed', j1);
  return;
}

_lastSaveError = '';
clearRecapMsg();;

      if (j1.duree != null) {
        const tdD = tr.querySelector('.td-duree');
        if (tdD) tdD.textContent = fmtFR(parseFloat(j1.duree) || 0, 2);
      }

      // 2) save_extras (mode/km)
      const fd2 = new FormData();
      fd2.append('id_seance', String(id));
      fd2.append('mode', mode);
      fd2.append('faf', '0');
      fd2.append('km', String(km));

      const r2 = await fetch(AUDRA_ACTIONS + '/save_seance_extras.php', {
        method: 'POST',
        body: fd2,
        credentials: 'include',
        cache: 'no-store'
      });
            const j2 = await r2.json().catch(() => null);
if (!(j2 && j2.success)) {
  const msg = (j2 && j2.error) ? j2.error : 'Enregistrement des options impossible.';
  _lastSaveError = msg;
  showRecapMsg(msg);
  console.warn('save_extras failed', j2);
  return;
}

_lastSaveError = '';
clearRecapMsg();

      // 3) sync meta km
      try {
        await syncMetaKmFromInputs();
      } catch(e) {
        console.warn('sync meta failed', e);
      }

      tr.classList.add('saved-ok');
      setTimeout(() => tr.classList.remove('saved-ok'), 600);
      updateTotalsUI();

    } finally {
      _rowSaving.delete(id);
    }
  }

  // --------------------------------------------------------------------------
  // VISIO => KM=0 et grisé
  // --------------------------------------------------------------------------
  function applyVisioRuleForRow(id){
    const selMode = document.querySelector('.se-extra-mode[data-id="'+id+'"]');
    const inpKm   = document.querySelector('.se-extra-km[data-id="'+id+'"]');
    if (!selMode || !inpKm) return;

    if ((selMode.value || '').toUpperCase() === 'VISIO') {
      inpKm.value = '0,0';
      inpKm.disabled = true;
    } else {
      inpKm.disabled = false;
    }
  }

  document.querySelectorAll('.se-extra-mode').forEach(sel => {
    applyVisioRuleForRow(sel.getAttribute('data-id'));
  });

  // --------------------------------------------------------------------------
  // Enter => blur (déclenche autosave)
  // --------------------------------------------------------------------------
  document.addEventListener('keydown', function(ev){
    const t = ev.target;
    if (!t) return;
    if (
      ev.key === 'Enter' &&
      (
        t.classList.contains('se-extra-km') ||
        t.classList.contains('se-date') ||
        t.classList.contains('se-debut') ||
        t.classList.contains('se-fin')
      )
    ) {
      ev.preventDefault();
      t.blur();
    }
  }, true);

    // --------------------------------------------------------------------------
  // Input => on marque la page comme modifiée seulement s'il y a un vrai changement
  // --------------------------------------------------------------------------
  document.addEventListener('input', function(ev){
    const t = ev.target;
    if (!t) return;

    if (
      t.classList.contains('se-extra-km') ||
      t.classList.contains('se-debut') ||
      t.classList.contains('se-fin')
    ) {
      markRecapDirty();
    }
  }, true);



  // --------------------------------------------------------------------------
  // Blur => autosave
  // --------------------------------------------------------------------------
  document.addEventListener('blur', function(ev){
    const t = ev.target;
    if (!t) return;

    if (t.classList.contains('se-extra-km')) {
      formatKmInput(t);
      scheduleSave(t.getAttribute('data-id'));
      updateTotalsUI();
      return;
    }

    if (
      t.classList.contains('se-date') ||
      t.classList.contains('se-debut') ||
      t.classList.contains('se-fin')
    ) {
      scheduleSave(t.getAttribute('data-id'));
      return;
    }
  }, true);

  // --------------------------------------------------------------------------
  // Change => date / mode
  // --------------------------------------------------------------------------
  document.addEventListener('change', function(ev){
    const t = ev.target;
    if (!t) return;

    if (t.classList.contains('se-date')) {
      markRecapDirty();
      return;
    }

    if (t.classList.contains('se-extra-mode')) {
      const id = t.getAttribute('data-id');
      applyVisioRuleForRow(id);
      updateTotalsUI();
      markRecapDirty();
      scheduleSave(id);
    }
  }, true);

  // --------------------------------------------------------------------------
  // Delete row
  // --------------------------------------------------------------------------
    async function delRow(id){
    if (!confirm('Supprimer cette séance ?')) return;

    const fd = new FormData();
    fd.append('id', String(id));

    try {
      const r = await fetch(AUDRA_ACTIONS + '/delete_saisie.php', {
        method: 'POST',
        body: fd,
        credentials: 'include',
        cache: 'no-store'
      });
      const js = await r.json().catch(() => null);
      if (!(js && js.success)) {
        const msg = (js && js.error) ? js.error : 'Suppression impossible';
        _lastSaveError = msg;
        showRecapMsg(msg);
        alert(msg);
        return;
      }

      document.querySelector('tr[data-id="'+id+'"]')?.remove();

      // La page a changé : on bloque le bouton suivant
      markRecapDirty();
      _lastSaveError = '';
      clearRecapMsg();

      try {
        await syncMetaKmFromInputs();
      } catch(e) {
        console.warn('sync meta failed (delete)', e);
      }
      updateTotalsUI();

    } catch(e) {
      _lastSaveError = 'Erreur réseau ou serveur pendant la suppression.';
      showRecapMsg(_lastSaveError);
      alert('Erreur réseau');
    }
  }
  window.delRow = delRow;

  // --------------------------------------------------------------------------
  // Bouton refresh (reload propre)
  // --------------------------------------------------------------------------
  document.getElementById('btnRefreshTotals')?.addEventListener('click', () => {
  try { document.activeElement?.blur(); } catch(e){}

  const btn = document.getElementById('btnRefreshTotals');
  if (btn) {
    btn.disabled = true;
    btn.textContent = '⏳ Actualisation…';
  }

  const started = Date.now();
  (function poll(){
    const pending = (_rowTimers.size > 0) || (_rowSaving.size > 0);
    if (!pending || (Date.now() - started) > 10000) {
      if (_lastSaveError) {
        if (btn) {
          btn.disabled = false;
          btn.textContent = '✅ Enregistrer vos modifications';
        }
        return;
      }
      location.reload();
      return;
    }
    setTimeout(poll, 200);
  })();
});

})();
</script>

  <?php endif; ?>

  <div class="actions">
  <form action="form_prof_meta.php" method="get">
    <?php if ($isAdminView): ?>
      <input type="hidden" name="prof"  value="<?= htmlspecialchars((string)$prof, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="code"  value="<?= htmlspecialchars((string)$profId, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="annee" value="<?= (int)$annee ?>">
      <input type="hidden" name="mois"  value="<?= (int)$mois ?>">
    <?php endif; ?>
    <button type="submit" class="btn btn-back">
      <?= $isAdminView ? '📋 Voir infos & kilomètres' : '← Retour (infos supplémentaires)' ?>
    </button>
  </form>

  <form action="form_prof_seances.php" method="get">
    <?php if ($isAdminView): ?>
      <input type="hidden" name="prof"  value="<?= htmlspecialchars((string)$prof, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="code"  value="<?= htmlspecialchars((string)$profId, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="annee" value="<?= (int)$annee ?>">
      <input type="hidden" name="mois"  value="<?= (int)$mois ?>">
    <?php endif; ?>
    <input type="hidden" name="force" value="1">
    <button type="submit" class="btn btn-back">
      <?= $isAdminView ? '✏️ Ajouter / compléter des cours' : '✏️ Vous avez oublié un cours ? Modifier mes cours' ?>
    </button>
  </form>

  <?php if ($isAdminView): ?>
    <a href="<?= htmlspecialchars($retourControleUrl, ENT_QUOTES, 'UTF-8') ?>"
       class="btn btn-back"
       style="display:inline-block;text-decoration:none;">
      ⬅ Retour contrôle
    </a>
  <?php endif; ?>

  <?php if (!$isAdmin): ?>
  <form action="form_prof_upload.php" method="get">
    <input type="hidden" name="annee" value="<?= (int)$annee ?>">
    <input type="hidden" name="mois"  value="<?= (int)$mois ?>">
    <button type="submit" id="btnNextJustifs" class="btn btn-next">Suivant : Mes justificatifs →</button>
  </form>
<?php endif; ?>
</div>
</div>

</body>
</html>