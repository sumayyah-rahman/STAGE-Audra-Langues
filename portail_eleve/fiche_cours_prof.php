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
        <link rel="stylesheet" href="./assets/css/dashboard_eleve.css">
    </head>
    <body>
	
		<div class="nav-bar">
			<ul>
				<li><img src="./assets/photos/audralangues-1.png" alt="Logo Audra Langues"></li>
				<li><a href="dashboard_prof.php">Accueil</a></li>
				<li><a href="cours_prof.php" class="active">Cours</a></li>
				<li><a href="deconnexion_eleve.php">Déconnexion</a></li>					
			</ul>
		</div>
		
        <div class="page">
            <div class="banner">
                <h1>Mode Professeur — Espace de <?= htmlspecialchars($studentName) ?></h1>
            </div>
			
			<section class="content-card">
                <h2>Liste des élèves</h2>
				
				<div class="group">
					<svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><g><path d="M21.53 20.47l-3.66-3.66C19.195 15.24 20 13.214 20 11c0-4.97-4.03-9-9-9s-9 4.03-9 9 4.03 9 9 9c2.215 0 4.24-.804 5.808-2.13l3.66 3.66c.147.146.34.22.53.22s.385-.073.53-.22c.295-.293.295-.767.002-1.06zM3.5 11c0-4.135 3.365-7.5 7.5-7.5s7.5 3.365 7.5 7.5-3.365 7.5-7.5 7.5-7.5-3.365-7.5-7.5z"></path></g></svg>
					<input placeholder="Search" type="search" class="search-box">
				</div>
                
				<div class="toc-box">
					<div class="student-containers">
						<div class="student">
							<p>Name | N° de cours</p>
						</div>
						<div class="btn-voir-plus">
							<button>Voir plus</button>
						</div>
					</div>
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

        <script src="./assets/js/widget_ai_eleve.js"></script>
    </body>
</html>