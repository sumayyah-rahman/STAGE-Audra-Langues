<?php
// progres_eleve.php — progrès de l'élève

declare(strict_types=1);

require_once __DIR__ . '/session_eleve.php';

$observation = 'Aucune observation pour le moment.';
$pointARenforcer = 'Aucun point à renforcer pour le moment.';

$idAcces = (int)($_SESSION['id_acces'] ?? 0);

if ($idAcces > 0) {
    require_once __DIR__ . '/../CVT/_db.php';

    $pdo = pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sqlSuivi = "
        SELECT TOP 1
            last_theme,
            last_grammar,
            last_session_date,
            progression_note,
            point_a_renforcer
        FROM dbo.AudraWeb_Eleve_Suivi_IA
        WHERE id_acces = ?
    ";

    $stSuivi = $pdo->prepare($sqlSuivi);
    $stSuivi->execute([$idAcces]);
    $rowSuivi = $stSuivi->fetch(PDO::FETCH_ASSOC);

    if ($rowSuivi) {
        $lastTheme = trim((string)($rowSuivi['last_theme'] ?? ''));
        if ($lastTheme === '') {
            $lastTheme = 'Aucun';
        }

        $lastGrammar = trim((string)($rowSuivi['last_grammar'] ?? ''));
        if ($lastGrammar === '') {
            $lastGrammar = 'Aucun';
        }

        $lastSessionDate = 'Aucune';
        if (!empty($rowSuivi['last_session_date'])) {
            $ts = strtotime((string)$rowSuivi['last_session_date']);
            if ($ts) {
                $lastSessionDate = date('d/m/Y', $ts);
            }
        }

        $observation = trim((string)($rowSuivi['progression_note'] ?? ''));
        if ($observation === '') {
            $observation = 'Aucune observation pour le moment.';
        }

        $pointARenforcer = trim((string)($rowSuivi['point_a_renforcer'] ?? ''));
        if ($pointARenforcer === '') {
            $pointARenforcer = 'Aucun point à renforcer pour le moment.';
        }
    }
}
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
					<p><strong>Certification visée :</strong> <?= htmlspecialchars($certificationVisee) ?></p>
					<p><strong>Dernière séance IA :</strong> <?= htmlspecialchars($lastSessionDate) ?></p>
					<p><strong>Dernier thème pratiqué :</strong> <?= htmlspecialchars($lastTheme) ?></p>
					<p><strong>Dernier point de grammaire :</strong> <?= htmlspecialchars($lastGrammar) ?></p>
				</div>
			</section>

			<section class="content-card">
				<h2>Observation</h2>

				<div class="toc-box">
					<p><?= htmlspecialchars($observation) ?></p>
				</div>
			</section>
			
			<section class="content-card">
				<h2>Point à renforcer</h2>

				<div class="toc-box">
					<p><?= htmlspecialchars($pointARenforcer) ?></p>
				</div>
			</section>			
        </div>
    </body>
</html>
