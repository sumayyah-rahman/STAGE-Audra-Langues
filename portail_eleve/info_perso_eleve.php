<?php
// info_perso_eleve.php — info personnelle de l'élève

declare(strict_types=1);

require_once __DIR__ . '/session_eleve.php';
?>

<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <title>Portail Elève — Info Personnelle</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="./assets/css/dashboard_eleve.css">
    </head>
    <body>
	
		<div class="nav-bar">
			<ul>
				<li><img src="./assets/photos/audralangues-1.png" alt="Logo Audra Langues"></li>
				<li><a href="dashboard_eleve.php">Accueil</a></li>
				<li><a href="entrainement_ia_eleve.php">Entraînement IA</a></li>
				<li><a href="progres_eleve.php">Progrès</a></li>
				<li><a href="info_perso_eleve.php" class="active">Info Perso.</a></li>
				<li><a href="deconnexion_eleve.php">Déconnexion</a></li>				
			</ul>
		</div>
		
        <div class="page">
            <div class="banner">
                <h1>Mode Élève — Espace de <?= htmlspecialchars($studentName) ?></h1>
            </div>



            <section class="content-card">
                <h2>Info personnelle</h2>

                <div class="toc-box">
				<p><strong>Nom : </strong><?= htmlspecialchars($studentName) ?></p>
				<p><strong>Numéro de cours : </strong><?= htmlspecialchars($numeroCours) ?></p>
				<p><strong>Langue étudiée : </strong><?= htmlspecialchars($langueEtudiee) ?></p>
				<p><strong>Niveau Actuel : </strong><?= htmlspecialchars($niveauActuel) ?></p>
				<p><strong>Niveau Visé : </strong><?= htmlspecialchars($niveauVise) ?></p>
				<p><strong>Objectifs : </strong><?= htmlspecialchars($objectifs) ?></p>
				<p><strong>Contexte : </strong></p>
				<ol>
					<?php foreach ($contexte as $x): ?>
						<li><?= htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8') ?></li>
					<?php endforeach; ?>
				</ol>
				<p><strong>Type de formation : </strong><?= htmlspecialchars($typeFormation) ?></p>
				<p><strong>Nom de professeur : </strong><?= htmlspecialchars($teacherName) ?></p>				
				</div>
            </section>
        </div>

        <script src="./assets/js/widget_ai_eleve.js"></script>
    </body>
</html>
