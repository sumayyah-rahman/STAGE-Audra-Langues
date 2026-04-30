<?php
// prof_espace_bloque.php — page "espace verrouillé" appelée par guards_prof.php

declare(strict_types=1);

// -----------------------------------------------------------------------------
// Bootstrap portail prof : session + config + libs communes
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page();

// ADMIN BYPASS : un admin ne doit jamais voir cette page rouge
if (!empty($_SESSION['admin']) && $_SESSION['admin']) {
    header('Location: form_prof_intro.php');
    exit;
}

if (empty($_SESSION['display']) && empty($_SESSION['firstname']) && empty($_SESSION['prof_code'])) {
    header('Location: portail_prof.php');
    exit;
}

$prof   = $_SESSION['display'] ?? (($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? ''));
$annee  = (int)($_SESSION['annee'] ?? date('Y'));
$moisN  = (int)($_SESSION['mois']  ?? date('n'));

setlocale(LC_TIME, 'fr_FR.utf8','fra');
$annee = (int)($_SESSION['annee'] ?? 0);
$mois  = (int)($_SESSION['mois'] ?? 0);

$moisNoms = [
  1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',
  7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'
];

$moisTexte = ($moisNoms[$mois] ?? ('Mois '.$mois)) . ' ' . $annee;
$moisTxt = $moisTexte;
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Espace bloqué — <?= htmlspecialchars($prof, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:sans-serif;margin:0;background:#f9fafb;color:#111}
    .banner{
      background:#fee2e2;color:#b91c1c;padding:16px;font-size:18px;
      font-weight:bold;border-bottom:4px solid #b91c1c;text-align:center
    }
    .container{
      max-width:700px;margin:40px auto;background:#fff;
      border:1px solid #ddd;border-radius:10px;padding:30px;
      box-shadow:0 2px 8px rgba(0,0,0,.1); text-align:center
    }
    .logo{width:120px;margin:0 auto 14px auto;display:block}
    .btn{
      display:inline-block;margin-top:20px;padding:12px 20px;
      border-radius:6px;background:#6b7280;color:#fff;
      text-decoration:none;font-size:16px;
    }
    .btn:hover{background:#374151;}
    p{margin:10px 0;font-size:15px}
  </style>
</head>
<body>

<div class="banner">
  ⚠️ Espace de <?= htmlspecialchars($prof, ENT_QUOTES, 'UTF-8') ?>
  — <?= htmlspecialchars($moisTxt, ENT_QUOTES, 'UTF-8') ?> verrouillé par le bureau
</div>

<div class="container">
  <img src="audralangues-1.png" alt="Logo Audra Langues" class="logo">

 <p>✅ Votre déclaration pour <b><?= htmlspecialchars($moisTxt, ENT_QUOTES, 'UTF-8') ?></b> a bien été transmise.</p>

<p>
  Elle est actuellement <strong>en cours de contrôle</strong> par l’administration.<br>
  Pour éviter les doublons, vous ne pouvez plus modifier vos saisies pour ce mois.
</p>

<p>
  Si vous constatez un oubli ou une erreur, contactez le bureau Audra Langues :
  <b>04.93.87.23.11</b> — <b>info@audralangues.fr</b>.
</p>


  <a href="portail_prof.php" class="btn">⬅ Retour à l’accueil Prof</a>
</div>

</body>
</html>
