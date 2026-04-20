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
				</div>
			</section>

			<section class="content-card">
				<h2>Choix de l'entraînement</h2>
				<p><strong>Consigne :</strong> Choisissez un thème de conversation et un point de grammaire à travailler (si besoin) avant de commencer. Si vous choisissez 'Other', veuillez préciser dans le chatbox :)</p>

				<div class="toc-box">
					<label for="theme-select"><strong>Thème :</strong></label>
					<select id="theme-select">
						<option value="">-- Choisir un thème --</option>
						<option value="Travel">Travel</option>
						<option value="Shopping">Shopping</option>
						<option value="Work">Work</option>
						<option value="School">School</option>
						<option value="Food">Food</option>
						<option value="Family">Family</option>
						<option value="Hobbies">Hobbies</option>
						<option value="Daily Life">Daily Life</option>
						<option value="Other">Other</option>
					</select>

					<br><br>

					<label for="grammar-select"><strong>Point de grammaire :</strong></label>
					<select id="grammar-select">
						<option value="">-- Aucun point particulier --</option>
						<option value="Present Tense">Present Tense</option>
						<option value="Past Tense">Past Tense</option>
						<option value="Future Tense">Future</option>
						<option value="Question Forms">Question Forms</option>
						<option value="Other">Other</option>
					</select>

					<br><br>

					<div class="training-controls">
						<button type="button" id="start-training-btn">Commencer l'entraînement</button>
					</div>
				</div>
			</section>

			<section class="content-card" id="training-area">
				<h2>Conversation</h2>

				<div id="training-meta" class="toc-box">
					<p><strong>Thème sélectionné :</strong> <span id="selected-theme">Aucun</span></p>
					<p><strong>Point de grammaire :</strong> <span id="selected-grammar">Aucun</span></p>
				</div>

				<div id="chat-log" class="chat-log" style="margin-top:16px;">
					<div class="msg bot">Hello! Choose a theme and click “Commencer l'entraînement” to start.</div>
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
