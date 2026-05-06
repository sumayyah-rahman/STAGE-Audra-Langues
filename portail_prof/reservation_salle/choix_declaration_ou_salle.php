<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page();

if (empty($_SESSION['display'])) {
    header('Location: /modules/portail_prof/portail_prof.php');
    exit;
}

$PROF = strtoupper(trim((string)($_SESSION['display'] ?? '')));
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Choix de l’action</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
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
  }
  h1{
    margin-top:0;
    font-size:24px;
    color:#1d4ed8;
    text-align:center;
  }
  .sub{
    text-align:center;
    font-size:16px;
    color:#374151;
    margin-bottom:24px;
  }
  .grid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));
    gap:20px;
    margin-top:20px;
  }
  .card{
    display:block;
    text-decoration:none;
    color:inherit;
    border-radius:12px;
    padding:24px;
    box-shadow:0 2px 6px rgba(0,0,0,.08);
    border:1px solid #e5e7eb;
  }
  .card-blue{
    background:#eff6ff;
    border-color:#bfdbfe;
  }
  .card-green{
    background:#f0fdf4;
    border-color:#bbf7d0;
  }
  .card-title{
    font-size:20px;
    font-weight:800;
    margin-bottom:10px;
  }
  .card-blue .card-title{
    color:#1d4ed8;
  }
  .card-green .card-title{
    color:#16a34a;
  }
  .card-text{
    font-size:14px;
    line-height:1.5;
    color:#374151;
  }
</style>
</head>
<body>

<div class="panel">
  <h1>Bienvenue dans votre espace professeur</h1>
  <p class="sub">Choisissez l’action que vous souhaitez effectuer.</p>

  <div class="grid">
    <a class="card card-blue" href="/modules/portail_prof/form_prof_intro.php">
      <div class="card-title">DÉCLARATION DES HEURES DU MOIS</div>
      <div class="card-text">
        Entrez et déclarez les heures du mois.
      </div>
    </a>

    <a class="card card-green" href="/modules/portail_prof/go_reservation_salle.php">
  <div class="card-title">RESERVATION DE SALLE - SEMAINE PROCHAINE- </div>
  <div class="card-text">
    Entrez et réservez une salle pour les cours de la semaine prochaine.
  </div>
</a>
  </div>

  <div style="text-align:center; margin-top:24px;">
    <a href="/modules/portail_prof/deconnexion.php" style="display:inline-block; padding:10px 18px; border-radius:8px; background:#6b7280; color:#fff; text-decoration:none; font-weight:bold;">
      🚪 Déconnexion
    </a>
  </div>
</div>

</body>
</html>
