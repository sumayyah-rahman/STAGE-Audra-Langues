<?php
declare(strict_types=1);

$returnUrl = (isset($_GET['from']) && $_GET['from'] === 'intro') ? '/modules/portail_prof/form_prof_intro.php' : '/modules/portail_prof/form_prof_seances.php';

?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>TUTORIEL</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{
    font-family:Arial, Helvetica, sans-serif;
    background:#f3f4f6;
    margin:0;
    padding:40px 20px;
  }
  .wrap{
    max-width:760px;
    margin:0 auto;
    background:#fff;
    border-radius:12px;
    box-shadow:0 2px 10px rgba(0,0,0,.12);
    padding:30px;
    text-align:center;
  }
  .topbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    margin-bottom:20px;
    flex-wrap:wrap;
  }
  h1{
    margin:0;
    color:#1d4ed8;
    font-size:30px;
    letter-spacing:.5px;
  }
  .btn-retour{
    display:inline-block;
    text-decoration:none;
    background:#6b7280;
    color:#fff;
    padding:10px 16px;
    border-radius:8px;
    font-weight:bold;
  }
  .btn-retour:hover{
    background:#4b5563;
    color:#fff;
  }
  .subtitle{
    margin:10px 0 30px 0;
    color:#374151;
    font-size:17px;
  }
  .langs{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
    max-width:520px;
    margin:0 auto;
  }
  .lang-card{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:12px;
    text-decoration:none;
    background:#2563eb;
    color:#fff;
    padding:16px 18px;
    border-radius:12px;
    font-size:20px;
    font-weight:bold;
    box-shadow:0 2px 6px rgba(0,0,0,.10);
  }
  .lang-card:hover{
    background:#1d4ed8;
    color:#fff;
  }
  .flag{
    font-size:28px;
    line-height:1;
  }
  .lang-text{
    display:flex;
    flex-direction:column;
    align-items:flex-start;
    text-align:left;
    line-height:1.2;
  }
  .lang-name{
    font-size:20px;
    font-weight:bold;
  }
  .lang-sub{
    font-size:13px;
    font-weight:normal;
    opacity:.95;
  }
  @media (max-width:640px){
    .langs{
      grid-template-columns:1fr;
    }
  }
</style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <h1>TUTORIEL</h1>
      <a class="btn-retour" href="javascript:history.back()">← Retour</a>
    </div>

    <div class="subtitle">Choisissez votre langue</div>

    <div class="langs">
      <a class="lang-card" href="/modules/portail_prof/tutoriel_fr.php">
        <span class="flag">🇫🇷</span>
        <span class="lang-text">
          <span class="lang-name">Français</span>
          <span class="lang-sub">Tutoriel complet</span>
        </span>
      </a>

      <a class="lang-card" href="/modules/portail_prof/tutoriel_en.php">
        <span class="flag">🇬🇧</span>
        <span class="lang-text">
          <span class="lang-name">English</span>
          <span class="lang-sub">Full tutorial</span>
        </span>
      </a>

      <a class="lang-card" href="/modules/portail_prof/tutoriel_it.php">
        <span class="flag">🇮🇹</span>
        <span class="lang-text">
          <span class="lang-name">Italiano</span>
          <span class="lang-sub">Tutorial completo</span>
        </span>
      </a>

      <a class="lang-card" href="/modules/portail_prof/tutoriel_es.php">
        <span class="flag">🇪🇸</span>
        <span class="lang-text">
          <span class="lang-name">Español</span>
          <span class="lang-sub">Tutorial completo</span>
        </span>
      </a>
    </div>
  </div>
</body>
</html>