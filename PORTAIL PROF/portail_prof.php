<?php
declare(strict_types=1);

// -----------------------------------------------------------------------------
// Bootstrap portail prof : session + config + libs communes
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page();

// -----------------------------------------------------------------------------
// ENV DEV / PROD (facultatif, basé sur $config['env'])
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
// Connexion SQL centrale + rôle (admin ou prof ?)
// -----------------------------------------------------------------------------
require_once $config['base_path'] . '/app/config/db_config.php';
$conn = db();
if (!$conn) {
    die('❌ Connexion SQL impossible');
}

$isAdmin      = !empty($_SESSION['admin']) && $_SESSION['admin'];
$isProfLogged = !empty($_SESSION['display']);

/* ------------------------------------------------------------- */
/* 🎯 Règle de saisie côté PROF
   - La page de connexion reste toujours accessible
   - On ne contrôle la règle d'ouverture QUE si un prof est déjà authentifié
   - Le mois de référence vient de mois_cible / annee_cible
   - Les dates ouverture / fermeture sont comparées en mode "jour inclus"
*/
/* ------------------------------------------------------------- */
if (!$isAdmin && $isProfLogged) {
    $tz       = new DateTimeZone('Europe/Paris');
    $todayObj = new DateTimeImmutable('now', $tz);
    $todayYmd = $todayObj->format('Y-m-d');

    $sqlRule = "
        SELECT TOP 1 ouverture, fermeture, etat, mois_cible, annee_cible
        FROM dbo.AudraWeb_Regles_Periodiques
        WHERE UPPER(RTRIM(LTRIM(etat))) = 'OUVERT'
        ORDER BY id DESC
    ";
    $stmtRule = sqlsrv_query($conn, $sqlRule);
    $rule     = $stmtRule ? sqlsrv_fetch_array($stmtRule, SQLSRV_FETCH_ASSOC) : null;
    if ($stmtRule) {
        sqlsrv_free_stmt($stmtRule);
    }

    if (
        $rule &&
        $rule['ouverture'] instanceof DateTimeInterface &&
        $rule['fermeture'] instanceof DateTimeInterface
    ) {
        $openYmd = $rule['ouverture']->format('Y-m-d');
        $closeYmd = $rule['fermeture']->format('Y-m-d');

        $moisCible  = (int)($rule['mois_cible'] ?? 0);
        $anneeCible = (int)($rule['annee_cible'] ?? 0);

        if (
            $todayYmd >= $openYmd &&
            $todayYmd <= $closeYmd &&
            $moisCible >= 1 && $moisCible <= 12 &&
            $anneeCible >= 2000 && $anneeCible <= 2100
        ) {
            $_SESSION['annee'] = $anneeCible;
            $_SESSION['mois']  = $moisCible;
        } else {
            unset($_SESSION['annee'], $_SESSION['mois']);
            header('Location: saisie_fermee.php');
            exit;
        }
    } else {
        unset($_SESSION['annee'], $_SESSION['mois']);
        header('Location: saisie_fermee.php');
        exit;
    }
}

// 🔑 Accès initié par l'admin (sélection d'un prof + période)
if ($isAdmin && !empty($_GET['prof'])) {
    $prof  = urlencode((string)$_GET['prof']);
    $annee = (int)($_GET['annee'] ?? date('Y'));
    $mois  = (int)($_GET['mois']  ?? date('n'));

    header("Location: form_prof_intro.php?prof=$prof&annee=$annee&mois=$mois");
    exit;
}

// ---------------------------------------------------------
// AUCUN TRAITEMENT LOGIN EN PHP ICI
// → le login est géré côté JS avec get_utilisateurs_colleague.php
//   + set_session_dev.php (initialisation de la session).
// ---------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>Portail Professeurs — Connexion</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body {
      margin:0; font-family:"Segoe UI",Tahoma,sans-serif;
      background:linear-gradient(135deg,#f0f4ff,#d9e6ff);
      display:flex; align-items:center; justify-content:center;
      height:100vh;
    }
    .login-container {
      background:#fff; padding:40px 30px; border-radius:16px;
      box-shadow:0 6px 20px rgba(0,0,0,.1);
      text-align:center; width:100%; max-width:400px;
    }
    .login-container img { width:120px; margin-bottom:14px; }
    h1 { font-size:20px; margin-bottom:18px; color:#2563eb; }
    label { display:block; text-align:left; margin:12px 0 6px; font-weight:600; color:#333; }
    input {
      width:100%; padding:10px; border:1px solid #d1d5db; border-radius:10px; font-size:14px;
    }
    input:focus { outline:none; border:1px solid #2563eb; box-shadow:0 0 4px rgba(37,99,235,.3); }
    button {
      width:100%; padding:12px; border:0; border-radius:10px; font-size:15px;
      font-weight:700; cursor:pointer; background:#2563eb; color:#fff; margin-top:20px;
    }
    button:hover { background:#1e40af; }
    .password-wrapper { position:relative; }
    .toggle-eye {
      position:absolute; right:12px; top:50%;
      transform:translateY(-50%); cursor:pointer;
      font-size:16px; color:#555;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <img src="audralangues-1.png" alt="Logo Audra Langues" />
    <h1>Connexion Formateur</h1>
    <form id="loginForm">
      <label for="login">Adresse email</label>
      <input type="email" id="login" name="login" required />

      <label for="password">Mot de passe</label>
      <div class="password-wrapper">
        <input type="password" id="password" name="password" required />
        <span class="toggle-eye" onclick="togglePassword()">👁</span>
      </div>

      <button type="submit">Se connecter</button>
    </form>
  </div>

  <script>
  function togglePassword() {
    const pwd = document.getElementById("password");
    pwd.type = (pwd.type === "password") ? "text" : "password";
  }

  document.getElementById("loginForm").addEventListener("submit", async (e) => {
    e.preventDefault();

    const login = document.getElementById("login").value.trim().toLowerCase();
    const password = document.getElementById("password").value.trim().toUpperCase();

    try {
      // 1) Récupère les utilisateurs (incachable)
      const res = await fetch("get_utilisateurs_colleague.php?_ts=" + Date.now(), { cache: "no-store" });
      if (!res.ok) {
        alert("Erreur HTTP: " + res.status);
        return;
      }

      const txt = await res.text();
      let utilisateurs = [];
      try {
        utilisateurs = JSON.parse(txt);
      } catch (e) {
        console.log("Réponse get_utilisateurs_colleague invalide:", txt);
        alert("Réponse invalide du serveur.");
        return;
      }

      const user = (utilisateurs || []).find(u =>
        (u.login || "").toLowerCase() === login &&
        (u.mdp   || "").toUpperCase() === password
      );
      if (!user) {
        alert("Identifiants incorrects.");
        return;
      }

      // 2) Initialise la session (INCACHABLE + vérif JSON)
      const display = (
        String(user.nom_formateur_sql || "").trim() ||
        `${(user.nom || "").toString().toUpperCase()} ${(user.prenom || "").toString().toUpperCase()}`
      ).trim();

      const codeProf = (user.mdp || user.code || "").toString().toUpperCase();

      // On remonte de /modules/portail_prof/ vers la racine audra_portail_prod
      const ts = Date.now();
      const url = `../../set_session_dev.php?display=${encodeURIComponent(display)}&email=${encodeURIComponent(login)}&code=${encodeURIComponent(codeProf)}&_ts=${ts}`;

      const sess = await fetch(url, {
        method: "GET",
        credentials: "include",
        cache: "no-store",
      });

      let data = null;
      try { data = await sess.json(); } catch (e) {}

      if (!sess.ok || !data || data.ok !== true) {
        console.log("set_session_dev KO:", sess.status, data);
        alert("Erreur session.");
        return;
      }

      console.log("DEBUG set_session_dev =>", data);

      // ✅ Redirection incachable
      window.location.href = "form_prof_intro.php?_ts=" + Date.now();
      return;

    } catch (err) {
      console.error("Erreur:", err);
      alert("Erreur serveur ou réseau.");
    }
  });
  </script>

</body>
</html>