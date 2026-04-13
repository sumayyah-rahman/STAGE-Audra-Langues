<?php
// form_prof_seances.php — saisie des heures (séances) pour un mois donné

declare(strict_types=1);

// -----------------------------------------------------------------------------
// Bootstrap portail prof : session + config + libs communes
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page();

// -----------------------------------------------------------------------------
// ENV DEV / PROD (basé sur $config['env'])
// -----------------------------------------------------------------------------
if (($config['env'] ?? 'DEV') === 'DEV') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    // ini_set('error_log', 'C:\\data\\audra\\logs\\php_errors_portail_profs.log');
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
}

// -----------------------------------------------------------------------------
// Garde de session minimale
// -----------------------------------------------------------------------------
if (empty($_SESSION['display'])) {
    // Sur le nouveau portail, on renvoie vers portail_prof.php
    header('Location: portail_prof.php');
    exit;
}

// -----------------------------------------------------------------------------
// Connexion SQL centrale via app/config/db_config.php
// -----------------------------------------------------------------------------
require_once $config['base_path'] . '/app/config/db_config.php';
$conn = db();
if (!$conn) {
    die('❌ Connexion SQL impossible');
}

// -----------------------------------------------------------------------------
// Garde centrale de sécurité (blocage / correction / portail)
// -----------------------------------------------------------------------------
audra_guard_prof_page($conn);

$isAdmin        = !empty($_SESSION['admin']) && $_SESSION['admin'];
$modeCorrection = !empty($_SESSION['mode_correction']) && $_SESSION['mode_correction'];

$tz       = new DateTimeZone('Europe/Paris');
$todayObj = new DateTimeImmutable('now', $tz);
$todayYmd = $todayObj->format('Y-m-d');

$isOpen = false;
$rule   = null;

$sql = "
    SELECT TOP 1
        ouverture,
        fermeture,
        etat,
        mois_cible,
        annee_cible
    FROM dbo.AudraWeb_Regles_Periodiques
    WHERE UPPER(RTRIM(LTRIM(etat))) = 'OUVERT'
    ORDER BY id DESC
";
$stmt = sqlsrv_query($conn, $sql);
$rule = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
if ($stmt) {
    sqlsrv_free_stmt($stmt);
}

if ($rule) {
    $openDT  = ($rule['ouverture'] instanceof DateTimeInterface)
        ? DateTimeImmutable::createFromInterface($rule['ouverture'])->setTimezone($tz)
        : null;

    $closeDT = ($rule['fermeture'] instanceof DateTimeInterface)
        ? DateTimeImmutable::createFromInterface($rule['fermeture'])->setTimezone($tz)
        : null;

    $etat = strtoupper(trim((string)($rule['etat'] ?? '')));
    if ($etat === 'OUVERT' && $openDT && $closeDT) {
        $openYmd  = $openDT->format('Y-m-d');
        $closeYmd = $closeDT->format('Y-m-d');

        if ($todayYmd >= $openYmd && $todayYmd <= $closeYmd) {
            $isOpen = true;
        }
    }
}

if (!$isAdmin && !$modeCorrection && !$isOpen) {
    header('Location: saisie_fermee.php');
    exit;
}

$PROF   = strtoupper(trim($_SESSION['display']));
$prenom = $_SESSION['firstname'] ?? '';
$nom    = $_SESSION['lastname'] ?? '';

// ✅ Id Colleague/EBP du prof
$profId = (isset($_SESSION['prof_code']) && $_SESSION['prof_code'] !== '')
        ? strtoupper(trim((string)$_SESSION['prof_code']))
        : '';

/* ------------------------------------------------------------- */
/* 🎯 Contexte mois / année
   - Admin        : lit toujours ?annee= & ?mois= dans l’URL
   - Prof / Normal: lit la règle active explicite (mois_cible / annee_cible)
   ⇒ On ne déduit plus le mois depuis la date d’ouverture.
*/
/* ------------------------------------------------------------- */
if ($isAdmin) {
    $annee = isset($_GET['annee']) ? (int)$_GET['annee'] : (int)date('Y');
    $mois  = isset($_GET['mois'])  ? (int)$_GET['mois']  : (int)date('n');
} else {
    if (!$modeCorrection) {
        $annee = (int)($rule['annee_cible'] ?? 0);
        $mois  = (int)($rule['mois_cible']  ?? 0);

        if ($annee <= 0 || $mois < 1 || $mois > 12) {
            header("Location: saisie_fermee.php");
            exit;
        }

        // On stocke pour cohérence de navigation
        $_SESSION['annee'] = $annee;
        $_SESSION['mois']  = $mois;
    } else {
        // En correction, on garde le mois déjà piloté par la session
        $annee = isset($_SESSION['annee']) ? (int)$_SESSION['annee'] : (int)date('Y');
        $mois  = isset($_SESSION['mois'])  ? (int)$_SESSION['mois']  : (int)date('n');

        if ($annee === 0 || $mois === 0) {
            header("Location: saisie_fermee.php");
            exit;
        }
    }
}

$forceSeances = (isset($_GET['force']) && (string)$_GET['force'] === '1');

// ============================================================================
// ✅ MODE CORRECTION : si des séances existent déjà ce mois, on envoie directement
// vers le récapitulatif (évite l'écran "séances" vide).
// ============================================================================
// ⚠️ Exception : si force=1, on reste sur cette page pour permettre d’ajouter un cours oublié.
if (!$isAdmin && $modeCorrection && !$forceSeances) {

    $hasAny = false;

    if ($profId !== '') {
        $stAny = sqlsrv_query($conn,
            "SELECT TOP 1 1
             FROM dbo.AudraWeb_Saisie_Heures_Web
             WHERE id_prof = ?
               AND YEAR(date_seance)=? AND MONTH(date_seance)=?",
            [$profId, (int)$annee, (int)$mois]
        );
        $hasAny = (bool)($stAny && sqlsrv_fetch_array($stAny, SQLSRV_FETCH_ASSOC));
        if ($stAny) sqlsrv_free_stmt($stAny);
    } else {
        // fallback au nom (legacy)
        $stAny = sqlsrv_query($conn,
            "SELECT TOP 1 1
             FROM dbo.AudraWeb_Saisie_Heures_Web
             WHERE UPPER(nom_formateur)=UPPER(?)
               AND YEAR(date_seance)=? AND MONTH(date_seance)=?",
            [$PROF, (int)$annee, (int)$mois]
        );
        $hasAny = (bool)($stAny && sqlsrv_fetch_array($stAny, SQLSRV_FETCH_ASSOC));
        if ($stAny) sqlsrv_free_stmt($stAny);
    }

    if ($hasAny) {
        $url = "form_prof_recap.php?prof=" . urlencode($PROF)
             . "&code=" . urlencode($profId !== '' ? $profId : ($_SESSION['prof_code'] ?? ''))
             . "&annee=" . (int)$annee
             . "&mois="  . (int)$mois;

        header('Location: ' . $url);
        exit;
    }
}
// ============================================================================


$lastDayInMonth = cal_days_in_month(CAL_GREGORIAN, (int)$mois, (int)$annee);
$moisNoms = ["Janvier","Février","Mars","Avril","Mai","Juin","Juillet",
             "Août","Septembre","Octobre","Novembre","Décembre"];
$moisTxt = $moisNoms[$mois-1] ?? $mois;

// 🔑 Mode admin : accès direct à un cours
$coursAdmin = null;
if ($isAdmin && !empty($_GET['cours'])) {
    $coursAdmin = (int)$_GET['cours'];
}

// 🔐 Clé utilisée pour la mémoire locale des totaux (même logique que form_prof_recap)
$storageProfKey = ($profId !== '' ? $profId : $PROF);
$retourProf  = (string)$PROF;
$retourCode  = (string)($profId !== '' ? $profId : ($_SESSION['prof_code'] ?? ''));
$retourAnnee = (int)$annee;
$retourMois  = (int)$mois;

// ---------------------------------------------------------------------------
// Cours par défaut depuis la base (dernier cours saisi ce mois-là)
// ---------------------------------------------------------------------------
$defaultCoursFromDB = null;
if ($profId !== '') {
    $sqlLast = "SELECT TOP 1 id_cours
                FROM dbo.AudraWeb_Saisie_Heures_Web
                WHERE id_prof = ?
                  AND YEAR(date_seance) = ?
                  AND MONTH(date_seance) = ?
                ORDER BY date_seance DESC, heure_debut DESC, id DESC";
    $stLast = sqlsrv_query($conn, $sqlLast, [$profId, (int)$annee, (int)$mois]);
} else {
    $sqlLast = "SELECT TOP 1 id_cours
                FROM dbo.AudraWeb_Saisie_Heures_Web
                WHERE UPPER(nom_formateur)=UPPER(?)
                  AND YEAR(date_seance)=?
                  AND MONTH(date_seance)=?
                ORDER BY date_seance DESC, heure_debut DESC, id DESC";
    $stLast = sqlsrv_query($conn, $sqlLast, [$PROF, (int)$annee, (int)$mois]);
}
if ($stLast) {
    if ($rLast = sqlsrv_fetch_array($stLast, SQLSRV_FETCH_ASSOC)) {
        $defaultCoursFromDB = (int)$rLast['id_cours'];
    }
    sqlsrv_free_stmt($stLast);
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8" />
<title>Saisie des heures — <?= htmlspecialchars($prenom." ".$nom) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  body{
    font-family:sans-serif;
    background:#f9fafb;
    margin:2rem;
  }

  .panel{
    background:#fff;
    padding:1.5rem;
    margin:auto;
    border-radius:10px;
    box-shadow:0 2px 6px rgba(0,0,0,.1);
    max-width:1000px;
    position:relative;
  }

  h1{
    margin-top:0;
    font-size:20px;
    color:#1d4ed8;
  }

  .prof-banner{
    padding:.7rem 1rem;
    border-radius:6px;
    margin:1rem 0;
    font-weight:bold;
    font-size:16px;
  }

  .prof-mode{background:#dcfce7;color:#065f46;}
  .admin-mode{background:#fde68a;color:#92400e;}
  .correction-mode{background:#fef3c7;color:#92400e;}

  select,
  input{
    padding:.4rem;
    border-radius:6px;
    border:1px solid #ccc;
  }

  table{
    width:100%;
    border-collapse:collapse;
    margin-top:15px;
  }

  th,td{
    border:1px solid #ddd;
    padding:6px;
    text-align:center;
    font-size:14px;
  }

  th{
    background:#f3f4f6;
  }

  .btn{
    cursor:pointer;
    padding:4px 8px;
    border-radius:6px;
    border:none;
    font-size:13px;
  }

  .btn-del{background:#dc2626;color:#fff;}
  .btn-ok{background:#16a34a;color:#fff;}
  .btn-edit{background:#2563eb;color:#fff;}
  .btn-add{background:#16a34a;color:#fff;margin-top:10px;}

  #total{
    margin-top:10px;
    font-weight:bold;
  }

  #actionsSousTableau{
    margin-top:20px;
    display:flex;
    gap:10px;
  }

  .errorMsg{
    color:#b91c1c;
    font-weight:bold;
    margin-top:8px;
  }

  .invalid{
    border:2px solid red !important;
    background:#fee2e2 !important;
  }

  #msgLock{
    color:#b91c1c;
    font-weight:bold;
    margin:10px 0;
    display:none;
  }

  /* --- Mini-liste des séances du cours --- */
  #miniListWrap{
    margin:8px 0 10px 0;
    background:#f8fafc;
    border:1px solid #e5e7eb;
    border-radius:8px;
    padding:10px;
  }

  #miniListHead{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:6px;
  }

  #miniList{
    margin:0;
    padding-left:18px;
  }

  #miniList li{
    margin:2px 0;
  }

  #miniList .mini-link{
    margin-left:8px;
    font-size:12px;
    text-decoration:underline;
    cursor:pointer;
  }

  #miniListEmpty{
    color:#6b7280;
    font-style:italic;
  }

  .btn-tutoriel{
    position:absolute;
    top:18px;
    right:18px;
    display:inline-block;
    padding:8px 14px;
    border-radius:8px;
    background:#2563eb;
    color:#fff;
    text-decoration:none;
    font-weight:bold;
    font-size:14px;
    box-shadow:0 2px 6px rgba(0,0,0,.15);
  }

  .btn-tutoriel:hover{
    background:#1d4ed8;
    color:#fff;
    text-decoration:none;
  }
</style>
</head>
<body>
<div class="panel">

  <div style="display:flex;justify-content:flex-start;align-items:center;margin-bottom:12px;">
    <a href="/modules/portail_prof/form_prof_intro.php"
       style="display:inline-block;padding:8px 14px;border-radius:8px;background:#6b7280;color:#fff;text-decoration:none;font-weight:bold;box-shadow:0 2px 6px rgba(0,0,0,.12);">
      ⬅ Retour écran précédent
    </a>
  </div>

  <a class="btn-tutoriel"
     href="/modules/portail_prof/tutoriel.php?from=seances">
    Consultez le Tutoriel
  </a>

  <div class="prof-banner 
      <?= $isAdmin ? 'admin-mode' : ($modeCorrection ? 'correction-mode' : 'prof-mode') ?>">
    <?php if ($isAdmin): ?>
      🔑 Mode Contrôle Admin — Espace de <?= htmlspecialchars($PROF) ?> — <?= $moisTxt ?> <?= $annee ?>
    <?php elseif ($modeCorrection): ?>
      ✏️ Mode Correction — <?= htmlspecialchars($PROF) ?> — <?= $moisTxt ?> <?= $annee ?>
    <?php else: ?>
      👤 Mode Professeur — Espace de <?= htmlspecialchars($PROF) ?> — <?= $moisTxt ?> <?= $annee ?>
    <?php endif; ?>
  </div>

  <h1>1) Sélectionner un cours</h1>
  <select id="cours"><option value="">— Sélectionnez un cours —</option></select>

  <div id="coursZone" style="display:none">
    <h2 id="titreCours"></h2>

    <!-- Mini-liste (lecture rapide) des séances du cours -->
    <div id="miniListWrap" style="display:none;">
      <div id="miniListHead">
        <div><strong>📅 Séances du cours sélectionné</strong></div>
        <button type="button" class="btn" onclick="toggleMiniList()">Afficher/Masquer</button>
      </div>
      <div id="miniListBody">
        <div id="miniListEmpty">Aucune séance enregistrée pour ce cours.</div>
        <ul id="miniList"></ul>
      </div>
    </div>

    <div id="msgLock">
      🔒 Ce cours a été validé par l’administration, vous ne pouvez plus modifier vos séances.<br>
      Si vous souhaitez le modifier à nouveau, contactez le bureau.
    </div>

    <div id="infoCoursActif" style="margin:10px 0; font-weight:bold; color:#2563eb;">
      🧾 Cours en saisie : <span id="coursActifTexte">(aucun cours sélectionné)</span>
    </div>

    <table id="tabSeances">
      <thead><tr>
        <th>Date (jj/mm/aaaa)</th><th>Début</th><th>Fin</th><th>Durée (h)</th><th>Actions</th>
      </tr></thead>
      <tbody></tbody>
    </table>

    <button id="btnAdd" class="btn btn-add" onclick="ajouterLigne()">+ Ajouter une séance</button>
    <div id="total">Total : 0 h</div>
    <div id="msg" class="errorMsg"></div>

    <div id="actionsSousTableau">
      <button class="btn" onclick="resetCours()">🔄 Sélectionner un autre cours</button>
      <?php if (!$isAdmin): ?>
        <button type="button" onclick="validerDeclaration()" class="btn btn-add">
          ✅ Passer au récapitulatif
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
const coursAdmin   = <?= $coursAdmin ? json_encode($coursAdmin) : 'null' ?>;
const KEY_TOTALS   = 'audra_totaux_<?= (int)$annee ?>_<?= (int)$mois ?>_<?= addslashes($storageProfKey) ?>';
const IS_ADMIN     = <?= $isAdmin ? 'true' : 'false' ?>;
const defaultCoursFromDB = <?= $defaultCoursFromDB ? (int)$defaultCoursFromDB : 'null' ?>;

// bornes de mois côté client (sécurité complémentaire)
const Y_EDIT = <?= (int)$annee ?>;
const M_EDIT = <?= (int)$mois ?>;

let storedTotals = {};
try { storedTotals = JSON.parse(sessionStorage.getItem(KEY_TOTALS) || '{}') || {}; } catch(e){ storedTotals = {}; }

let lignes = [];
let currentCours = 0;

// --- Helpers totaux mémorisés ---
function _getStoredTotal(idCours){
  if (!idCours) return 0;
  const v = storedTotals[String(idCours)];
  const n = parseFloat(v);
  return isNaN(n) ? 0 : n;
}
function _showTotal(val){
  const h = (typeof val === 'number' ? val : parseFloat(val)) || 0;
  document.getElementById('total').textContent = 'Total : ' + h.toFixed(2).replace('.', ',') + ' h';
}
function _applyStoredTotalForCurrent(){
  const st = _getStoredTotal(currentCours);
  if (st > 0) _showTotal(st); else _showTotal(0);
}
function _persistCurrentComputedTotal(){
  let total = 0;
  lignes.forEach(x => total += x.duree || 0);
  storedTotals[String(currentCours)] = total;
  try { sessionStorage.setItem(KEY_TOTALS, JSON.stringify(storedTotals)); } catch(e){}
}

/* ===== Mini-liste : helpers ===== */
function toggleMiniList(){
  const b = document.getElementById('miniListBody');
  if(!b) return;
  b.style.display = (b.style.display === 'none' ? '' : 'none');
}
function scrollToRowById(id){
  const tr = document.querySelector('tr[data-row-id="'+id+'"]');
  if(tr){ tr.scrollIntoView({behavior:'smooth', block:'center'}); }
}
function focusEditInputs(id){
  setTimeout(()=>{ document.getElementById('d_'+id)?.focus(); }, 60);
}
function editRowFromMiniList(id){
  modifierLigne(id);
  scrollToRowById(id);
  focusEditInputs(id);
}
function renderMiniList(){
  const wrap  = document.getElementById('miniListWrap');
  const list  = document.getElementById('miniList');
  const empty = document.getElementById('miniListEmpty');
  if(!wrap || !list || !empty) return;

  if(!currentCours || !Array.isArray(lignes)){
    wrap.style.display = 'none';
    return;
  }

  function frToIso(d){
    const p = d.split('/');
    return `${p[2]}-${p[1]}-${p[0]}`;
  }

  const data = lignes
    .filter(l =>
      l &&
      l.date &&
      l.debut &&
      l.fin &&
      parseFloat(l.duree || 0) > 0
    )
    .slice()
    .sort((a,b)=>{
      const ad = frToIso(a.date || '01/01/1970') + ' ' + (a.debut || '00:00');
      const bd = frToIso(b.date || '01/01/1970') + ' ' + (b.debut || '00:00');
      return ad.localeCompare(bd);
    });

  wrap.style.display = 'block';
  list.innerHTML = '';

  if(data.length === 0){
    empty.style.display = '';
    return;
  }

  empty.style.display = 'none';

  data.forEach(l=>{
    const li = document.createElement('li');
    const dur = (l.duree || 0).toFixed(2).replace('.', ',');
    li.innerHTML = `${l.date} — ${l.debut} → ${l.fin} &nbsp; <em>(${dur} h)</em>` +
                   (l.confirme_admin
                     ? ' <span title="Validée administration">🔒</span>'
                     : ` <span class="mini-link" onclick="editRowFromMiniList(${l.id})">✏️ modifier</span>`);
    list.appendChild(li);
  });
}

// === CRUD / réseau ===
function _applyServerErrorToLine(l, js) {
  if (js && js.fields) {
    if (js.fields.date  != null) l.date  = js.fields.date;
    if (js.fields.debut != null) l.debut = js.fields.debut;
    if (js.fields.fin   != null) l.fin   = js.fields.fin;
  }
  l.etat = 'edit';
  rafraichirTableau();
  const box = document.getElementById('msg');
  if (box) box.textContent = (js && js.error) ? js.error : 'Erreur enregistrement';
  if (js && js.focus) {
    setTimeout(() => {
      const row = document.querySelector(`[data-row-id="${l.id || 'new'}"]`);
      const el  = row ? row.querySelector(`[name="${js.focus}"]`)
                      : document.querySelector(`[name="${js.focus}"]`);
      if (el) { el.focus(); if (el.select) el.select(); }
    }, 0);
  }
}

// ✅ Base stable : évite les ../../ selon le dossier
const AUDRA_ACTIONS = "/audra_portail_prod/actions";

async function saveSeance(l) {
  if ((!currentCours || currentCours <= 0) && sessionStorage.getItem('cours_selectionne')) {
    currentCours = parseInt(sessionStorage.getItem('cours_selectionne'), 10);
  }
  if (!currentCours || currentCours <= 0) {
    document.getElementById('msg').textContent = "⚠️ Aucun cours actif. Veuillez en sélectionner un avant de saisir.";
    return;
  }
  const fd = new FormData();
  fd.append('id',       l.id > 0 ? l.id : 0);
  fd.append('id_cours', currentCours);
  fd.append('date',     l.date);
  fd.append('debut',    l.debut);
  fd.append('fin',      l.fin);

  try {
    const r  = await fetch(AUDRA_ACTIONS + '/save_saisie.php', {
      method: 'POST',
      body: fd,
      credentials: 'include'
    });

    const js = await r.json();
    if (js && js.success) {
      const msg = document.getElementById('msg');
      if (msg) msg.textContent = '';
      if (l.id <= 0 && js.id) l.id = js.id;
      l.duree = js.duree || l.duree;
      l.etat  = 'lock';

      let total = 0; lignes.forEach(x => total += x.duree || 0);
      _showTotal(total);
      _persistCurrentComputedTotal();

// ✅ Mode admin : créer/maintenir un point "DOCS_ADMIN" (non bloquant) pour réclamer les justificatifs
if (IS_ADMIN) {
  try {
    const fdIssue = new FormData();
    fdIssue.set('prof',  <?= json_encode((string)($_GET['prof'] ?? $PROF)) ?>);
    fdIssue.set('code',  <?= json_encode((string)($_GET['code'] ?? $profId)) ?>);
    fdIssue.set('annee', String(<?= (int)$annee ?>));
    fdIssue.set('mois',  String(<?= (int)$mois ?>));
    fdIssue.set('type',  'DOCS_ADMIN');
    fdIssue.set('message', 'Docs à réclamer suite à ajout/correction admin (présence + facture si TNS).');

    // ✅ Ligne ciblée : on ajoute la séance concernée au point (sans mail énorme)
    const dur = (js && js.duree != null) ? Number(js.duree) : Number(l.duree || 0);
    const durTxt = (isNaN(dur) ? '0,00' : dur.toFixed(2).replace('.', ','));
    const line = `Séance (admin) — Cours ${currentCours} — ${l.date} ${l.debut} — ${durTxt} h`;
    fdIssue.set('append_line', line);

    await fetch('/audra_portail_prod/admin_actions/admin_issue_upsert_docs.php', {
  method: 'POST',
  body: fdIssue,
  credentials: 'include',
  cache: 'no-store'
});
  } catch (e) {
    // silencieux : on ne bloque pas la saisie si le point n'a pas pu être créé
    console.warn('upsert DOCS_ADMIN failed', e);
  }
}

      seancesCache[currentCours] = JSON.parse(JSON.stringify(lignes));
      rafraichirTableau();
    } else {
      _applyServerErrorToLine(l, js || {});
    }
  } catch (err) {
    _applyServerErrorToLine(l, { error:'Erreur réseau/serveur' });
  }
}

async function deleteSeance(id) {
  const fd = new FormData();
  fd.append('id', id);
  const r = await fetch(AUDRA_ACTIONS + '/delete_saisie.php', {
  method: 'POST',
  body: fd,
  credentials: 'include',
  cache: 'no-store'
});

  return r.json();
}

// === tableau ===
function ajouterLigne(){
  const newId = Date.now()*-1;
  lignes.push({id:newId,date:'',debut:'',fin:'',duree:0,etat:'edit',confirme_admin:false});
  rafraichirTableau();
  setTimeout(()=>{ document.getElementById('d_'+newId)?.focus(); },0);
}

async function supprimerLigne(id){
  const l=lignes.find(x=>x.id===id);
  if(l && l.id>0){
    const res = await deleteSeance(id);
    if(res && res.success){
      lignes=lignes.filter(x=>x.id!==id);
      rafraichirTableau();
      document.getElementById('msg').textContent='';
      _persistCurrentComputedTotal();
    } else {
      document.getElementById('msg').textContent = res?.error || 'Suppression impossible';
    }
  } else {
    lignes=lignes.filter(x=>x.id!==id);
    rafraichirTableau();
    _persistCurrentComputedTotal();
  }
}

function validerLigne(id){
  const l = lignes.find(x=>x.id===id);
  if(!l) return;
  const dInput = document.getElementById('d_'+id);
  const bInput = document.getElementById('b_'+id);
  const fInput = document.getElementById('f_'+id);

  let d;
  if (dInput && /^\d{4}-\d{2}-\d{2}$/.test(dInput.value.trim())) {
    const [y,m,day] = dInput.value.split('-').map(Number);
    d = new Date(y, m-1, day);
  } else {
    d = parseDate(dInput.value, dInput);
  }

  const hd = parseHeure(bInput.value);
  const hf = parseHeure(fInput.value);
  if(!d || !hd || !hf){
    document.getElementById('msg').textContent='⚠️ Champs incomplets ou invalides';
    l.etat='edit'; rafraichirTableau(); return;
  }

    if (!IS_ADMIN) {
    const m = d.getMonth() + 1, y = d.getFullYear();
    if (m !== M_EDIT || y !== Y_EDIT) {
      document.getElementById('msg').textContent = '⚠️ Date hors mois autorisé (<?= addslashes($moisTxt) ?> <?= (int)$annee ?>).';
      l.etat='edit'; rafraichirTableau(); return;
    }

    const today = new Date();
    const todayOnly = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const pickedOnly = new Date(d.getFullYear(), d.getMonth(), d.getDate());

    const currentMonth = today.getMonth() + 1;
    const currentYear  = today.getFullYear();

    if (M_EDIT === currentMonth && Y_EDIT === currentYear && pickedOnly > todayOnly) {
      document.getElementById('msg').textContent = '⚠️ Vous ne pouvez pas saisir une date future dans le mois en cours.';
      l.etat='edit'; rafraichirTableau(); return;
    }
  }

  const [h1,m1]=hd.split(':').map(Number), [h2,m2]=hf.split(':').map(Number);
  const t1=h1*60+m1, t2=h2*60+m2;
  if(t2<=t1){
    document.getElementById('msg').textContent='⚠️ Durée invalide (fin ≤ début)';
    l.etat='edit'; rafraichirTableau(); return;
  }
  l.date=formatDateFr(d); l.debut=hd; l.fin=hf; l.duree=(t2-t1)/60; l.etat='lock';
  saveSeance(l);
}

function modifierLigne(id){
  const l = lignes.find(x=>x.id===id);
  if(!l) return;
  l.etat = 'edit'; rafraichirTableau();
  setTimeout(()=>{ document.getElementById('d_'+id)?.focus(); },50);
}

// --- Helpers date/heure ---
function formatDateISO(fr){
  if(!fr) return '';
  const p = fr.split('/');
  if(p.length!==3) return '';
  return `${p[2]}-${p[1].padStart(2,'0')}-${p[0].padStart(2,'0')}`;
}
function parseDate(val, inputEl=null){
  if(!val) { if(inputEl){ inputEl.classList.add('invalid'); } return null; }
  val = val.trim().replace(/[-.]/g,'/').replace(/\s+/g,'');
  let d,m,y;
  if (/^\d{6}$/.test(val)){ d=val.substr(0,2); m=val.substr(2,2); y=val.substr(4,2); }
  else if(/^\d{8}$/.test(val)){ d=val.substr(0,2); m=val.substr(2,2); y=val.substr(4,4); }
  else { const p=val.split('/'); if(p.length<2){ if(inputEl){ inputEl.classList.add('invalid'); } return null; } d=p[0]; m=p[1]; y=p[2]||''; }
  d=parseInt(d,10); m=parseInt(m,10); y=parseInt(y,10);
  if(isNaN(d)||isNaN(m)||isNaN(y)){ if(inputEl){ inputEl.classList.add('invalid'); } return null; }
  if(y<100){ y=(y<=49)?2000+y:1900+y; }
  const dt=new Date(y,m-1,d);
  if(dt.getFullYear()!==y||dt.getMonth()!==m-1||dt.getDate()!==d){ if(inputEl){ inputEl.classList.add('invalid'); } return null; }
  if(inputEl){ inputEl.classList.remove('invalid'); }
  return dt;
}
function formatDateFr(d){ return `${String(d.getDate()).padStart(2,'0')}/${String(d.getMonth()+1).padStart(2,'0')}/${d.getFullYear()}`; }
function parseHeure(val){
  if(!val) return null;
  val=val.trim().toLowerCase().replace('h',':');
  if(/^\d{1,2}$/.test(val)) return val.padStart(2,'0')+":00";
  if(/^\d{3,4}$/.test(val)){ const h=val.length===3?val.substr(0,1):val.substr(0,2); const m=val.substr(-2); return h.padStart(2,'0')+":"+m; }
  if(/^\d{1,2}[:.]\d{2}$/.test(val)) return val.replace('.',':').padStart(5,'0');
  return null;
}

// Affichage
function rafraichirTableau(){
  const tbody=document.querySelector('#tabSeances tbody');
  tbody.innerHTML='';
  let total=0;
  let locked=false;

  lignes.forEach(l=>{
    total+=l.duree||0;
    const tr=document.createElement('tr');
    tr.setAttribute('data-row-id', String(l.id));

    if(l.confirme_admin){
      locked=true;
      tr.innerHTML=`
        <td>${l.date}</td>
        <td>${l.debut}</td>
        <td>${l.fin}</td>
        <td>${(l.duree||0).toFixed(2).replace('.',',')}</td>
        <td>🔒</td>`;
    } else {
      if(l.etat==='edit'){
        tr.innerHTML=`
  <td>
    <input id="d_${l.id}" name="date" type="date"
           min="<?= $annee ?>-<?= sprintf('%02d', $mois) ?>-01"
           max="<?= $annee ?>-<?= sprintf('%02d', $mois) ?>-<?= $lastDayInMonth ?>"
           value="${formatDateISO(l.date)||''}" placeholder="jj/mm/aaaa" />
  </td>
  <td><input id="b_${l.id}" name="debut" type="text" value="${l.debut||''}" placeholder="10:00" /></td>
  <td><input id="f_${l.id}" name="fin"   type="text" value="${l.fin||''}"   placeholder="11:30" /></td>
  <td>${(l.duree||0).toFixed(2).replace('.',',')}</td>
  <td>
    <button class="btn btn-ok"  onclick="validerLigne(${l.id})">✅</button>
    <button class="btn btn-del" onclick="supprimerLigne(${l.id})">🗑</button>
  </td>`;

      } else {
        tr.innerHTML=`
          <td>${l.date}</td>
          <td>${l.debut}</td>
          <td>${l.fin}</td>
          <td>${(l.duree||0).toFixed(2).replace('.',',')}</td>
          <td>
            <button class="btn btn-edit" onclick="modifierLigne(${l.id})">✏️</button>
            <button class="btn btn-del"  onclick="supprimerLigne(${l.id})">🗑</button>
          </td>`;
      }
    }
    tbody.appendChild(tr);
  });

  if (total > 0) _showTotal(total); else _showTotal(_getStoredTotal(currentCours));

  if(locked){
    document.getElementById('msgLock').style.display='block';
    document.getElementById('btnAdd').style.display='none';
  } else {
    document.getElementById('msgLock').style.display='none';
    document.getElementById('btnAdd').style.display='inline-block';
  }

  renderMiniList();
}

// === boutons globaux ===
function resetCours() {
  document.getElementById('cours').value = '';
  document.getElementById('coursZone').style.display = 'none';
  sessionStorage.removeItem('cours_selectionne');
}

async function validerDeclaration() {
  const enEdition = lignes.some(l => l.etat === 'edit');
  if (enEdition) {
    const conf = confirm("Certaines séances ne sont pas encore validées. Voulez-vous les enregistrer avant de continuer ?");
    if (!conf) return;
    for (const l of lignes.filter(l => l.etat === 'edit')) {
      await saveSeance(l);
    }
  }
  window.location.href = "form_prof_meta.php";
}

// === chargement cours ===
async function chargerCours() {
  console.log("chargerCours() appelé");

  const sel = document.getElementById("cours");
  if (!sel) {
    console.error('Impossible de trouver le <select id=\"cours\">');
    return;
  }

  sel.innerHTML = '<option value=\"\">— Sélectionnez un cours —</option>';

  try {
    const url = "/audra_portail_prod/modules/select_cours.php?annee=<?= (int)$annee ?>&mois=<?= (int)$mois ?><?= $isAdmin ? '&prof=' . urlencode($PROF) : '' ?>";

const res = await fetch(url, {
  cache: "no-store",
  credentials: "include"
});

    console.log("Status select_cours:", res.status, res.url);

    if (!res.ok) {
      console.error("Réponse HTTP non OK pour select_cours.php");
      const msgBox = document.getElementById("msg");
      if (msgBox) {
        msgBox.textContent = "⚠️ Impossible de charger la liste des cours (code " + res.status + ").";
      }
      return;
    }

    const js = await res.json();
    console.log("Réponse select_cours:", js);

    const msgBox = document.getElementById("msg");

    if (!js || js.success !== true || !Array.isArray(js.cours)) {
      if (msgBox) {
        msgBox.textContent = "⚠️ La liste des cours n’a pas pu être chargée.";
      }
      return;
    }

    const coursTries = js.cours.slice().sort((a, b) => {
      const A = Number(a.id_cours), B = Number(b.id_cours);
      return (isNaN(A) || isNaN(B))
        ? String(b.id_cours).localeCompare(String(a.id_cours), "fr", { numeric: true })
        : B - A;
    });

    coursTries.forEach(c => {
      const opt = document.createElement("option");
      opt.value = c.id_cours;
      opt.textContent = c.id_cours + " — " + (c.intitule || "");
      sel.appendChild(opt);
    });

    const stock  = sessionStorage.getItem('cours_selectionne');
    const initId = (coursAdmin || (stock ? parseInt(stock, 10) :
                     (defaultCoursFromDB !== null ? defaultCoursFromDB : null)));

    if (initId && sel.querySelector(`option[value="${initId}"]`)) {
      sel.value      = String(initId);
      currentCours   = initId;
      document.getElementById('coursZone').style.display = 'block';
      document.getElementById('titreCours').textContent  = 'Planning du cours ' + initId;
      const infoCours = document.getElementById('coursActifTexte');
      if (infoCours) {
        const opt = sel.querySelector(`option[value="${initId}"]`);
        infoCours.textContent = opt ? opt.textContent : ('Cours ' + initId);
      }

      _applyStoredTotalForCurrent();
      await chargerSeances(currentCours);
    } else {
      if (msgBox) msgBox.textContent = "";
    }

  } catch (err) {
    console.error("Erreur chargement cours:", err);
    const msgBox = document.getElementById("msg");
    if (msgBox) {
      msgBox.textContent = "⚠️ Erreur lors du chargement des cours (réseau/serveur).";
    }
  }
}

// === chargement séances ===
const seancesCache = {};

async function chargerSeances(idCours) {
  if (!idCours || idCours <= 0) {
    lignes = [];
    rafraichirTableau();
    return;
  }

  if (seancesCache[idCours]) {
    lignes = seancesCache[idCours].map(s => ({
      id: s.id,
      date: s.date || '',
      debut: s.debut || '',
      fin: s.fin || '',
      duree: parseFloat(s.duree) || 0,
      confirme_admin: s.confirme_admin == 1,
      etat: 'lock'
    }));
    rafraichirTableau();
    return;
  }

  try {
    const r = await fetch(
      AUDRA_ACTIONS + '/list_saisies.php?id_cours=' + encodeURIComponent(idCours),
      { cache: 'no-store', credentials: 'include' }
    );
    const js = await r.json();

    if (js && js.success && Array.isArray(js.seances)) {
      seancesCache[idCours] = js.seances;
      lignes = js.seances.map(s => ({
        id: s.id,
        date: s.date || '',
        debut: s.debut || '',
        fin: s.fin || '',
        duree: parseFloat(s.duree) || 0,
        confirme_admin: s.confirme_admin == 1,
        etat: 'lock'
      }));
    } else {
      lignes = [];
    }
  } catch (err) {
    console.error('chargerSeances : erreur réseau', err);
    lignes = seancesCache[idCours]
      ? seancesCache[idCours].map(s => ({
          id: s.id,
          date: s.date,
          debut: s.debut,
          fin: s.fin,
          duree: parseFloat(s.duree) || 0,
          confirme_admin: s.confirme_admin == 1,
          etat: 'lock'
        }))
      : [];
  }

  rafraichirTableau();
}

// === choix cours ===
document.getElementById('cours').addEventListener('change', async function(){
  const val = this.value;
  const txt = this.options[this.selectedIndex]?.text || "(aucun cours sélectionné)";
  const infoCours = document.getElementById('coursActifTexte');

  if (!val) {
    document.getElementById('coursZone').style.display = 'none';
    if (infoCours) infoCours.textContent = '(aucun cours sélectionné)';
    sessionStorage.removeItem('cours_selectionne');
    return;
  }

  currentCours = +val;
  sessionStorage.setItem('cours_selectionne', String(currentCours));

  document.getElementById('coursZone').style.display = 'block';
  document.getElementById('titreCours').textContent = 'Planning du cours ' + val;
  if (infoCours) infoCours.textContent = txt;

  _applyStoredTotalForCurrent();

  if (seancesCache[currentCours]) {
    lignes = seancesCache[currentCours].map(s => ({
      id: s.id,
      date: s.date || '',
      debut: s.debut || '',
      fin: s.fin || '',
      duree: parseFloat(s.duree) || 0,
      confirme_admin: s.confirme_admin == 1,
      etat: 'lock'
    }));
    rafraichirTableau();
  } else {
    lignes = [];
    rafraichirTableau();
  }

  await chargerSeances(currentCours);
});

// === initialisation au chargement ===
document.addEventListener('DOMContentLoaded', () => {
  console.log('DOMContentLoaded -> appel chargerCours()');
  chargerCours();
});
</script>

<?php if ($isAdmin): ?>
<div style="margin:20px; text-align:center;">
  <a href="../../admin_controle_saisie.php?prof=<?= urlencode($retourProf) ?>&code=<?= urlencode($retourCode) ?>&annee=<?= $retourAnnee ?>&mois=<?= $retourMois ?>"
     class="nav-btn">⬅ Retour contrôle</a>

  <a href="form_prof_meta.php?prof=<?= urlencode($retourProf) ?>&code=<?= urlencode($retourCode) ?>&annee=<?= $retourAnnee ?>&mois=<?= $retourMois ?>"
     class="nav-btn">📋 Voir infos & kilomètres</a>
</div>
<?php endif; ?>

</body>
</html>


