<?php
// dashboard_eleve.php — tableau de bord de l'élève

declare(strict_types=1);

require_once __DIR__ . '/session_eleve.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<title>Portail Elève — Dashboard</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="./assets/css/dashboard_eleve.css?v=2">
</head>
<body>

    <a href="#main-content" class="skip-link">Aller au contenu principal</a>

	<button id="open-sidebar-button" type="button" onclick="openSidebar()" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="navbar">
		☰
	</button>

	<div class="nav-bar" id="navbar">
		<ul>
			<li class="close-li">
				<button id="close-sidebar-button" type="button" onclick="closeSidebar()" aria-label="Fermer le menu">✕</button>
			</li>
			<li><img src="./assets/photos/audralangues-1.png" alt="Logo Audra Langues"></li>
			<li><a href="dashboard_eleve.php" class="active">Accueil</a></li>
			<li><a href="entrainement_ia_eleve.php">Entraînement IA</a></li>
			<li><a href="progres_eleve.php">Progrès</a></li>
			<li><a href="info_perso_eleve.php">Info Perso.</a></li>
			<li class="logout-li"><a href="deconnexion_eleve.php">Déconnexion</a></li>
		</ul>
	</div>

	<div id="overlay" onclick="closeSidebar()" aria-hidden="true"></div>

	<div class="page" id="main-content">
		<div class="banner">
			<h1>Mode Élève — Espace de <?= htmlspecialchars($studentName) ?></h1>
		</div>

		<div class="panel">
			<p><strong>Bienvenue <?= htmlspecialchars($studentName) ?></strong></p>
			<p><strong>Nom du professeur : </strong><?= htmlspecialchars($teacherName) ?></p>
			<p><strong>N° de cours : </strong> <?= htmlspecialchars($numeroCours) ?></p>
		</div>

		<section class="content-card">
			<h2>Derniers contenus</h2>

			<div class="toc-box">
			<?php
			echo '<p><a href="" style="text-decoration: none;">Aucun à voir maintenant</a></p>';
			?>
			</div>
		</section>
	</div>

	<script src="./assets/js/navbar_eleve.js"></script>
</body>
</html>