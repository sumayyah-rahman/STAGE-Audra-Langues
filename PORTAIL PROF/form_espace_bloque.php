<?php
// form_espace_bloque.php — Page d’information quand l’espace prof est verrouillé

declare(strict_types=1);

require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['display']) && empty($_SESSION['firstname']) && empty($_SESSION['prof_code'])) {
    header('Location: portail_prof.php');
    exit;
}

require_once $config['base_path'] . '/app/config/db_config.php';
$conn = db();
if (!$conn) { die('❌ Connexion SQL impossible'); }

// 🧱 Garde centrale de sécurité (BLOCKED / CORRECTION / PORTAIL)
// Ici on autorise explicitement l’état BLOQUÉ + portail fermé pour afficher ce message.
require_once __DIR__ . '/guards_prof.php';
audra_guard_prof_page($conn, [
    'allow_blocked'       => true,
    'allow_portal_closed' => true,
    'allow_correction'    => true,
]);

// 🔑 Mode admin ?
$isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'];

// 🔎 Mode Correction (prof avec mois débloqué par admin)
$modeCorrection = !empty($_SESSION['mode_correction']) && $_SESSION['mode_correction'];

// ---- (inchangé ci-dessous) ----

if ($isAdmin) {
    $annee = isset($_GET['annee']) ? (int)$_GET['annee'] : (int)date('Y');
    $mois  = isset($_GET['mois'])  ? (int)$_GET['mois']  : (int)date('n');
    if (!empty($_GET['prof'])) {
        $_SESSION['display'] = $_GET['prof'];
    }
} else {
    $annee = $_SESSION['annee'] ?? (int)date('Y');
    $mois  = $_SESSION['mois']  ?? (int)date('n');
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
<title>Espace bloqué</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{font-family:sans-serif;background:#f9fafb;margin:2rem}
  .panel{background:#fff;padding:1.5rem;margin:auto;border-radius:10px;
         box-shadow:0 2px 6px rgba(0,0,0,.1);max-width:640px;text-align:center}
  h1{color:#b91c1c;margin-top:0}
  .banner{padding:.7rem 1rem;border-radius:6px;margin:1rem 0;font-weight:bold}
  .prof-mode{background:#dcfce7;color:#065f46;}
  .admin-mode{background:#fde68a;color:#92400e;}
  .correction-mode{background:#fef3c7;color:#92400e;} /* Jaune clair pour correction */
  .btn{display:inline-block;margin:10px;padding:10px 20px;
       border:none;border-radius:6px;font-size:15px;font-weight:bold;
       cursor:pointer;text-decoration:none}
  .btn-primary{background:#2563eb;color:#fff}
  .btn-primary:hover{background:#1e40af}
  .btn-logout{background:#6b7280;color:#fff}
  .btn-logout:hover{background:#374151}
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
</div>

<div class="panel">
  <h1>Espace de saisie verrouillé</h1>

  <p>
    L’espace de déclaration des heures pour <strong><?= htmlspecialchars($PROF) ?></strong><br>
    au titre du mois de <strong><?= htmlspecialchars($moisTxt) ?> <?= (int)$annee ?></strong>
    est actuellement <strong>verrouillé</strong>.
  </p>

  <p>
    Cela signifie que votre déclaration a été transmise à l’administration et intégrée<br>
    dans les traitements comptables et sociaux (paie / honoraires).
  </p>

  <p>
    Si vous constatez une erreur importante, merci de contacter le bureau Audra Langues :<br>
    <strong>04.93.87.23.11</strong> — <strong>info@audralangues.fr</strong>
  </p>

  <p>
    <a href="portail_prof.php" class="btn btn-primary">⬅ Retour accueil Prof</a>
  </p>

  <!-- 🚪 Bouton déconnexion -->
  <p>
    <a href="deconnexion.php" class="btn btn-logout">🚪 Déconnexion</a>
  </p>
</div>

</body>
</html>
