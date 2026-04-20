<?php
// progres_eleve.php — progrès de l'élève

declare(strict_types=1);

require_once __DIR__ . '/session_eleve.php';
?>

<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <title>Portail Elève — Progrès</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="./assets/css/dashboard_eleve.css">
    </head>
    <body>
	
		<div class="nav-bar">
			<ul>
				<li><img src="./assets/photos/audralangues-1.png" alt="Logo Audra Langues"></li>
				<li><a href="dashboard_eleve.php">Accueil</a></li>
				<li><a href="entrainement_ia_eleve.php">Entraînement IA</a></li>
				<li><a href="progres_eleve.php" class="active">Progrès</a></li>
				<li><a href="info_perso_eleve.php">Info Perso.</a></li>
				<li><a href="deconnexion_eleve.php">Déconnexion</a></li>				
			</ul>
		</div>
		
        <div class="page">
            <div class="banner">
                <h1>Mode Élève — Espace de <?= htmlspecialchars($studentName) ?></h1>
            </div>



            <section class="content-card">
                <h2>Progrès</h2>

                <div class="toc-box">
				<p><strong>Niveau actuel :</strong> <?= htmlspecialchars($niveauActuel) ?></p>
				<p><strong>Niveau visé :</strong> <?= htmlspecialchars($niveauVise) ?></p>
				<p><strong>Objectif :</strong> <?= htmlspecialchars($objectifs) ?></p>				
				<p>Le suivi de progression est en cours de mise en place</p>
				</div>
            </section>
        </div>

        <script src="./assets/js/widget_ai_eleve.js"></script>
    </body>
</html>
