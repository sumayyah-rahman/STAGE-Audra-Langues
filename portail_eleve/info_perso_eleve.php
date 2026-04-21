<?php
// info_perso_eleve.php — info personnelle de l'élève

declare(strict_types=1);

require_once __DIR__ . '/session_eleve.php';

$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newContexte = trim((string)($_POST['contexte_input'] ?? ''));

    if ($newContexte !== '') {
        $items = array_filter(array_map('trim', explode(',', $newContexte)));
        $_SESSION['contexte'] = $items;
        $contexte = $_SESSION['contexte'];
        $successMessage = 'Contexte mis à jour avec succès.';
    }
}
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

			<?php if ($successMessage !== ''): ?>
				<div class="toc-box" style="margin-bottom:16px; border-color:#86efac; background:#ecfdf5; color:#065f46;">
					<?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
				</div>
			<?php endif; ?>

            <section class="content-card">
                <h2>Info personnelle</h2>

                <div class="toc-box">
					<p><strong>Nom : </strong><?= htmlspecialchars($studentName) ?></p>
					<p><strong>Numéro de cours : </strong><?= htmlspecialchars($numeroCours) ?></p>
					<p><strong>Langue étudiée : </strong><?= htmlspecialchars($langueEtudiee) ?></p>
					<p><strong>Niveau Actuel : </strong><?= htmlspecialchars($niveauActuel) ?></p>
					<p><strong>Niveau Visé : </strong><?= htmlspecialchars($niveauVise) ?></p>
					<p><strong>Objectifs : </strong><?= htmlspecialchars($objectifs) ?></p>
					<p><strong>Contexte :</strong></p>
					<ol>
						<?php foreach ($contexte as $x): ?>
							<li><?= htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8') ?></li>
						<?php endforeach; ?>
					</ol>

					<form method="post" style="margin-top:16px;">
						<label for="contexte_input"><strong>Modifier le contexte :</strong></label>
						<input 
							type="text" 
							id="contexte_input" 
							name="contexte_input" 
							value="<?= htmlspecialchars(is_array($contexte) ? implode(', ', $contexte) : (string)$contexte, ENT_QUOTES, 'UTF-8') ?>" 
							placeholder="Ex. médical, nourriture, avis"
						>
						<button type="submit" style="margin-top:10px;">Enregistrer</button>
					</form>
					<p><strong>Type de formation : </strong><?= htmlspecialchars($typeFormation) ?></p>
					<p><strong>Nom de professeur : </strong><?= htmlspecialchars($teacherName) ?></p>				
				</div>
            </section>
        </div>
    </body>
</html>
