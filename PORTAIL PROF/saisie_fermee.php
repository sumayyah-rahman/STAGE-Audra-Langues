<?php
// saisie_fermee.php — Page affichée quand le portail est fermé pour le prof

declare(strict_types=1);

// -----------------------------------------------------------------------------
// Bootstrap portail prof : session + config + libs communes
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page();

// Si aucune session prof, on renvoie vers le portail de connexion
if (empty($_SESSION['display']) && empty($_SESSION['firstname']) && empty($_SESSION['prof_code'])) {
    header('Location: portail_prof.php');
    exit;
}

// Vérifie si mode correction
$modeCorrection = !empty($_SESSION['mode_correction']) && $_SESSION['mode_correction'] === true;

// On capture le nom du prof AVANT éventuel nettoyage de session
$prof = $_SESSION['display'] ?? trim((($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')));

// fallback = session (si on n’a pas mieux)
$annee = (int)($_SESSION['annee'] ?? date('Y'));
$moisN = (int)($_SESSION['mois']  ?? date('n'));

function mois_fr(int $mois, int $annee): string {
    static $m = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    if ($mois < 1 || $mois > 12) $mois = (int)date('n');
    if ($annee < 2000 || $annee > 2100) $annee = (int)date('Y');
    return $m[$mois - 1] . ' ' . $annee;
}

$moisTxt = mois_fr($moisN, $annee);

// Si on n’est PAS en mode correction, on nettoie la session prof
// pour que le retour ultérieur sur portail_prof.php revienne bien à la connexion.
if (!$modeCorrection) {
    unset(
        $_SESSION['display'],
        $_SESSION['firstname'],
        $_SESSION['lastname'],
        $_SESSION['email'],
        $_SESSION['prof_code'],
        $_SESSION['annee'],
        $_SESSION['mois']
    );
}

// Connexion SQL (uniquement pour afficher la période théorique d’ouverture)
require_once $config['base_path'] . '/app/config/db_config.php';
$conn = db();

$etatTxt    = "La saisie est actuellement <strong>fermée</strong>.";
$periodeTxt = "";

// On va chercher la règle d’ouverture globale uniquement si pas en correction
if (!$modeCorrection && $conn) {
    $sql = "SELECT TOP 1 ouverture, fermeture, etat, mois_cible, annee_cible
            FROM dbo.AudraWeb_Regles_Periodiques
            ORDER BY id DESC";
    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $etat = strtoupper(trim((string)($row['etat'] ?? '')));

        if ($etat === 'OUVERT') {
            $moisCible  = (int)($row['mois_cible'] ?? 0);
            $anneeCible = (int)($row['annee_cible'] ?? 0);

            if ($moisCible >= 1 && $moisCible <= 12 && $anneeCible >= 2000 && $anneeCible <= 2100) {
                $moisTxt = mois_fr($moisCible, $anneeCible);
            }

            $ouverture = ($row['ouverture'] instanceof DateTimeInterface) ? $row['ouverture']->format('d/m/Y') : null;
            $fermeture = ($row['fermeture'] instanceof DateTimeInterface) ? $row['fermeture']->format('d/m/Y') : null;

            if ($ouverture && $fermeture) {
                $periodeTxt = "La déclaration est prévue entre le <b>$ouverture</b> et le <b>$fermeture</b> inclus.";
            }
        }
    }

    if ($stmt) {
        sqlsrv_free_stmt($stmt);
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title><?= $modeCorrection ? "Mode Correction — " . htmlspecialchars($prof) : "Saisie fermée — " . htmlspecialchars($prof) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:sans-serif;margin:0;background:#f9fafb;color:#111}
    .banner{padding:16px;font-size:18px;font-weight:bold;text-align:center}
    .banner-closed{background:#fee2e2;color:#991b1b;border-bottom:4px solid #fca5a5}
    .banner-correction{background:#fef3c7;color:#92400e;border-bottom:4px solid #facc15}
    .container{max-width:700px;margin:40px auto;background:#fff;
               border:1px solid #ddd;border-radius:10px;padding:30px;
               box-shadow:0 2px 8px rgba(0,0,0,.1); text-align:center}
    .logo{width:120px;margin:0 auto 14px auto;display:block}
        p{margin:10px 0;font-size:15px}
  </style>
</head>
<body>

<div class="banner <?= $modeCorrection ? 'banner-correction' : 'banner-closed' ?>">
  <?php if ($modeCorrection): ?>
    ✏️ Mode Correction — <?= htmlspecialchars($prof) ?> — <?= htmlspecialchars($moisTxt) ?>
  <?php else: ?>
    ⏳ Espace de <?= htmlspecialchars($prof) ?> — <?= htmlspecialchars($moisTxt) ?> : saisie fermée
  <?php endif; ?>
</div>

<div class="container">
  <img src="audralangues-1.png" alt="Logo Audra Langues" class="logo">

  <?php if ($modeCorrection): ?>
    <p>Vous êtes en <strong>Mode Correction</strong> pour le mois de <b><?= htmlspecialchars($moisTxt) ?></b>.</p>
    <p>👉 Vous pouvez modifier vos saisies, puis valider à nouveau.</p>
  <?php else: ?>
    <p><?= $etatTxt ?> pour la période de <b><?= htmlspecialchars($moisTxt) ?></b>.</p>
    <?php if ($periodeTxt): ?>
      <p style="margin-top:20px;"><?= $periodeTxt ?></p>
    <?php endif; ?>
    <p>👉 Merci de vous reconnecter lors de la prochaine période de saisie autorisée.</p>
  <?php endif; ?>

  <p style="margin-top:20px;"><em>📞 Contactez le bureau Audra Langues pour plus d’informations.</em></p>
</div>

</body>
</html>
