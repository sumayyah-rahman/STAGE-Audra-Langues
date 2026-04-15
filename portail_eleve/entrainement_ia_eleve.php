<?php
// progres_eleve.php — progrès de l'élève

declare(strict_types=1);

$studentName = 'Sumayyah MAR';
$teacherName = 'Munirah MAR';
$numeroCours = '12345';
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
				<li><a href="cours_eleve.php">Cours</a></li>
				<li><a href="entrainement_ia_eleve.php">Entraînement IA</a></li>
				<li><a href="progres_eleve.php">Progrès</a></li>
				<li><a href="info_perso_eleve.php">Info Perso.</a></li>
				<li><a href="deconnexion_eleve.php">Déconnexion</a></li>				
			</ul>
		</div>
		
        <div class="page">
            <div class="banner">
                <h1>Mode Élève — Espace de <?= htmlspecialchars($studentName) ?></h1>
            </div>



            <section class="content-card">
                <h2>Entraînement IA</h2>

                <div class="toc-box">
				<p>En cours de construction :)</p>
				</div>
            </section>
        </div>
        
        <div id="ai-widget" class="ai-widget hidden">
            <div class="ai-widget-header">
                <span>Assistant oral d'anglais</span>
                <button type="button" id="close-widget" class="close-widget-btn">×</button>
            </div>

            <div class="ai-widget-body">
                <p>Hello! What would you like to talk today?</p>
                <div class="theme-btns">
                    <button type="button" class="theme-btn">Travel</button>
                    <button type="button" class="theme-btn">Shopping</button>
                    <button type="button" class="theme-btn">Work</button>
                    <button type="button" class="theme-btn">School</button>
                    <button type="button" class="theme-btn">Food</button>
                    <button type="button" class="theme-btn">Family</button>
                    <button type="button" class="theme-btn">Hobbies</button>
                    <button type="button" class="theme-btn">Daily Life</button>
					<button type="button" class="theme-btn">Other</button>

                </div>
            </div>
        </div>

        <button class="ai-widget-btn" id="open-widget" title="Parler avec l'IA">💬</button>

        <script src="./assets/js/dashboard_eleve.js"></script>
    </body>
</html>