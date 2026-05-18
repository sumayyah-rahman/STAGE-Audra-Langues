<?php
// consigne_ia_prof.php -- page prof pour remplir une consigne IA

declare(strict_types=1);

require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page();

if (($config['env'] ?? 'DEV') === 'DEV') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
}

if (empty($_SESSION['display'])) {
    header('Location: /modules/portail_prof/portail_prof.php');
    exit;
}

require_once $config['base_path'] . '/app/config/db_config.php';
$conn = db();

if (!$conn) {
    die('❌ Connexion SQL impossible');
}

// acah2 nak selit code kat sini

audra_guard_prof_page($conn, [
    'allow_blocked'       => true,
    'allow_portal_closed' => true,
    'allow_correction'    => true,
]);

$PROF   = strtoupper(trim((string)($_SESSION['display'] ?? '')));
$prenom = (string)($_SESSION['firstname'] ?? '');
$nom    = (string)($_SESSION['lastname'] ?? '');

$profId = (isset($_SESSION['prof_code']) && $_SESSION['prof_code'] !== '')
    ? strtoupper(trim((string)$_SESSION['prof_code']))
    : '';

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Envoi de consigne à l'IA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Pour l’instant on réutilise le CSS élève -->
    <link rel="stylesheet" href="./assets/css/dashboard_eleve.css?v=10">
	<style>
		.consigne-layout {
			display: grid;
			grid-template-columns: 1fr 2fr;
			gap: 24px;
			align-items: start;
			margin-top: 18px;
		}

		.consigne-left,
		.consigne-right {
			background: #ffffff;
		}

		.consigne-form select,
		.consigne-form textarea {
			width: 100%;
			max-width: 100%;
		}

		.consigne-right #bilan-ia-eleve {
			padding: 14px;
			border: 1px solid #d1d5db;
			border-radius: 10px;
			background: #f9fafb;
			max-height: 560px;
			overflow-y: auto;
			width: 100%;
		}

		.consigne-right h2 {
			margin-top: 0;
			font-size: 18px;
			color: #111827;
		}

		@media (max-width: 700px) {
			.consigne-layout {
				grid-template-columns: 1fr;
			}

			.consigne-right #bilan-ia-eleve {
				max-height: 420px;
			}
		}
	</style>
</head>

<body>
    <div class="panel">
        <div class="topbar">
            <a href="/modules/portail_prof/choix_declaration_ou_salle.php" class="back-btn">
                ⬅ Retour écran précédent
            </a>

            <div class="title-main">
                ENVOI DE CONSIGNE À L'IA
            </div>

            <div style="width:190px;"></div>
        </div>

        <div class="banner">
            👤 Espace de <?= h($PROF) ?>
        </div>

        <h1>ENVOI DE CONSIGNE À L'IA</h1>

        <div class="notice">
            Cette page permet d’envoyer une consigne à l’IA afin que les étudiants puissent pratiquer la langue à la maison.
        </div>

		<div id="msg" style="margin:14px 0; font-weight:600;"></div>

		<div class="consigne-layout">

			<div class="consigne-left">
				<form id="consigne-form" class="consigne-form">
					<label class="etape" for="cours">1) Sélectionner un cours</label><br>
					<select id="cours" name="id_cours" required>
						<option value="">-- Sélectionnez un cours --</option>
					</select>
					<br><br>

					<label class="etape" for="eleve">2) Sélectionner un élève</label><br>
					<select id="eleve" name="eleve" required>
						<option value="">-- Sélectionnez un élève --</option>
					</select>
					<br><br>

					<label class="etape" for="consigne">3) Saisir une consigne</label><br>
					<textarea
						id="consigne"
						name="consigne"
						rows="6"
						required
						placeholder="Ex. Travaille plus sur l'utilisation de 'could' et 'would'."
					></textarea>
					<br>

					<button type="submit" class="submit-btn">
						Envoyer la consigne
					</button>
				</form>
			</div>

			<div class="consigne-right">
				<div id="bilan-ia-eleve" style="display:none;">
					<h2>Dernières séances IA de l’élève</h2>
					<div id="bilan-ia-contenu">
						Sélectionnez un élève pour afficher son bilan IA.
					</div>
				</div>
			</div>

		</div>
    </div>
	
    <script src="./assets/js/consigne_ia_prof.js?v=7"></script>
</body>
</html>