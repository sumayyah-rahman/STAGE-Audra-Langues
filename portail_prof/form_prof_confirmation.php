<?php
// form_prof_confirmation.php — Confirmation d’envoi

declare(strict_types=1);

// -----------------------------------------------------------------------------
// Bootstrap portail prof : session + config + libs communes
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// -----------------------------------------------------------------------------
// Garde de session minimale : on exige un identifiant de prof en session
// -----------------------------------------------------------------------------
if (
    empty($_SESSION['display'])
    && empty($_SESSION['firstname'])
    && empty($_SESSION['prof_code'])
) {
    header('Location: portail_prof.php');
    exit;
}

// -----------------------------------------------------------------------------
// Connexion SQL centrale (via app/config/db_config.php)
// -----------------------------------------------------------------------------
require_once $config['base_path'] . '/app/config/db_config.php';
$conn = db();
if (!$conn) {
    die("❌ Erreur connexion SQL");
}

// -----------------------------------------------------------------------------
// Helpers NOM (Prénom Nom)
// -----------------------------------------------------------------------------
function audra_title_case(string $s): string {
    $s = trim($s);
    if ($s === '') return '';
    if (function_exists('mb_convert_case')) {
        return mb_convert_case(mb_strtolower($s, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }
    return ucwords(strtolower($s));
}

function audra_guess_prenom_nom(string $display): string {
    $display = trim($display);
    if ($display === '') return '';

    // Heuristique simple : si "NOM PRENOM" on inverse
    $parts = preg_split('/\s+/', $display);
    if (!$parts || count($parts) < 2) return audra_title_case($display);

    $prenom = array_pop($parts);
    $nom = implode(' ', $parts);

    return audra_title_case($prenom) . ' ' . audra_title_case($nom);
}

// -----------------------------------------------------------------------------
// Contexte prof + période
// -----------------------------------------------------------------------------

// 🔑 Mode admin ?
$isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'];

// ✏️ Mode correction ? (on se base sur le GET mode=... pour cette page)
// et on sort automatiquement du mode correction après affichage.
$modeParam = (string)($_GET['mode'] ?? '');
$modeCorrection = ($modeParam === 'correction') || (!empty($_SESSION['mode_correction']) && $_SESSION['mode_correction']);

if ($modeParam === 'correction') {
    $_SESSION['mode_correction'] = false;
}

// Valeurs de période : prioriser GET puis session
$annee = (int)($_GET['annee'] ?? $_SESSION['annee'] ?? date('Y'));
$moisN = (int)($_GET['mois']  ?? $_SESSION['mois']  ?? date('n'));

// Mise en forme mois/année (sans strftime, compatible PHP 8+)
$moisNoms = [
    "Janvier","Février","Mars","Avril","Mai","Juin",
    "Juillet","Août","Septembre","Octobre","Novembre","Décembre"
];
$moisTxt = ($moisNoms[$moisN - 1] ?? (string)$moisN) . ' ' . $annee;

// Code prof
$profCode = strtoupper(trim((string)($_SESSION['prof_code'] ?? '')));

// Nom affiché (on préfère Prénom Nom)
$profFirstname = trim((string)($_SESSION['firstname'] ?? ''));
$profLastname  = trim((string)($_SESSION['lastname'] ?? ''));
$profDisplay   = trim((string)($_SESSION['display'] ?? ''));

$profPN = '';
if ($profFirstname !== '' || $profLastname !== '') {
    $profPN = audra_title_case(trim($profFirstname . ' ' . $profLastname));
}

// Fallback depuis Colleague via le code (Prénom Nom)
if ($profPN === '' && $profCode !== '') {
    $st = sqlsrv_query(
        $conn,
        "SELECT TOP 1
            LTRIM(RTRIM(Contact_FirstName)) AS prenom,
            LTRIM(RTRIM(Contact_Name))      AS nom
         FROM dbo.Colleague
         WHERE Id = ?",
        [$profCode]
    );
    if ($st && ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC))) {
        $p = trim((string)($r['prenom'] ?? ''));
        $n = trim((string)($r['nom'] ?? ''));
        if ($p !== '' || $n !== '') {
            $profPN = audra_title_case(trim($p . ' ' . $n));
        }
    }
    if ($st) sqlsrv_free_stmt($st);
}

// Dernier fallback : display
if ($profPN === '') {
    $profPN = ($profDisplay !== '') ? audra_guess_prenom_nom($profDisplay) : 'Professeur';
}

// -----------------------------------------------------------------------------
// 🔒 Mise à jour du blocage au code si besoin (legacy NOM -> CODE)
// -----------------------------------------------------------------------------
$nomUC = strtoupper(trim($profDisplay));

if ($profCode !== '') {
    // 1) s'il existe déjà une ligne au code → ok
    $st = sqlsrv_query(
        $conn,
        "SELECT TOP 1 1
         FROM dbo.AudraWeb_Espace_Bloque
         WHERE annee=? AND mois=? AND UPPER(LTRIM(RTRIM(prof)))=?",
        [$annee, $moisN, $profCode]
    );
    $hasCode = $st && sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
    if ($st) sqlsrv_free_stmt($st);

    if (!$hasCode && $nomUC !== '') {
        // 2) sinon : convertir la ligne au NOM → CODE (si elle existe)
        sqlsrv_query(
            $conn,
            "UPDATE dbo.AudraWeb_Espace_Bloque
             SET prof = ?
             WHERE annee=? AND mois=? AND UPPER(LTRIM(RTRIM(prof)))=?",
            [$profCode, $annee, $moisN, $nomUC]
        );
    }
}

// -----------------------------------------------------------------------------
// ✅ Infos d’envoi : on lit le dernier event SUBMITTED/CORRECTION_SUBMITTED
// -----------------------------------------------------------------------------
$eventType = $modeCorrection ? 'CORRECTION_SUBMITTED' : 'SUBMITTED';

// Fallbacks (si event absent)
$sentAt = new DateTime('now');
$sentTo = '';
$sentCc = '';

if ($profCode !== '') {
    $stEvt = sqlsrv_query(
        $conn,
        "SELECT TOP 1 created_at, target_email, cc_email
         FROM dbo.AudraWeb_Declaration_Events
         WHERE prof_code = ? AND annee = ? AND mois = ? AND event_type = ?
         ORDER BY created_at DESC",
        [$profCode, $annee, $moisN, $eventType]
    );
    if ($stEvt && ($ev = sqlsrv_fetch_array($stEvt, SQLSRV_FETCH_ASSOC))) {
        // created_at peut être DateTime (sqlsrv) ou string
        if (!empty($ev['created_at'])) {
            if ($ev['created_at'] instanceof DateTimeInterface) {
                $sentAt = new DateTime($ev['created_at']->format('c'));
            } else {
                $tmp = @new DateTime((string)$ev['created_at']);
                if ($tmp) $sentAt = $tmp;
            }
        }

        $t = trim((string)($ev['target_email'] ?? ''));
        $c = trim((string)($ev['cc_email'] ?? ''));

        if ($t !== '') $sentTo = $t;
        if ($c !== '') $sentCc = $c;
    }
    if ($stEvt) sqlsrv_free_stmt($stEvt);
}

$sentDate = $sentAt->format('d/m/Y');
$sentTime = $sentAt->format('H:i');

// URL admin (si besoin)
$adminUrl = '../../admin_controle_saisie.php'
    . '?prof=' . urlencode($profDisplay !== '' ? $profDisplay : $profPN)
    . '&annee=' . (int)$annee
    . '&mois=' . (int)$moisN;

if ($profCode !== '') {
    $adminUrl .= '&code=' . urlencode($profCode);
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Confirmation d’envoi</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{font-family:sans-serif;margin:0;background:#f9fafb;color:#111}
  .container{max-width:800px;margin:40px auto;padding:20px}
  .panel{background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px}
  .ok{color:#065f46;background:#d1fae5;border:1px solid #34d399;padding:12px;border-radius:8px;margin-bottom:15px}
  .muted{color:#6b7280}
  .btn{padding:10px 16px;border:none;border-radius:6px;cursor:pointer;text-decoration:none;font-size:15px}
  .btn-primary{background:#1d4ed8;color:#fff}
  .btn-danger{background:#dc2626;color:#fff;margin-left:10px}
  .banner{padding:10px 15px;border-radius:6px;margin-bottom:20px;font-weight:600}
  .prof-mode{background:#d1fae5;color:#065f46;}
  .admin-mode{background:#fde68a;color:#92400e;}
  .correction-mode{background:#fef3c7;color:#92400e;}
  .alert-correction{background:#fff8e1;border:1px solid #facc15;color:#92400e;padding:10px;border-radius:6px;margin-bottom:15px;}
</style>
</head>
<body>

<div class="banner <?= $isAdmin ? 'admin-mode' : ($modeCorrection ? 'correction-mode' : 'prof-mode') ?>">
  <?php if ($isAdmin): ?>
    🔑 Mode Contrôle Admin — Espace de <?= htmlspecialchars($profPN, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($moisTxt, ENT_QUOTES, 'UTF-8') ?>
  <?php elseif ($modeCorrection): ?>
    ✏️ Mode Correction — <?= htmlspecialchars($profPN, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($moisTxt, ENT_QUOTES, 'UTF-8') ?>
  <?php else: ?>
    👤 Mode Professeur — Espace de <?= htmlspecialchars($profPN, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($moisTxt, ENT_QUOTES, 'UTF-8') ?>
  <?php endif; ?>
</div>

<div class="container">
  <div class="panel">
    <div class="ok">
      <?php if ($modeCorrection): ?>
        ✅ <b>Correction envoyée.</b>
      <?php else: ?>
        ✅ <b>Déclaration envoyée.</b>
      <?php endif; ?>
    </div>

    <?php if ($modeCorrection): ?>
      <div class="alert-correction">
        ℹ️ Votre <strong>correction</strong> a bien été transmise au bureau Audra Langues.<br>
        ✅ Si vous avez renvoyé une nouvelle <code>PRESENCE</code> ou <code>FACTURE</code>, la dernière version est prise en compte (les anciennes restent consultables dans “Historique”).<br>
        ℹ️ Pour éviter les blocages, le système peut renommer automatiquement les fichiers <code>PRESENCE</code>/<code>FACTURE</code> après l’envoi.<br>
        Si un document erroné apparaît encore, vous pouvez le supprimer depuis l’écran “Upload” (Historique) ou l’administration pourra le faire lors du contrôle final.
      </div>
    <?php endif; ?>

    <!-- ✅ Message demandé (mise en page exacte) -->
    <p>
  Merci <b><?= htmlspecialchars($profPN, ENT_QUOTES, 'UTF-8') ?></b><br>
  Nous avons bien reçu votre <b><?= $modeCorrection ? "correction" : "déclaration" ?></b> et vos documents pour <b><?= htmlspecialchars($moisTxt, ENT_QUOTES, 'UTF-8') ?></b>.<br>
  Elle a été envoyée le <b><?= htmlspecialchars($sentDate, ENT_QUOTES, 'UTF-8') ?></b> à <b><?= htmlspecialchars($sentTime, ENT_QUOTES, 'UTF-8') ?></b><br>
  à : <b>direction@audralangues.fr</b>.
</p>

<p>
  Nous allons la traiter dans les meilleurs délais.
</p>

<p class="muted">
  ⚠️ Si vous constatez une erreur, ou si vous pensez avoir oublié des informations ou des documents, ne refaites pas une nouvelle <?= $modeCorrection ? "correction" : "déclaration" ?>.
  Contactez directement le bureau au <b>04.93.87.23.11</b> ou par mail :
  <b>info@audralangues.fr</b> (Julia), <b>direction@audralangues.fr</b> (Elfie).
</p>

    <div style="margin-top:20px">
      <?php if ($isAdmin): ?>
        <button class="btn btn-primary" onclick="window.location.href='<?= htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8') ?>'">
          ⬅ Retour contrôle
        </button>
      <?php else: ?>
        <button class="btn btn-primary" onclick="window.location.href='portail_prof.php'">
          Retour à l’accueil Prof
        </button>
        <a href="deconnexion.php" class="btn btn-danger">🚪 Déconnexion</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// Même clé que dans form_prof_meta : année + mois + prof
const META_KEY = 'audra_meta_<?=(int)$annee?>_<?=(int)$moisN?>_<?=addslashes(
    isset($_SESSION['prof_code']) && $_SESSION['prof_code'] !== ''
        ? strtoupper(trim((string)$_SESSION['prof_code']))
        : strtoupper(trim((string)($_SESSION['display'] ?? 'PROF_INCONNU')))
)?>';

// Sur la page de confirmation, on nettoie le brouillon META
document.addEventListener('DOMContentLoaded', () => {
    try {
        sessionStorage.removeItem(META_KEY);
    } catch (e) {
        // si ça plante, ce n'est pas bloquant
    }
});
</script>

</body>
</html>
