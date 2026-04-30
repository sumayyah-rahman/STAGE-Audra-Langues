<?php
// form_prof_upload_check.php — Vérification des pièces + confirmation de déclaration + verrouillage automatique

declare(strict_types=1);

// -----------------------------------------------------------------------------
// Bootstrap portail prof : session + config + libs communes
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page();

// Garde de session minimale
if (empty($_SESSION['display']) && empty($_SESSION['firstname']) && empty($_SESSION['prof_code'])) {
    header('Location: portail_prof.php');
    exit;
}

// -----------------------------------------------------------------------------
// Connexion SQL centrale
// -----------------------------------------------------------------------------
require_once $config['base_path'] . '/app/config/db_config.php';
$conn = db();
if (!$conn) {
    die('❌ Connexion SQL impossible');
}

// -----------------------------------------------------------------------------
// Garde centrale prof (blocage / correction / portail)
// Ici on autorise explicitement le mode CORRECTION
// -----------------------------------------------------------------------------
audra_guard_prof_page($conn, ['allow_correction' => true]);

// Racine de stockage des uploads profs (dossier réseau propre)
require_once $config['base_path'] . '/app/config/uploads_prof.php';

// -----------------------------------------------------------------------------
// Contexte utilisateur (prof / admin / correction / période)
// -----------------------------------------------------------------------------
$display = $_SESSION['display'] ?? (($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? ''));
$prof    = strtoupper(trim($display));
$isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'];
$modeCorrection = !empty($_SESSION['mode_correction']) && $_SESSION['mode_correction'];
$statut  = $_SESSION['statut'] ?? '';

if ($isAdmin) {
    $annee = isset($_GET['annee']) ? (int)$_GET['annee'] : (int)date('Y');
    $mois  = isset($_GET['mois'])  ? (int)$_GET['mois']  : (int)date('n');
    if (!empty($_GET['prof'])) {
        $display = $_GET['prof'];
        $prof    = strtoupper(trim($display));
    }
} else {
    $annee = (int)($_SESSION['annee'] ?? date('Y'));
    $mois  = (int)($_SESSION['mois']  ?? date('n'));
}

// === Statuts docs & statut prof (TNS/Salarié) pour ce mois ===
if ($isAdmin) {
    $profKey = $_GET['code'] ?? ($_SESSION['prof_code'] ?? ($_GET['prof'] ?? ''));
} else {
    // Prof : on ignore code= en GET, on force la session
    $profKey = $_SESSION['prof_code'] ?? ($_SESSION['display'] ?? '');
}


$presenceOK = 0; $factureOK = 0; $nbDocs = 0;
$__st = sqlsrv_query(
    $conn,
    "SELECT presence_ok, facture_ok, nb_docs FROM dbo.AudraWeb_fx_DocsStatus(?, ?, ?)",
    [ (string)$profKey, (int)$annee, (int)$mois ]
);
if ($__st) {
    if ($__r = sqlsrv_fetch_array($__st, SQLSRV_FETCH_ASSOC)) {
        $presenceOK = (int)$__r['presence_ok'];
        $factureOK  = (int)$__r['facture_ok'];
        $nbDocs     = (int)$__r['nb_docs'];
    }
    sqlsrv_free_stmt($__st);
}

$isTNS = false;

// On récupère le statut depuis la base (Colleague) si possible
$statForTns = '';
$__st2 = sqlsrv_query(
    $conn,
    "SELECT TOP 1 xx_Statut_prof
     FROM dbo.Colleague
     WHERE Id = ?",
    [ (string)$profKey ]
);
if ($__st2 && ($__r2 = sqlsrv_fetch_array($__st2, SQLSRV_FETCH_ASSOC))) {
    $statForTns = (string)($__r2['xx_Statut_prof'] ?? '');
}
if ($__st2) sqlsrv_free_stmt($__st2);

// Déduire TNS/HONORAIRE à partir du statut Colleague (sinon fallback session)
$stProf = strtoupper(trim($statForTns !== '' ? $statForTns : (string)($statut ?? '')));
$isTNS  = (strpos($stProf, 'TNS') !== false) || (strpos($stProf, 'HONORAIRE') !== false);

// (optionnel mais propre) : si on a un statut base, on l'affiche aussi à l'écran
if ($statForTns !== '') {
    $statut = $statForTns;
}


// Fallback : si on n’a rien en base, on se rabat sur le statut en session
if ($statForTns === '') {
    $statForTns = (string)($statut ?? '');
}

// Détection robuste : TNS ou HONORAIRE (AHONORAIRE contient HONORAIRE)
$su = strtoupper(trim($statForTns));
$isTNS = (strpos($su, 'TNS') !== false) || (strpos($su, 'HONORAIRE') !== false);


$needsFacture = $isTNS;
$canConfirm   = ($presenceOK === 1) && (!$needsFacture || $factureOK === 1);
$disableAttr  = $canConfirm ? '' : 'disabled';
$helpMsg      = (!$presenceOK ? "Feuille de présence manquante. " : "")
             . (($needsFacture && !$factureOK) ? "Facture manquante (TNS)." : "");

// === Vérifier si le mois est déjà verrouillé (CODE OU NOM) ===
$isLocked = false;

// on privilégie le code si présent, sinon le nom
$profLock = strtoupper(trim((string)$profKey));
if ($profLock === '') {
    $profLock = strtoupper(trim((string)$prof));
}

$sqlLock = "
    SELECT TOP 1 espace_bloque
    FROM dbo.AudraWeb_Espace_Bloque
    WHERE (UPPER(prof)=UPPER(?) OR UPPER(prof)=UPPER(?))
      AND annee=? AND mois=? AND espace_bloque=1
";

$stLock = sqlsrv_query($conn, $sqlLock, [$profLock, $prof, $annee, $mois]);
if ($stLock && ($rowLock = sqlsrv_fetch_array($stLock, SQLSRV_FETCH_ASSOC))) {
    $isLocked = true;
}
if ($stLock) sqlsrv_free_stmt($stLock);


// === Définition du dossier upload (SOURCE UNIQUE : uploads_prof.php) ===
$mm     = str_pad((string)$mois, 2, '0', STR_PAD_LEFT);
$folder = audra_prof_dir($prof, $annee, $mois);

function audra_doc_kind_and_base(string $filename): array {
    $name = trim($filename);
    $lower = mb_strtolower($name, 'UTF-8');

    $kind = 'AUTRE';
    if (
        strpos($lower, 'presence') !== false
        || strpos($lower, 'présence') !== false
        || strpos($lower, 'feuille') !== false
        || strpos($lower, 'emargement') !== false
        || strpos($lower, 'émargement') !== false
        || strpos($lower, 'attendance') !== false
    ) {
        $kind = 'PRESENCE';
    } elseif (
        strpos($lower, 'facture') !== false
        || strpos($lower, 'invoice') !== false
        || strpos($lower, 'fattura') !== false
        || strpos($lower, 'honoraire') !== false
        || strpos($lower, 'honoraires') !== false
    ) {
        $kind = 'FACTURE';
    }

    $base = preg_replace('/\.[^.]+$/u', '', $lower);
    $base = preg_replace('/\s*\(\d+\)\s*$/u', '', $base);
    $base = preg_replace('/[\s_-]*v\d+\s*$/u', '', $base);
    $base = preg_replace('/[\s_-]*(corrige|corrigé|final|bis)\s*$/u', '', $base);
    $base = preg_replace('/\s+/u', ' ', trim($base));

    return [$kind, $base];
}



// === Scan des fichiers ===
$files = [];
if (is_dir($folder)) {
    $scan = @scandir($folder);
    if ($scan !== false) {
        foreach ($scan as $f) {
            if ($f==='.' || $f==='..') continue;
            $path = $folder.DIRECTORY_SEPARATOR.$f;
            if (is_file($path)) {
                $files[] = [
                    'name'  => $f,
                    'size'  => filesize($path),
                    'mtime' => filemtime($path),
                ];
            }
        }
    }
}

// Tri : plus récent -> plus ancien (plus lisible)
usort($files, function ($a, $b) {
    return (($b['mtime'] ?? 0) <=> ($a['mtime'] ?? 0));
});

$filesGrouped = [];

foreach ($files as $f) {
    [$kind, $base] = audra_doc_kind_and_base((string)($f['name'] ?? ''));
    $groupKey = $kind . '|' . $base;

    if (!isset($filesGrouped[$groupKey])) {
        $filesGrouped[$groupKey] = [];
    }

    $f['kind'] = $kind;
    $f['base'] = $base;
    $filesGrouped[$groupKey][] = $f;
}


// Conserver tous les fichiers trouvés pour l'écran de vérification finale
// (ne pas limiter à un seul fichier PRESENCE / FACTURE)

// On recalcule presenceOK / factureOK à partir des fichiers trouvés
$presenceOK = false;
$factureOK  = false;

foreach ($files as $it) {
    $n = mb_strtolower((string)($it['name'] ?? ''), 'UTF-8');

    // ✅ PRESENCE / FEUILLES / EMARGEMENT / ATTENDANCE
    if (
        !$presenceOK
        && (
            strpos($n, 'presence') !== false
            || strpos($n, 'présence') !== false
            || strpos($n, 'feuille') !== false
            || strpos($n, 'emargement') !== false
            || strpos($n, 'émargement') !== false
            || strpos($n, 'attendance') !== false
        )
    ) {
        $presenceOK = true;
    }

   // ✅ FACTURE / INVOICE / FATTURA / HONORAIRE(S)
if (
    !$factureOK
    && (
        strpos($n, 'facture') !== false
        || strpos($n, 'invoice') !== false
        || strpos($n, 'fattura') !== false
        || strpos($n, 'honoraire') !== false
        || strpos($n, 'honoraires') !== false
    )
) {
    $factureOK = true;
}


    // Stop dès qu'on a tout ce qu'il faut
    if ($presenceOK && (!$isTNS || $factureOK)) {
        break;
    }
}

$needsFacture = $isTNS;
$canConfirm   = ($presenceOK === true) && (!$needsFacture || $factureOK === true);
$disableAttr  = $canConfirm ? '' : 'disabled';
$helpMsg      = (!$presenceOK ? "Feuille de présence manquante. " : "")
             . (($needsFacture && !$factureOK) ? "Facture manquante (TNS/Honoraire)." : "");

// === Paramètre global admin : activation / désactivation du bouton final prof ===
$profConfirmButtonEnabled = true;

$stSet = sqlsrv_query(
    $conn,
    "SELECT TOP 1 setting_value
     FROM dbo.AudraWeb_Settings
     WHERE setting_key = ?",
    ['PROF_CONFIRM_BUTTON_ENABLED']
);

if ($stSet && ($rowSet = sqlsrv_fetch_array($stSet, SQLSRV_FETCH_ASSOC))) {
    $profConfirmButtonEnabled = ((string)($rowSet['setting_value'] ?? '1') === '1');
}
if ($stSet) {
    sqlsrv_free_stmt($stSet);
}

// On désactive le bouton uniquement côté prof normal.
// Admin et mode correction restent libres.
if (!$isAdmin && !$modeCorrection && !$profConfirmButtonEnabled) {
    $canConfirm  = false;
    $disableAttr = 'disabled';
    $helpMsg     = "Confirmation finale temporairement désactivée par l'administration.";
}

$errParam = isset($_GET['err']) ? (string)$_GET['err'] : '';

// ---------- Flash ----------


$flash = $_GET['flash'] ?? '';
$ftype = $_GET['ftype'] ?? 'ok';
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Vérification des pièces — <?= htmlspecialchars($display) ?> — <?= $mm ?>/<?= $annee ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{font-family:Arial,Helvetica,sans-serif;background:#f3f4f6;margin:0;padding:0;}
  .wrap{max-width:900px;margin:24px auto;padding:20px;background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.08);}
  h1{font-size:22px;margin:0 0 10px}
  .meta{color:#4b5563;margin-bottom:14px}
  .chips{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 14px}
  .chip{padding:6px 10px;border-radius:999px;font-size:13px}
  .ok{background:#e7f8ee;color:#065f46;border:1px solid #10b981}
  .ko{background:#fde2e2;color:#7f1d1d;border:1px solid #ef4444}
  .neutral{background:#f3f4f6;color:#374151;border:1px solid #d1d5db} 
  table{width:100%;border-collapse:collapse;margin:10px 0 16px}
  th,td{border:1px solid #e5e7eb;padding:8px;text-align:left;font-size:14px}
  th{background:#f9fafb}
  .btn{display:inline-block;padding:10px 14px;border:none;border-radius:8px;background:#1d4ed8;color:#fff;font-weight:600;cursor:pointer}
  .btn:disabled{opacity:.6;cursor:not-allowed}
  .btn-outline{background:#fff;color:#1f2937;border:1px solid #d1d5db}
  .bar{display:flex;gap:10px;align-items:center;justify-content:flex-end;margin-top:12px}
  .alert{padding:10px 12px;border-radius:8px;margin-bottom:12px}
  .alert.err{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca}
  .alert.ok {background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
  .small{font-size:12px;color:#6b7280}
  .bandeau-admin{background:#fde68a;color:#92400e;padding:10px 12px;border-bottom:2px solid #e0c060;font-weight:bold;text-align:center}
  .flash{max-width:900px;margin:14px auto 0 auto;padding:10px 14px;border-radius:8px;border:1px solid transparent;font-weight:600}
  .flash.ok{background:#ecfdf5;border-color:#a7f3d0;color:#065f46}
  .flash.warn{background:#fffbeb;border-color:#fde68a;color:#92400e}
  .flash.err{background:#fee2e2;border-color:#fecaca;color:#7f1d1d}
</style>
</head>
<body>

  <?php if ($isAdmin): ?>
  <div class="bandeau-admin">
    🔑 Mode Contrôle Admin — Espace de <?= htmlspecialchars($prof) ?> — <?= $mm ?>/<?= $annee ?>
  </div>
  <?php endif; ?>

  <?php if ($flash): ?>
    <div class="flash <?= $ftype==='err'?'err':($ftype==='warn'?'warn':'ok') ?>"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="wrap">
    <h1>Vérification des pièces — <?= htmlspecialchars($display) ?> (<?= htmlspecialchars((string)$profKey) ?>) — <?= $mm ?>/<?= $annee ?></h1>
<div class="meta">
  Code prof : <b><?= htmlspecialchars((string)$profKey) ?></b>
  <?php if (!empty($statForTns)): ?> — Statut prof : <b><?= htmlspecialchars((string)$statForTns) ?></b><?php endif; ?>
  <?php if ($modeCorrection): ?><span class="chip ok">✏️ Mode correction</span><?php endif; ?>
  <?php if ($isAdmin): ?><span class="chip ok">🔑 Admin</span><?php endif; ?>
</div>

    <?php if ($isLocked): ?>
      <div class="alert ok" style="font-weight:bold;">
        ✅ Votre déclaration du mois de <?= $mm ?>/<?= $annee ?> est déjà verrouillée et traitée. Merci !
      </div>
    <?php endif; ?>

    <?php if ($errParam): ?>
      <div class="alert err">❌ Erreur précédente : <b><?= htmlspecialchars($errParam, ENT_QUOTES, 'UTF-8') ?></b></div>
    <?php endif; ?>

    <div class="chips" style="margin-top:6px">
      <span class="chip <?= $presenceOK ? 'ok' : 'ko' ?>">
        Feuille de présence : <?= $presenceOK ? 'OK' : 'MANQUANTE' ?>
      </span>

      <?php if ($isTNS): ?>
        <span class="chip <?= $factureOK ? 'ok' : 'ko' ?>">
          Facture (TNS) : <?= $factureOK ? 'OK' : 'MANQUANTE' ?>
        </span>
      <?php else: ?>
        <span class="chip ok">Facture : non requise (salarié)</span>
      <?php endif; ?>
    </div>

   <table>
  <thead>
    <tr>
      <th>Type</th>
      <th>Fichier</th>
      <th>Taille</th>
      <th>Modifié le</th>
    </tr>
  </thead>
  <tbody>
  <?php if (empty($filesGrouped)): ?>
    <tr>
      <td colspan="4" class="small">
        Aucun fichier trouvé dans <code><?= htmlspecialchars($folder, ENT_QUOTES, 'UTF-8') ?></code>
      </td>
    </tr>
  <?php else: foreach ($filesGrouped as $group): foreach ($group as $idx => $f): ?>
    <?php
      $filename = (string)($f['name'] ?? '');

      $url = '../../actions/get_doc_prof.php?prof=' . urlencode($prof)
           . '&annee=' . (int)$annee
           . '&mois='  . (int)$mois
           . '&file='  . urlencode($filename);

      // Détection simple du type (PRESENCE / FACTURE / AUTRE)
$kind = (string)($f['kind'] ?? 'AUTRE');


    ?>
    <tr>
      <?php
  $kcls = ($kind === 'PRESENCE' || $kind === 'FACTURE') ? 'ok' : 'neutral';
?>
<td><span class="chip <?= $kcls ?>"><?= htmlspecialchars($kind, ENT_QUOTES, 'UTF-8') ?></span></td>

      <td>
        📄 <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
          <?= htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') ?>
        </a>
      </td>
      <td><?= number_format(((float)($f['size'] ?? 0))/1024, 1, ',', ' ') ?> Ko</td>
      <td><?= date('d/m/Y H:i', (int)($f['mtime'] ?? 0)) ?></td>
    </tr>
  <?php endforeach; endforeach; endif; ?>
  </tbody>
</table>


    <?php if ($modeCorrection): ?>
  <div class="alert ok" style="background:#fff8e1;border:1px solid #facc15;color:#92400e;">
    ℹ️ Vous êtes en <strong>mode correction</strong> : vous pouvez téléverser de nouveaux fichiers corrigés.<br>
    ✅ Si vous renvoyez une nouvelle <code>PRESENCE</code> ou <code>FACTURE</code>, la dernière version devient automatiquement la version active (les anciennes restent consultables).<br>
    ℹ️ Si besoin, vous pouvez supprimer les anciennes versions depuis l’écran “Upload” (Historique), ou l’administration pourra le faire.
  </div>
<?php endif; ?>


    <div id="msg" class="alert" style="display:none"></div>

    <div class="bar">
      <button type="button"
              id="btnConfirm"
              class="btn"
              <?= $disableAttr ?>
              title="<?= htmlspecialchars($helpMsg, ENT_QUOTES, 'UTF-8') ?>">
        ✅ JE CONFIRME MA DECLARATION
      </button>

      <button type="button" class="btn btn-outline"
              onclick="window.location.href='form_prof_upload.php?prof=<?= urlencode($display) ?>&code=<?= urlencode($profKey) ?>&annee=<?= (int)$annee ?>&mois=<?= (int)$mois ?>'">
        ← Retour upload
      </button>
    </div>

    <?php if (!$canConfirm): ?>
      <div class="alert err"><?= htmlspecialchars($helpMsg, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <p class="small">En confirmant, vous attestez que les documents requis sont déposés et conformes.</p>
  </div>

<script>
(function () {
  const btn = document.getElementById('btnConfirm');
  const box = document.getElementById('msg');
  if (!btn) return;

  function showMsg(text, ok = false) {
    if (!box) return;
    box.textContent = text || '';
    box.className = 'alert ' + (ok ? 'ok' : 'err');
    box.style.display = text ? 'block' : 'none';
  }

  btn.addEventListener('click', async (e) => {
    // IMPORTANT : si le bouton est dans un <form>, on bloque l’envoi normal
    if (e && typeof e.preventDefault === 'function') {
      e.preventDefault();
    }
    if (btn.disabled) return;

    showMsg('');
    btn.disabled = true;

    try {
      // =========================================================================
      // 🔴 BLOC CONFIRM 1 — Finaliser la déclaration (lecture UNIQUE de la réponse)
      // =========================================================================
      const urlFinal = '../../actions/finaliser_declaration.php?as_json=1';
      const rFinal   = await fetch(urlFinal, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      if (!rFinal.ok) {
        showMsg('❌ Erreur serveur lors de la finalisation (code ' + rFinal.status + ').');
        btn.disabled = false;
        return;
      }

    // ✅ On lit UNE SEULE FOIS le body, en texte, puis on tente JSON.parse
const raw = await rFinal.text();

// ✅ Nettoyage : si PHP a ajouté du texte avant le JSON (warning, BOM, etc.),
// on tente de récupérer le JSON à partir du 1er "{"
const start = raw.indexOf('{');
const cleaned = (start >= 0) ? raw.slice(start).trim() : raw.trim();

let jsFinal = null;
try {
  jsFinal = JSON.parse(cleaned);
} catch (err) {
  showMsg('❌ Réponse non valide lors de la finalisation : ' + raw.slice(0, 200));
  btn.disabled = false;
  return;
}


            // ✅ Si success=false => on affiche le message d’erreur serveur
      if (!jsFinal.success) {
        showMsg(jsFinal.error || '❌ Erreur lors de la finalisation.');
        btn.disabled = false;
        return;
      }

      // ✅ SUCCESS : message différent selon le mode
      <?php if ($modeCorrection): ?>
      showMsg('Correction enregistrée. Le bureau va traiter votre dossier.', true);
      <?php else: ?>
      showMsg('Déclaration transmise.', true);
      <?php endif; ?>



      // =========================================================================
      // 🔴 FIN BLOC CONFIRM 1
      // =========================================================================

      // =========================================================================
      // 🔴 BLOC CONFIRM 2 — Redirection vers la page de confirmation finale
      // =========================================================================
      location.href =
        'form_prof_confirmation.php'
        + '?prof=<?= urlencode($display) ?>'
        + '&code=<?= urlencode($profKey) ?>'
        + '&annee=<?= (int)$annee ?>'
        + '&mois=<?= (int)$mois ?>'
        + '&mode=<?= $modeCorrection ? 'correction' : 'declaration' ?>';
      // =========================================================================
      // 🔴 FIN BLOC CONFIRM 2
      // =========================================================================

    } catch (err) {
      showMsg('❌ Erreur réseau ou JavaScript : ' + (err && err.message ? err.message : err));
      btn.disabled = false;
    }
  });
})();
</script>
