<?php
// entrainement_ia_eleve.php — entraînement IA de l'élève

declare(strict_types=1);

require_once __DIR__ . '/session_eleve.php';
?>
<!DOCTYPE html>
<html lang="fr">
	<head>
		<meta charset="utf-8">
		<title>Portail Élève — Entraînement IA</title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="./assets/css/dashboard_eleve.css">
	</head>
	<body>

    <div class="nav-bar">
        <ul>
            <li><img src="./assets/photos/audralangues-1.png" alt="Logo Audra Langues"></li>
            <li><a href="dashboard_eleve.php">Accueil</a></li>
            <li><a href="entrainement_ia_eleve.php" class="active">Entraînement IA</a></li>
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
                <p><strong>Langue étudiée :</strong> <?= htmlspecialchars($langueEtudiee) ?></p>
                <p><strong>Contexte :</strong> <?= htmlspecialchars(is_array($contexte) ? implode(', ', $contexte) : (string)$contexte) ?></p>
                <p><strong>Consigne :</strong> Répondez directement dans le chat. L’assistant vous guidera étape par étape.</p>
            </div>
        </section>

        <section class="content-card" id="training-area">
            <h2>Conversation</h2>

            <div id="training-meta" class="toc-box">
                <p><strong>Mode sélectionné :</strong> <span id="selected-mode">Aucun</span></p>
                <p><strong>Sujet / point demandé :</strong> <span id="selected-focus">Aucun</span></p>
            </div>

            <div id="chat-log" class="chat-log" style="margin-top:16px;">
                <div class="msg bot">Hello! What would you like to do today?</div>
                </div>

            <div class="chat-controls" style="margin-top:16px;">
                <input type="text" id="user-input" placeholder="Type or use the mic..." />
                <button type="button" id="send-btn">➜</button>
                <button type="button" id="mic-btn" class="mic">🎤</button>
            </div>
        </section>
    </div>

    <script src="./assets/js/widget_ai_eleve.js"></script>
</body>
</html>
