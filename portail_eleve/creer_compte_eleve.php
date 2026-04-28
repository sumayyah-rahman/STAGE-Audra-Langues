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
    $passwordConfirmation = trim((string)($_POST['password_confirmation'] ?? ''));

    if (
        $studentName === '' ||
        $studentEmail === '' ||
        $courseNumber === '' ||
        $login === '' ||
        $password === '' ||
        $passwordConfirmation === ''
    ) {
        $error = 'Tous les champs sont obligatoires.';
    } elseif (!filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } elseif (strlen($password) < 12) {
        $error = 'Le mot de passe doit contenir au moins 12 caractères.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Le mot de passe doit contenir au moins une majuscule.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Le mot de passe doit contenir au moins une minuscule.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Le mot de passe doit contenir au moins un chiffre.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Le mot de passe doit contenir au moins un caractère spécial.';
    } elseif ($password !== $passwordConfirmation) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        // 1. Vérifier si le login existe déjà
        $sqlCheckLogin = "
            SELECT TOP 1 id
            FROM dbo.AudraWeb_Eleve_Acces
            WHERE login = ?
        ";
        $stCheckLogin = $pdo->prepare($sqlCheckLogin);
        $stCheckLogin->execute([$login]);
        $existingLogin = $stCheckLogin->fetch(PDO::FETCH_ASSOC);

        if ($existingLogin) {
            $error = 'Cet identifiant existe déjà. Merci d’en choisir un autre.';
        } else {
            // 2. Vérifier si l’email existe déjà
            $sqlCheckEmail = "
                SELECT TOP 1 id
                FROM dbo.AudraWeb_Eleve_Acces
                WHERE email = ?
            ";
            $stCheckEmail = $pdo->prepare($sqlCheckEmail);
            $stCheckEmail->execute([$studentEmail]);
            $existingEmail = $stCheckEmail->fetch(PDO::FETCH_ASSOC);

            if ($existingEmail) {
                $error = 'Cette adresse email est déjà utilisée.';
            } else {
                // 3. Vérifier si le numéro de cours existe dans EBP
                $sqlCheckCours = "
                    SELECT TOP 1 Id
                    FROM dbo.Item
                    WHERE CAST(Id AS nvarchar(40)) = ?
                ";
                $stCheckCours = $pdo->prepare($sqlCheckCours);
                $stCheckCours->execute([$courseNumber]);
                $existingCours = $stCheckCours->fetch(PDO::FETCH_ASSOC);

                if (!$existingCours) {
                    $error = 'Le numéro de cours indiqué n’existe pas.';
                } else {
                    // 4. Créer le compte
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

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
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
	<head>
		<meta charset="UTF-8">
		<title>Portail Élève — Créer un Compte</title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="./assets/css/portail_eleve.css?v=3">
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
				<ul id="password-rules">
				  <li id="rule-length">Au moins 12 caractères</li>
				  <li id="rule-upper">Une majuscule</li>
				  <li id="rule-lower">Une minuscule</li>
				  <li id="rule-number">Un chiffre</li>
				  <li id="rule-special">Un caractère spécial</li>
				</ul>
				
				<label for="password_confirmation">Confirmation du mot de passe</label>
				<div class="password-wrapper">
					<input type="password" id="password_confirmation" name="password_confirmation" required>
				</div>
				<ul id="password-rules">				
					<li id="rule-match">Les mots de passe correspondent</li>
				</ul>

				<button type="submit">Créer un compte</button>
				
				<div class="login-links">
					<a href="portail_eleve.php">Retour à la connexion</a>
				</div>
			</form>
		</div>
		<script src="./assets/js/creer_compte_eleve.js"></script>
	</body>
</html>