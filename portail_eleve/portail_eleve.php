<?php
// portail_eleve.php — page de connexion de l'élève

declare(strict_types=1);

require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page(); // TODO: adapter à un bootstrap élève dédié s’il en existe un

if (($config['env'] ?? 'DEV') === 'DEV') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Si déjà connecté, on redirige directement
if (!empty($_SESSION['student_logged']) && $_SESSION['student_logged'] === true) {
    header('Location: dashboard_eleve.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim((string)($_POST['login'] ?? ''));
    $password = trim((string)($_POST['password'] ?? ''));

    // Accès test temporaire
    if ($login === 'boss' && $password === 'boss') {
        $_SESSION['student_logged']  = true;
        $_SESSION['student_name']    = 'Sumayyah MAR';
        $_SESSION['teacher_name']    = 'Munirah MAR';
        $_SESSION['course_number']   = '12345';
        $_SESSION['student_email']   = 'boss';
        $_SESSION['langue_etudiee']  = 'English';
        $_SESSION['niveau_actuel']   = 'B2';
        $_SESSION['niveau_vise']     = 'C1';
        $_SESSION['objectifs']       = 'langue professionnelle';
        $_SESSION['type_formation']  = 'Présentiel';

        header('Location: dashboard_eleve.php');
        exit;
    } else {
        $error = 'Identifiants incorrects.';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
	<head>
		<meta charset="UTF-8">
		<title>Portail Élève — Connexion</title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="./assets/css/portail_eleve.css">
	</head>
	<body>
		<div class="login-container">
			<img src="./assets/photos/audralangues-1.png" alt="Logo Audra Langues">
			<h1>Connexion</h1>

			<?php if ($error !== ''): ?>
				<p style="color:#b91c1c; font-weight:600; margin-bottom:12px;">
					<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
				</p>
			<?php endif; ?>

			<form method="post" action="">
				<label for="login">Adresse email</label>
				<input type="text" id="login" name="login" required>

				<label for="password">Mot de passe</label>
				<div class="password-wrapper">
					<input type="password" id="password" name="password" required>
					<span class="toggle-eye" onclick="togglePassword()">👁</span>
				</div>

				<button type="submit">Se connecter</button>
			</form>
		</div>
        <script src="./assets/js/portail_eleve.js"></script>
	</body>
</html>
