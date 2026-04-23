<?php
// creer_compte_eleve.php — page de créatiion d'un compte de l'élève

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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentName = trim((string)($_POST['student_name'] ?? ''));
    $studentEmail = trim((string)($_POST['student_email'] ?? ''));
    $courseNumber = trim((string)($_POST['course_number'] ?? ''));
    $login = trim((string)($_POST['login'] ?? ''));
    $password = trim((string)($_POST['password'] ?? ''));

    if (
        $studentName === '' ||
        $studentEmail === '' ||
        $courseNumber === '' ||
        $login === '' ||
        $password === ''
    ) {
        $error = 'Tous les champs sont obligatoires.';
    } else {
        // Vérifie si le login existe déjà
        $sqlCheck = "
            SELECT TOP 1 id
            FROM dbo.AudraWeb_Eleve_Acces
            WHERE login = ?
        ";

        $stCheck = $pdo->prepare($sqlCheck);
        $stCheck->execute([$login]);
        $existing = $stCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $error = 'Cet identifiant existe déjà. Merci d’en choisir un autre.';
        } else {
            // V1 : mot de passe en clair pour test local
            // Plus tard : password_hash($password, PASSWORD_DEFAULT)
            $passwordHash = $password;

            $sqlInsert = "
                INSERT INTO dbo.AudraWeb_Eleve_Acces (
                    login,
                    password_hash,
                    student_name,
                    email,
                    numero_cours,
                    is_active
                )
                VALUES (?, ?, ?, ?, ?, 1)
            ";

            $stInsert = $pdo->prepare($sqlInsert);
            $ok = $stInsert->execute([
                $login,
                $passwordHash,
                $studentName,
                $studentEmail,
                $courseNumber
            ]);

            if ($ok) {
                $success = 'Compte créé avec succès. Vous pouvez maintenant vous connecter.';
            } else {
                $error = 'Une erreur est survenue lors de la création du compte.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
	<head>
		<meta charset="UTF-8">
		<title>Portail Élève — Créer un Compte</title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="./assets/css/portail_eleve.css?v=2">
	</head>
	<body>
		<div class="login-container">
			<img src="./assets/photos/audralangues-1.png" alt="Logo Audra Langues">
			<h1>Création d'un compte</h1>

			<?php if ($error !== ''): ?>
				<p style="color:#b91c1c; font-weight:600; margin-bottom:12px;">
					<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
				</p>
			<?php endif; ?>
			
			<?php if ($success !== ''): ?>
				<p style="color:#065f46; font-weight:600; margin-bottom:12px;">
					<?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
				</p>
			<?php endif; ?>

			<form method="post" action="">
				<label for="student_name">NOM Prénom</label>
				<p>il faut bien respecter la casse (NOM Prénom ou NOM PRENOM)</p>
				<input type="text" id="student_name" name="student_name" required>

				<label for="student_email">Adresse mail</label>
				<input type="email" id="student_email" name="student_email" required>
				
				<label for="course_number">Numéro de cours</label>
				<input type="text" id="course_number" name="course_number" required>

				<label for="login">Identifiant</label>
				<input type="text" id="login" name="login" required>

				<label for="password">Mot de passe</label>
				<div class="password-wrapper">
					<input type="password" id="password" name="password" required>
					<span class="toggle-eye" onclick="togglePassword()">👁</span>
				</div>

				<button type="submit">Créer un compte</button>
				
				<div class="login-links">
					<a href="portail_eleve.php">Retour à la connexion</a>
				</div>
			</form>
		</div>
        <script src="./assets/js/portail_eleve.js"></script>
	</body>
</html>