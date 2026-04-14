<?php
// dashboard_eleve.php — tableau de bord de l'élève

declare(strict_types=1);

$studentName = 'Sumayyah MAR';
$teacherName = 'Munirah MAR';
$numeroCours = '12345';
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
        <div class="page">
            <div class="banner">
                <h1>Mode Élève — Espace de <?= htmlspecialchars($studentName) ?></h1>
            </div>

            <div class="panel">
                <p><strong>Bienvenue <?= htmlspecialchars($studentName) ?></strong></p>
                <p><strong>Nom du professeur : </strong><?= htmlspecialchars($teacherName) ?></p>
                <p><strong>N° de cours : </strong> <?= htmlspecialchars($numeroCours) ?></p>
            </div>

            <section class="content-card">
                <h2>Tableau de matière</h2>

                <div class="toc-box">
                <p>Aucun à voir maintenant</p>
                </div>
            </section>
        </div>
        
        <div id="ai-widget" class="ai-widget hidden">
            <div class="ai-widget-header">
                <span>Assitant oral d'anglais</span>
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
