<?php
// form_prof_meta.php — Informations complémentaires professeur

declare(strict_types=1);

// -----------------------------------------------------------------------------
// Bootstrap portail prof : session + config + libs communes
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page();
if (($config['env'] ?? 'DEV') === 'DEV') {
    echo "\n<!-- META_FILE_EXEC=" . htmlspecialchars(__FILE__, ENT_QUOTES, 'UTF-8') . " -->\n";
}

// Si pas de prof en session → retour portail
// -----------------------------------------------------------------------------
// FIX ADMIN : toujours prendre le prof/mois/année depuis l'URL (évite "prof collé")
// -----------------------------------------------------------------------------
if (!empty($_SESSION['admin']) && $_SESSION['admin']) {

    $gCode  = strtoupper(trim((string)($_GET['code'] ?? $_GET['prof_id'] ?? $_GET['prof_code'] ?? '')));
    $gAnnee = (int)($_GET['annee'] ?? 0);
    $gMois  = (int)($_GET['mois']  ?? 0);
    $gProf  = trim((string)($_GET['prof'] ?? ''));

    if ($gCode !== '') {
        $_SESSION['prof_code'] = $gCode;
        $_SESSION['display']   = $gCode; // clé : force le prof affiché
    }
    if ($gAnnee > 0) $_SESSION['annee'] = $gAnnee;
    if ($gMois  > 0) $_SESSION['mois']  = $gMois;

    // (optionnel mais sympa) : pour l'affichage prénom/nom si l'URL le fournit
    if ($gProf !== '') {
        $parts = preg_split('/\s+/', $gProf, 2);
        $_SESSION['firstname'] = $parts[0] ?? '';
        $_SESSION['lastname']  = $parts[1] ?? '';
    }
}

if (empty($_SESSION['display'])) {
    header('Location: portail_prof.php');
    exit;
}

// 🔹 Initialisation sécurisée des variables principales
$prof  = $_SESSION['display'] ?? '';
$annee = $_SESSION['annee'] ?? date('Y');
$mois  = $_SESSION['mois']  ?? date('n');

// -----------------------------------------------------------------------------
// Connexion SQL + garde
// -----------------------------------------------------------------------------
require_once $config['base_path'] . '/app/config/db_config.php';
$conn = db();
if (!$conn) {
    die('❌ Connexion SQL impossible');
}

// Garde centrale (bloqué / correction / portail)
audra_guard_prof_page($conn); // redirige selon l’état

// 🔑 Mode admin
$isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'];

// 🔎 Mode Correction (prof avec mois débloqué par l’admin)
$modeCorrection = !empty($_SESSION['mode_correction']) && $_SESSION['mode_correction'];

// 🔒 GARDE SIMPLE PORTAIL global (legacy) — on conserve la logique
$tz    = new DateTimeZone('Europe/Paris');
$today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');

$isOpen = false;
$sql = "SELECT TOP 1 ouverture, fermeture, etat
        FROM dbo.AudraWeb_Regles_Periodiques
        ORDER BY id DESC";
$stmt = sqlsrv_query($conn, $sql);
$rule = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;
if ($stmt) {
    sqlsrv_free_stmt($stmt);
}

if ($rule && isset($rule['etat']) && strtoupper(trim((string)$rule['etat'])) === 'OUVERT') {
    $open  = ($rule['ouverture'] instanceof DateTime) ? $rule['ouverture']->format('Y-m-d') : null;
    $close = ($rule['fermeture'] instanceof DateTime) ? $rule['fermeture']->format('Y-m-d') : null;
    if ($open && $close && $today >= $open && $today <= $close) {
        $isOpen = true;
    }
}

if (!$isAdmin && !$modeCorrection && !$isOpen) {
    header('Location: saisie_fermee.php');
    exit;
}

$PROF   = strtoupper(trim($_SESSION['display']));
$prenom = $_SESSION['firstname'] ?? '';
$nom    = $_SESSION['lastname'] ?? '';

// 🔧 Utilisation différente selon contexte
if ($isAdmin) {
    $annee = isset($_GET['annee']) ? (int)$_GET['annee'] : (int)date('Y');
    $mois  = isset($_GET['mois'])  ? (int)$_GET['mois']  : (int)date('n');
} else {
    $annee = (int)($_SESSION['annee'] ?? date('Y'));
    $mois  = (int)($_SESSION['mois']  ?? date('n'));
    if (empty($annee) || empty($mois)) {
        header("Location: saisie_fermee.php");
        exit;
    }
}

// 🔑 Id prof EBP (prend GET ?code= si présent, sinon session)
$profId = '';
if (!empty($_GET['code'])) {
    $profId = strtoupper(trim((string)$_GET['code']));
} elseif (!empty($_SESSION['prof_code'])) {
    $profId = strtoupper(trim((string)$_SESSION['prof_code']));
}

// Pour l’affichage
$moisNoms = [
    "Janvier","Février","Mars","Avril","Mai","Juin",
    "Juillet","Août","Septembre","Octobre","Novembre","Décembre"
];
$moisTxt = $moisNoms[$mois-1] ?? (string)$mois;

// 🔑 Mode admin : accès direct à un cours
$coursAdmin = null;
if ($isAdmin && !empty($_GET['cours'])) {
    $coursAdmin = (int)$_GET['cours'];
}

// ✅ Charger les valeurs déjà enregistrées
$metaRow = null;

if (!empty($profId)) {
    // Lecture par identifiant unique EBP
    $sqlMeta = "SELECT TOP 1
                    km,
                    nb_seances,
                    remontee,
                    infos,
                    COALESCE([suggestion],[suggest]) AS suggestion
                FROM dbo.AudraWeb_Saisie_Meta_Web
                WHERE id_prof = ? AND annee = ? AND mois = ?
                ORDER BY horodatage DESC";
    $stMeta  = sqlsrv_query($conn, $sqlMeta, [$profId, $annee, $mois]);
} else {
    // Fallback legacy par nom formateur
    $sqlMeta = "SELECT TOP 1
                    km,
                    nb_seances,
                    remontee,
                    infos,
                    COALESCE([suggestion],[suggest]) AS suggestion
                FROM dbo.AudraWeb_Saisie_Meta_Web
                WHERE nom_formateur = ? AND annee = ? AND mois = ?
                ORDER BY horodatage DESC";
    $stMeta  = sqlsrv_query($conn, $sqlMeta, [$prof, $annee, $mois]);
}

if ($stMeta) {
    $metaRow = sqlsrv_fetch_array($stMeta, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stMeta);
}

$meta = [
    'km'         => (float)($metaRow['km'] ?? 0),
    'nb_seances' => (int)($metaRow['nb_seances'] ?? 0),
    'remontee'   => (string)($metaRow['remontee'] ?? ''),
    'infos'      => (string)($metaRow['infos'] ?? ''),
    'suggest'    => (string)($metaRow['suggestion'] ?? '')
];
?>
<!doctype html>

<html lang="fr">
<head>
<meta charset="utf-8">
<title>Informations supplémentaires</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{font-family:sans-serif;margin:0;background:#f9fafb;color:#111}
  .container{padding:20px}
  h1{font-size:20px;margin-bottom:12px}
  .panel{background:#fff;border:1px solid #ddd;border-radius:8px;padding:15px;margin-bottom:20px}
  label{font-weight:600;display:block;margin-bottom:6px}
  textarea, input[type=number]{width:100%;padding:8px;border:1px solid:#ccc;border-radius:6px}
  textarea{min-height:120px}
  .row{display:flex;gap:15px}
  .col{flex:1}
  .actions{display:flex;justify-content:space-between;margin-top:20px;flex-wrap:wrap;gap:10px}
  .btn{padding:10px 18px;border:none;border-radius:6px;cursor:pointer;font-size:15px}
  .btn-back{background:#e5e7eb}
  .btn-next{background:#1d4ed8;color:#fff}
  .btn-danger{background:#dc2626;color:#fff}
  .banner{padding:10px 15px;border-radius:6px;margin-bottom:20px;font-weight:600}
  .prof-mode{background:#d1fae5;color:#065f46;}
  .admin-mode{background:#fde68a;color:#92400e;}
  .correction-mode{background:#fef3c7;color:#92400e;}
  .nav-btn{display:inline-block;padding:8px 14px;border-radius:6px;background:#6b7280;color:#fff;text-decoration:none;cursor:pointer;}
  .nav-btn:hover{background:#374151;}
</style>
</head>
<body>

<!-- ✅ Bandeau supérieur -->
<div class="banner <?= $isAdmin ? 'admin-mode' : ($modeCorrection ? 'correction-mode' : 'prof-mode') ?>">
  <?php if ($isAdmin): ?>
    🔑 Mode Contrôle Admin — Espace de <?= htmlspecialchars($prof) ?> — <?= $moisTxt ?> <?= $annee ?>
  <?php elseif ($modeCorrection): ?>
    ✏️ Mode Correction — <?= htmlspecialchars($prof) ?> — <?= $moisTxt ?> <?= $annee ?>
  <?php else: ?>
    👤 Mode Professeur — Espace de <?= htmlspecialchars($prof) ?> — <?= $moisTxt ?> <?= $annee ?>
  <?php endif; ?>
</div>

<!-- ✅ FORM META : ouverture du formulaire -->
<div class="container">
<form id="metaForm" method="post" action="../../actions/save_meta.php">
  <input type="hidden" name="prof_code" value="<?= htmlspecialchars($_GET['code'] ?? ($_SESSION['prof_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="id_prof"  value="<?= htmlspecialchars($_GET['code'] ?? ($_SESSION['prof_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="annee"    value="<?= (int)$annee ?>">
  <input type="hidden" name="mois"     value="<?= (int)$mois ?>">

<h1>ÉCRAN D’INFORMATIONS SUPPLÉMENTAIRES</h1>
<p style="margin:0 0 14px 0; font-size:16px; color:#1d4ed8; font-weight:bold;">Informations supplémentaires</p>

  <?php if ($isAdmin): ?>
  <div class="panel">
    <h2>Déplacements et kilomètres</h2>

    <label for="nb_seances">Nombre de séances avec déplacements</label>
    <input type="number" id="nb_seances" name="nb_seances" data-meta-auto="1" min="0"
           value="<?= (int)($meta['nb_seances'] ?? 0) ?>">

    <label for="km">Nombre total de kilomètres</label>
    <input type="number" id="km" name="km" data-meta-auto="1" min="0" step="0.1"
           value="<?= htmlspecialchars((string)($meta['km'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
  </div>
<?php endif; ?>

  <div class="panel">
  <h2>Remontées pédagogiques par cours (QUALIOPI)</h2>

  <p style="color:#6b7280; margin-top:6px;">
    Sélectionnez un cours, puis saisissez une remontée et/ou un problème. (Ces informations seront utilisées dans l’audit QUALIOPI par cours.)
  </p>

  <!-- Bloc sélection cours + saisie -->
  <div style="margin-top:10px;">
    <div>
      <label for="rp_cours"><b>Choisir un cours du mois</b></label>
      <select id="rp_cours" onchange="window.audraMetaLoadForCours && window.audraMetaLoadForCours();" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:6px;">
  <option value="">— Sélectionnez un cours —</option>
  <?php
  // ✅ Liste des cours du mois depuis la base (AudraWeb_Saisie_Heures_Web → date_seance)
  $coursIds = [];

  if (!empty($profId)) {
    $stC = sqlsrv_query($conn, "
      SELECT DISTINCT id_cours
      FROM dbo.AudraWeb_Saisie_Heures_Web
      WHERE id_prof = ?
        AND YEAR(date_seance) = ?
        AND MONTH(date_seance) = ?
        AND ISNULL(annule_admin,0) = 0
      ORDER BY id_cours
    ", [$profId, (int)$annee, (int)$mois]);
  } else {
    $stC = sqlsrv_query($conn, "
      SELECT DISTINCT id_cours
      FROM dbo.AudraWeb_Saisie_Heures_Web
      WHERE UPPER(LTRIM(RTRIM(nom_formateur))) = UPPER(?)
        AND YEAR(date_seance) = ?
        AND MONTH(date_seance) = ?
        AND ISNULL(annule_admin,0) = 0
      ORDER BY id_cours
    ", [$prof, (int)$annee, (int)$mois]);
  }

  if ($stC) {
    while ($r = sqlsrv_fetch_array($stC, SQLSRV_FETCH_ASSOC)) {
      $cid = (int)($r['id_cours'] ?? 0);
      if ($cid > 0) $coursIds[] = $cid;
    }
    sqlsrv_free_stmt($stC);
  }

  $coursIds = array_values(array_unique($coursIds));
  sort($coursIds, SORT_NUMERIC);

  foreach ($coursIds as $cid) {
    echo '<option value="'.(int)$cid.'">'.(int)$cid.'</option>';
  }
  ?>
</select>

<div style="margin-top:10px; font-size:12px; color:#6b7280;">
  Astuce : si vous avez plusieurs remarques sur un même cours, ajoutez-les dans la zone du cours correspondant.
</div>

</div>

<div id="cours_fields_wrap" style="display:none; margin-top:12px;">
 
  <div>
    <label for="rp_remontee"><b>1. Remontée pédagogique</b></label>
    <textarea id="rp_remontee" placeholder="Notes pédagogiques, progrès, alertes…"
      style="width:100%; min-height:110px; max-height:110px; overflow:auto; padding:8px; border:1px solid #ccc; border-radius:6px;"></textarea>
    <div style="font-size:12px; color:#6b7280; margin-top:6px;">
      Zone fixe (scroll interne) : la page ne se déforme pas.
    </div>
  </div>

  <div style="margin-top:12px;">
    <label for="rp_infos"><b>2. Infos / Problème (cours ou élève)</b></label>
    <textarea id="rp_infos" placeholder="Absences, retards, problème, contact…"
      style="width:100%; min-height:110px; max-height:110px; overflow:auto; padding:8px; border:1px solid #ccc; border-radius:6px;"></textarea>
    <div style="font-size:12px; color:#6b7280; margin-top:6px;">
      Zone fixe (scroll interne) : la page ne se déforme pas.
    </div>
  </div>
</div>

  <!-- Bouton (sera activé à l’étape 2 avec endpoint) -->
  <div style="margin-top:12px;">
    <button type="button" class="btn btn-next" disabled title="Étape suivante : enregistrement par cours">
      💾 Enregistrer vos informations pour le cours sélectionné puis selectionnez choisissez un autre cours
    </button>
    <span style="margin-left:10px; color:#6b7280; font-size:12px;">
    </span>
  </div>

  <!-- Historique des cours déjà renseignés -->
  <div style="margin-top:16px;">
    <h3 style="margin:0 0 8px 0; font-size:15px;">Cours déjà renseignés (ce mois)</h3>

    <?php
    // Lecture des remarques existantes (ce mois)
    $rpRows = [];
    $rpProf = ($profId !== '' ? $profId : ($idProfFix ?? ''));
    if ($rpProf !== '') {
      $stRp = sqlsrv_query($conn, "
  SELECT
      rp.id_cours,
      rp.remontee,
      rp.infos,
      COALESCE(abs.heures_absence, 0) AS heures_absence,
      rp.updated_at
  FROM dbo.AudraWeb_Remarques_ParCours rp
  LEFT JOIN (
      SELECT
          s.id_cours,
          SUM(CAST(s.duree AS float)) AS heures_absence
      FROM dbo.AudraWeb_Saisie_Heures_Web s
      LEFT JOIN dbo.AudraWeb_Seance_Extras x
          ON x.id_seance = s.id
      WHERE s.id_prof = ?
        AND YEAR(s.date_seance) = ?
        AND MONTH(s.date_seance) = ?
        AND ISNULL(s.annule_admin, 0) = 0
        AND ISNULL(x.eleve_absent, 0) = 1
      GROUP BY s.id_cours
  ) abs
      ON abs.id_cours = rp.id_cours
  WHERE rp.id_prof = ? AND rp.annee = ? AND rp.mois = ?
  ORDER BY rp.id_cours
", [$rpProf, (int)$annee, (int)$mois, $rpProf, (int)$annee, (int)$mois]);

      if ($stRp) {
        while ($rr = sqlsrv_fetch_array($stRp, SQLSRV_FETCH_ASSOC)) {
          $rpRows[] = $rr;
        }
        sqlsrv_free_stmt($stRp);
      }
    }
    ?>
	
	

    <?php if (empty($rpRows)): ?>
      <div style="color:#6b7280; font-style:italic;">Aucune remontée enregistrée pour le moment.</div>
    <?php else: ?>
      <div style="max-height:160px; overflow:auto; border:1px solid #e5e7eb; border-radius:6px;">
        <table style="width:100%; border-collapse:collapse;">
          <thead>
           <tr style="background:#f9fafb;">
  <th style="border:1px solid #e5e7eb; padding:6px 8px; text-align:left;">Cours</th>
  <th style="border:1px solid #e5e7eb; padding:6px 8px; text-align:left;">Remontée</th>
  <th style="border:1px solid #e5e7eb; padding:6px 8px; text-align:left;">Problème</th>
  <th style="border:1px solid #e5e7eb; padding:6px 8px; text-align:left;">Absences élève</th>
  <th style="border:1px solid #e5e7eb; padding:6px 8px; text-align:left;">Maj</th>
</tr>
          </thead>
          <tbody>
          <?php foreach ($rpRows as $rr): ?>
            <?php
  $cid = (int)($rr['id_cours'] ?? 0);
  $rem = (string)($rr['remontee'] ?? '');
  $inf = (string)($rr['infos'] ?? '');
  $abs = (string)($rr['heures_absence'] ?? '0');
  $upd = $rr['updated_at'];
  $updTxt = ($upd instanceof DateTimeInterface) ? $upd->format('d/m/Y H:i') : '';
?>
<tr>
  <td style="border:1px solid #e5e7eb; padding:6px 8px;"><?= $cid ?></td>
  <td style="border:1px solid #e5e7eb; padding:6px 8px;"><?= htmlspecialchars(mb_strimwidth($rem, 0, 120, '…'), ENT_QUOTES, 'UTF-8') ?></td>
  <td style="border:1px solid #e5e7eb; padding:6px 8px;"><?= htmlspecialchars(mb_strimwidth($inf, 0, 120, '…'), ENT_QUOTES, 'UTF-8') ?></td>
  <td style="border:1px solid #e5e7eb; padding:6px 8px;"><?= htmlspecialchars($abs, ENT_QUOTES, 'UTF-8') ?></td>
  <td style="border:1px solid #e5e7eb; padding:6px 8px;"><?= htmlspecialchars($updTxt, ENT_QUOTES, 'UTF-8') ?></td>
</tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Suggestions en bas -->
  <div style="margin-top:18px;">
    <label for="suggest"><b>Suggestions générales</b></label>
    <textarea id="suggest" name="suggest" data-meta-auto="1"
      placeholder="Idées, demandes de matériel, organisation…"
      style="width:100%; min-height:90px; max-height:90px; overflow:auto; padding:8px; border:1px solid #ccc; border-radius:6px;"><?= htmlspecialchars($meta['suggest'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
  </div>
</div>

  <!-- ✅ Bloc Actions -->
  <div class="actions">
    <a href="form_prof_seances.php?prof=<?= urlencode($prof) ?>&code=<?= urlencode($_SESSION['prof_code'] ?? '') ?>&annee=<?= $annee ?>&mois=<?= $mois ?>"
       class="btn btn-back">← Retour (séances)</a>

    <?php if (!$isAdmin): ?>
      <button type="submit" class="btn btn-next">Suivant : Récapitulatif et Uploads →</button>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
      <a href="../../admin_controle_saisie.php?prof=<?= urlencode($prof) ?>&code=<?= urlencode($profId ?: ($_GET['code'] ?? '')) ?>&annee=<?= (int)$annee ?>&mois=<?= (int)$mois ?>" 
   class="nav-btn">⬅ Retour contrôle</a>
      <button type="submit" class="btn btn-next">💾 Enregistrer (Admin)</button>  
	  <a href="form_prof_recap.php?admin=1&prof=<?= urlencode($PROF) ?>&code=<?= urlencode($_SESSION['prof_code'] ?? '') ?>&annee=<?= (int)$annee ?>&mois=<?= (int)$mois ?>"
   class="nav-btn">➡ Passez à la vérification des heures et des KM </a>

      <button type="button" id="btnDeleteMeta" class="btn btn-danger" title="Supprimer la meta (km & infos) du mois">
        🗑 Supprimer la meta du mois
      </button>
    <?php endif; ?>
  </div>

</form>

<?php if ($isAdmin): ?>
<form id="deleteMetaForm" method="post" action="/audra_portail_prod/actions/delete_meta.php" style="display:none;">
  <input type="hidden" name="annee" value="<?= htmlspecialchars((string)$annee, ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="mois"  value="<?= htmlspecialchars((string)$mois,  ENT_QUOTES, 'UTF-8') ?>">
</form>
<?php endif; ?>

</div>

<?php if ($isAdmin): ?>
<script>
document.getElementById('btnDeleteMeta')?.addEventListener('click', async function(){
  if (!confirm("Confirmez-vous la suppression de la meta (km & informations) pour <?= addslashes($moisTxt.' '.$annee) ?> ?")) return;

  try {
    const fd = new FormData();
    fd.append('annee','<?= (int)$annee ?>');
    fd.append('mois','<?= (int)$mois ?>');

    const r = await fetch('/audra_portail_prod/actions/delete_meta.php', {
      method:'POST',
      body: fd,
      credentials:'include',
      headers: {'X-Requested-With':'XMLHttpRequest'}
    });
    const js = await r.json();

    if (js && js.success) {
      alert("Meta supprimée.");
      location.reload();
    } else {
      alert(js?.error || "Suppression impossible.");
    }
  } catch(e) {
    document.getElementById('deleteMetaForm')?.submit();
  }
});
</script>
<?php endif; ?>

<?php if ($isAdmin): ?>
<script>
(function(){
  const form = document.getElementById('metaForm');
  if (!form) return;

  const CONTROL_URL = <?= json_encode(
  "../../admin_controle_saisie.php?prof=" . urlencode($prof)
  . "&code=" . urlencode($profId ?: ($_GET['code'] ?? ''))
  . "&annee=" . (int)$annee
  . "&mois="  . (int)$mois
) ?>;

// ✅ Admin : crée/maintient un point "DOCS_ADMIN" (non bloquant) quand l'admin modifie la déclaration
async function upsertDocsAdminPoint(message){
  try{
    const fdIssue = new FormData();
    fdIssue.set('prof',  <?= json_encode((string)$prof) ?>);
    fdIssue.set('code',  <?= json_encode((string)($profId ?: ($_GET['code'] ?? ''))) ?>);
    fdIssue.set('annee', String(<?= (int)$annee ?>));
    fdIssue.set('mois',  String(<?= (int)$mois ?>));
    fdIssue.set('type',  'DOCS_ADMIN');
    fdIssue.set('message', message);

    await fetch('../../admin_actions/admin_issue_upsert_docs.php', {
      method: 'POST',
      body: fdIssue,
      credentials: 'include',
      cache: 'no-store'
    });
  }catch(e){
    console.warn('upsert DOCS_ADMIN failed (meta)', e);
  }
}


  form.addEventListener('submit', async function(ev){
  ev.preventDefault();

  const fd = new FormData(form);

  // ✅ Compat : on envoie aussi les anciens noms (comme côté prof)
  fd.set('nb_deplacements', fd.get('nb_seances') || '0');
  fd.set('km_total',        fd.get('km')        || '0');
  fd.set('suggestion',      fd.get('suggest')   || '');

  try{
    const r = await fetch(form.action, {
      method:'POST',
      body: fd,
      credentials:'include',
      headers: {'X-Requested-With':'XMLHttpRequest'}
    });

    let js = null;
    try { js = await r.clone().json(); } catch(_){}

    // ✅ Cas standard : le endpoint renvoie du JSON
    if (js) {
      if (js.success) {
        await upsertDocsAdminPoint('Docs à réclamer suite à modification admin (présence + facture si TNS).');
        alert("Enregistré ✅");
        window.location.href = CONTROL_URL;
      } else {
        alert(js.error || "Enregistrement impossible. Merci de réessayer.");
      }
      return;
    }

    // ✅ Fallback legacy : pas de JSON mais HTTP 200 OK
    if (r.ok) {
      await upsertDocsAdminPoint('Docs à réclamer suite à modification admin (présence + facture si TNS).');
      alert("Enregistré ✅");
      window.location.href = CONTROL_URL;
    } else {
      alert("Enregistrement impossible. Merci de réessayer.");
    }

  } catch(e){
    // Fallback si fetch échoue : submit classique
    HTMLFormElement.prototype.submit.call(form);
  }
});
})();
</script>
<?php endif; ?>



<?php if (!$isAdmin): ?>
<script>
(function(){
  const form = document.getElementById('metaForm');
  if (!form) return;

  const recapURL = "form_prof_recap.php?prof=<?= urlencode($prof) ?>&code=<?= urlencode($_SESSION['prof_code'] ?? '') ?>&annee=<?= (int)$annee ?>&mois=<?= (int)$mois ?>";

  form.addEventListener('submit', async function(ev){
    ev.preventDefault();
	
    const fd = new FormData(form);

    // ✅ Compat : on envoie aussi les anciens noms au cas où save_meta.php les attend encore
    fd.set('nb_deplacements', fd.get('nb_seances') || '0');
    fd.set('km_total',        fd.get('km')        || '0');
    fd.set('suggestion',      fd.get('suggest')   || '');

    try{
      const r = await fetch(form.action, {
        method:'POST',
        body: fd,
        credentials:'include',
        headers: {'X-Requested-With':'XMLHttpRequest'}
      });
      let ok = r.ok;
      let js = null;
      try { js = await r.clone().json(); } catch(_){}
      if (js) {
  if (js.success) {
    window.location.href = recapURL;
  } else {
    alert(js.error || "Enregistrement impossible. Merci de réessayer.");
  }
  return;
}

// fallback legacy : si pas de JSON mais 200 OK
if (ok) {
  window.location.href = recapURL;
} else {
  alert("Enregistrement impossible. Merci de réessayer.");
}

    }catch(e){
      HTMLFormElement.prototype.submit.call(form);
    }
  });
})();
</script>
<?php endif; ?>

<script>
// Clé de stockage unique : année + mois + prof (code ou nom)
const META_KEY = 'audra_meta_<?=(int)$annee?>_<?=(int)$mois?>_<?=addslashes(
    isset($_SESSION['prof_code']) && $_SESSION['prof_code'] !== ''
        ? strtoupper(trim((string)$_SESSION['prof_code']))
        : strtoupper(trim((string)($_SESSION['display'] ?? 'PROF_INCONNU')))
)?>';

function loadMetaFromStorage() {
    let data = {};
    try {
        const raw = sessionStorage.getItem(META_KEY);
        if (raw) {
            data = JSON.parse(raw) || {};
        }
    } catch (e) {
        data = {};
    }

    // 🔁 Migration (anciens noms → nouveaux noms)
    if (data.nb_deplacements !== undefined && data.nb_seances === undefined) data.nb_seances = data.nb_deplacements;
    if (data.km_total !== undefined && data.km === undefined) data.km = data.km_total;

    Object.keys(data).forEach(name => {
        const el = document.querySelector('[data-meta-auto="1"][name="'+name+'"]');
        if (el) {
            el.value = data[name];
        }
    });
}

function saveMetaToStorage() {
    const data = {};
    document.querySelectorAll('[data-meta-auto="1"]').forEach(el => {
        if (el.name) {
            data[el.name] = el.value;
        }
    });
    try {
        sessionStorage.setItem(META_KEY, JSON.stringify(data));
    } catch (e) {
        // si sessionStorage est plein, on ignore
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // 1) On recharge les valeurs existantes
    loadMetaFromStorage();

    // 2) À chaque saisie, on sauvegarde
    document.querySelectorAll('[data-meta-auto="1"]').forEach(el => {
        el.addEventListener('input', saveMetaToStorage);
        el.addEventListener('change', saveMetaToStorage);
    });
});
</script>

<script>
(function(){
const API_SAVE = "/audra_portail_prod/actions/save_remarque_cours.php";
const API_GET  = "/audra_portail_prod/actions/get_remarque_cours.php";

  const annee = <?= (int)$annee ?>;
  const mois  = <?= (int)$mois ?>;

  const sel = document.getElementById('rp_cours');
  const taR = document.getElementById('rp_remontee');
  const taI = document.getElementById('rp_infos');

  // bouton (le premier bouton du bloc)
  const btn = document.querySelector('button.btn.btn-next[type="button"]');

  if(!sel || !taR || !taI || !btn) return;
  
const wrap = document.getElementById('cours_fields_wrap');
if (wrap) {
  wrap.style.display = (sel.value && sel.value !== '') ? 'block' : 'none';
}

  btn.disabled = false;
  btn.title = "";

  async function loadForCours(){
  const idCours = parseInt(sel.value || "0", 10);
  const wrap = document.getElementById('cours_fields_wrap');

  taR.value = "";
  taI.value = "";

  if (wrap) {
    wrap.style.display = idCours ? 'block' : 'none';
  }

  if(!idCours) return;

  try{
    const u = new URL(API_GET, window.location.origin);
    u.searchParams.set('annee', String(annee));
    u.searchParams.set('mois', String(mois));
    u.searchParams.set('id_cours', String(idCours));

    const r = await fetch(u.toString(), {credentials:'include', cache:'no-store'});
    const js = await r.json().catch(()=>null);
    if(js && js.success){
  taR.value = js.remontee || "";
  taI.value = js.infos || "";

  }
  }catch(e){
    console.warn('load remarque failed', e);
  }
}
window.audraMetaLoadForCours = loadForCours;


  sel.addEventListener('change', loadForCours);

  btn.addEventListener('click', async function(){
    const idCours = parseInt(sel.value || "0", 10);
    

    if(!idCours){
      alert("Choisissez d'abord un cours.");
      return;
    }

    const fd = new FormData();
    fd.append('annee', String(annee));
    fd.append('mois',  String(mois));
    fd.append('id_cours', String(idCours));
    fd.append('remontee', taR.value || "");
    fd.append('infos', taI.value || "");
    fd.append('heures_absence', "0");

    try{
      const r = await fetch(API_SAVE, {method:'POST', body:fd, credentials:'include', cache:'no-store'});
      const js = await r.json().catch(()=>null);
      if(js && js.success){
        location.reload(); // robuste
      } else {
        alert("Erreur : " + ((js && (js.error||js.err)) ? (js.error||js.err) : "impossible d'enregistrer"));
      }
    }catch(e){
      alert("Erreur réseau");
    }
  });

})();
</script>


</body>
</html>