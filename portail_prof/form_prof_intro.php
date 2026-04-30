<?php
// form_prof_intro.php — page d’introduction du workflow de saisie

declare(strict_types=1);

// -----------------------------------------------------------------------------
// Bootstrap portail prof : session + config + libs communes
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page();

// Si la session prof est vide → retour portail
if (
    empty($_SESSION['display']) &&
    empty($_SESSION['firstname']) &&
    empty($_SESSION['prof_code'])
) {
    header('Location: portail_prof.php');
    exit;
}

// -----------------------------------------------------------------------------
// Connexion SQL (sqlsrv maintenant actif sur le serveur)
// -----------------------------------------------------------------------------
require_once $config['base_path'] . '/app/config/db_config.php';
$conn = db();
if (!$conn) {
    die('❌ Connexion SQL impossible');
}

// -----------------------------------------------------------------------------
// Garde centrale de sécurité avec autorisation correction
// -----------------------------------------------------------------------------
audra_guard_prof_page($conn, ['allow_correction' => true]);

// 🔑 Mode admin ?
$isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'];

// 🔎 Mode Correction (prof avec mois débloqué par admin)
$modeCorrection = !empty($_SESSION['mode_correction']) && $_SESSION['mode_correction'];

// ---- (inchangé ci-dessous, sauf logique mois/année) ----

/* ------------------------------------------------------------- */
/* 🎯 Contexte mois / année
   - Admin        : lit ?annee=&?mois= si présents, sinon reprend la session
   - Prof / normal: lit toujours $_SESSION['annee'] / $_SESSION['mois']
     (fixés dans portail_prof.php à partir de AudraWeb_Regles_Periodiques)
*/
/* ------------------------------------------------------------- */
if ($isAdmin) {
    $annee = isset($_GET['annee']) ? (int)$_GET['annee'] : (int)($_SESSION['annee'] ?? date('Y'));
    $mois  = isset($_GET['mois'])  ? (int)$_GET['mois']  : (int)($_SESSION['mois']  ?? date('n'));
    if (!empty($_GET['prof'])) {
        $_SESSION['display'] = $_GET['prof'];
    }
} else {
    $annee = isset($_SESSION['annee']) ? (int)$_SESSION['annee'] : (int)date('Y');
    $mois  = isset($_SESSION['mois'])  ? (int)$_SESSION['mois']  : (int)date('n');

    if ($annee === 0 || $mois === 0) {
        header('Location: saisie_fermee.php');
        exit;
    }
}

$PROF    = strtoupper(trim($_SESSION['display']));
$prenom  = $_SESSION['firstname'] ?? '';
$nom     = $_SESSION['lastname'] ?? '';
$statut  = $_SESSION['statut'] ?? ''; // ✅ récupération du statut

$moisNoms = ["Janvier","Février","Mars","Avril","Mai","Juin","Juillet",
             "Août","Septembre","Octobre","Novembre","Décembre"];
$moisTxt = $moisNoms[$mois-1] ?? $mois;
?>

<!doctype html>

<html lang="fr">
<head>
<meta charset="utf-8">
<title>Formulaire Prof — Étape 1</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{font-family:sans-serif;background:#f9fafb;margin:2rem}
  .panel{background:#fff;padding:1.5rem;margin:auto;border-radius:10px;
         box-shadow:0 2px 6px rgba(0,0,0,.1);max-width:640px;text-align:center}
  h1{color:#1d4ed8;margin-top:0}
  .banner{padding:.7rem 1rem;border-radius:6px;margin:1rem 0;font-weight:bold}
  .prof-mode{background:#dcfce7;color:#065f46;}
  .admin-mode{background:#fde68a;color:#92400e;}
  .correction-mode{background:#fef3c7;color:#92400e;} /* Jaune clair pour correction */
  .btn{display:inline-block;margin:10px;padding:10px 20px;
       border:none;border-radius:6px;font-size:15px;font-weight:bold;
       cursor:pointer;text-decoration:none}
  .btn-primary{background:#2563eb;color:#fff}
  .btn-primary:hover{background:#1e40af}
  .btn-admin{background:#e11d48;color:#fff}
  .btn-admin:hover{background:#9f1239}
  .btn-logout{background:#6b7280;color:#fff}
  .btn-logout:hover{background:#374151}
  
  
    .banner{
    position:relative;
    padding-right:220px;
  }
  .btn-tutoriel-banner{
    position:absolute;
    right:12px;
    top:50%;
    transform:translateY(-50%);
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
  .btn-tutoriel-banner:hover{
    background:#1d4ed8;
    color:#fff;
    text-decoration:none;
  }
</style>
</head>
<body>

<div class="banner 
    <?= $isAdmin ? 'admin-mode' : ($modeCorrection ? 'correction-mode' : 'prof-mode') ?>">
  <?php if ($isAdmin): ?>
    🔑 Mode Contrôle Admin — Espace de <?= htmlspecialchars($PROF) ?> — <?= $moisTxt ?> <?= $annee ?>
  <?php elseif ($modeCorrection): ?>
    ✏️ Mode Correction — <?= htmlspecialchars($PROF) ?> — <?= $moisTxt ?> <?= $annee ?>
  <?php else: ?>
    👤 Mode Professeur — Espace de <?= htmlspecialchars($PROF) ?> — <?= $moisTxt ?> <?= $annee ?>
  <?php endif; ?>

  <a class="btn-tutoriel-banner" href="/modules/portail_prof/tutoriel.php?from=intro">
    Consultez le Tutoriel
  </a>
</div>

<div class="panel">
  <p><strong>Étape 1 sur 6</strong></p>

  <h1>
    Bienvenue 
    <?php if ($isAdmin): ?>
      <?= htmlspecialchars($PROF) ?>
    <?php else: ?>
      <?= htmlspecialchars($prenom . " " . $nom) ?>
    <?php endif; ?>
    <?php if (!empty($statut)): ?>
      (<?= htmlspecialchars($statut) ?>)
    <?php endif; ?>
  </h1>

  <p>Ce formulaire va vous guider pour déclarer vos heures de 
     <strong><?= $moisTxt ?> <?= $annee ?></strong>.</p>

  <?php if (!$isAdmin): ?>
  <!-- ✅ Bouton normal pour les profs -->
  <form method="get" action="form_prof_seances.php">
    <input type="hidden" name="annee" value="<?= $annee ?>">
    <input type="hidden" name="mois" value="<?= $mois ?>">
    <button type="submit" class="btn btn-primary">Commencer la saisie</button>
  </form>
  <p>
  <a href="choix_declaration_ou_salle.php" class="btn btn-logout">⬅ Retour écran précédent</a>
</p>
<?php else: ?>
    <!-- ✅ Bouton spécial Admin -->
  <a href="../../admin_controle_saisie.php?annee=<?= $annee ?>&mois=<?= $mois ?>&prof=<?= urlencode($PROF) ?>&code=<?= urlencode($_SESSION['prof_code'] ?? '') ?>" class="btn btn-admin">
    ← Retour contrôle admin
  </a>
<?php endif; ?>

  <!-- 🚪 Bouton déconnexion -->
  <p>
    <a href="deconnexion.php" class="btn btn-logout">🚪 Déconnexion</a>
  </p>
</div>

</body>
</html>
