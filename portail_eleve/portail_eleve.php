<?php
// portail_eleve.php — page de connexion de l'élève

declare(strict_types=1);

require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page(); // TODO: adapter à un bootstrap élève dédié s’il en existe un

require_once __DIR__ . '/../CVT/_db.php';

$pdo = pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

	$sql = "
		SELECT TOP 1
			id,
			login,
			password_hash,
			student_name,
			email,
			numero_cours,
			is_active
		FROM dbo.AudraWeb_Eleve_Acces
		WHERE login = ?
		  AND is_active = 1
	";

	$st = $pdo->prepare($sql);
	$st->execute([$login]);
	$row = $st->fetch(PDO::FETCH_ASSOC);

	if (!$row) {
		$error = 'Identifiants incorrects.';
	} else {
		if (!password_verify($password, (string)$row['password_hash'])) {
			$error = 'Identifiants incorrects.';
		} else {
			$_SESSION['role'] = 'student';
			$_SESSION['student_logged'] = true;
			$_SESSION['id_acces'] = (int)($row['id']);
			$_SESSION['course_number'] = (string)$row['numero_cours'];
			$_SESSION['student_name'] = (string)($row['student_name'] ?? '');
			$_SESSION['student_email'] = (string)($row['email'] ?? '');

			header('Location: dashboard_eleve.php');
			exit;
		}
	}
}
?>

<!DOCTYPE html>
<html lang="fr">
	<head>
		<meta charset="UTF-8">
		<title>Portail Élève — Connexion</title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="./assets/css/portail_eleve.css?v=2">
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
				<label for="login">Identifiant</label>
				<input type="text" id="login" name="login" required>

				<label for="password">Mot de passe</label>
				<div class="password-wrapper">
					<input type="password" id="password" name="password" required>
					<span class="toggle-eye" onclick="togglePassword()">👁</span>
				</div>

				<button type="submit">Se connecter</button>
			</form>
			
			<div class="login-links">
				<div class="login-link-row">
					<p>C'est votre première connexion&nbsp;?</p>
					<a href="creer_compte_eleve.php">Créer un compte</a>
				</div>

				<div class="login-link-row">
					<p>Vous avez oublié votre mot de passe&nbsp;?</p>
					<a href="mdp_oublie_eleve.php">Réinitialiser</a>
				</div>
			</div>
		</div>
        <script src="./assets/js/portail_eleve.js"></script>
	</body>
</html>