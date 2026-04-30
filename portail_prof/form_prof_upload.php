<?php
// ============================================================================
// modules/portail_prof/form_prof_upload.php
// Envoi et consultation des justificatifs du mois
// - utilisé par les PROFESSEURS (workflow normal)
// - et par l'ADMIN (mode contrôle)
// ============================================================================

declare(strict_types=1);

// 1) Bootstrap portail prof
require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page();

// 2) Session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// On doit au minimum avoir un prof ou un admin connecté
if (empty($_SESSION['display']) && (empty($_SESSION['admin']) || !$_SESSION['admin'])) {
    http_response_code(403);
    exit('Accès refusé (session manquante)');
}

// Uploads profs — config centralisée
require_once $config['base_path'] . '/app/config/uploads_prof.php';

// --------------------------------------------------------------------------
// 3) Paramètres prof / mois / année
// --------------------------------------------------------------------------
$profParam  = isset($_GET['prof'])  ? trim((string)$_GET['prof'])  : '';
$anneeParam = isset($_GET['annee']) ? (int)$_GET['annee'] : (int)($_SESSION['annee'] ?? 0);
$moisParam  = isset($_GET['mois'])  ? (int)$_GET['mois']  : (int)($_SESSION['mois']  ?? 0);

// Admin ?
$isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'];

// Si pas admin : on force le prof de la session (on ignore les paramètres GET)
if (!$isAdmin) {
    $profDisplay = (string)($_SESSION['display'] ?? '');
    $profCode    = trim((string)($_SESSION['prof_code'] ?? ''));
} else {
    // Admin : on accepte prof/code en GET (mode contrôle)
    $profDisplay = $profParam !== '' ? $profParam : (string)($_SESSION['display'] ?? '');
    $profCode    = trim((string)($_GET['code'] ?? $_POST['code'] ?? ($_SESSION['prof_code'] ?? '')));
}

$annee = $anneeParam;
$mois  = $moisParam;

$profLabel     = strtoupper(trim($profDisplay)); // affichage / URL
$profDiskLabel = $profLabel;                     // sera corrigé après connexion SQL si possible

// Identifiant utilisé en base pour AudraWeb_Documents : CODE OBLIGATOIRE
$profDb = strtoupper(trim($profCode));
if ($profDb === '') {
    http_response_code(400);
    exit("Erreur : code prof manquant (prof_code). Reconnectez-vous.");
}

// --------------------------------------------------------------------------
// 4) Connexion SQL
// --------------------------------------------------------------------------
require_once $config['base_path'] . '/app/config/db_config.php';
$conn = db();
if (!$conn) {
    http_response_code(500);
    exit('Erreur connexion SQL');
}

// --------------------------------------------------------------------------
// 4a) Résolution du nom canonique du dossier à partir du code prof
//     Objectif : retrouver les anciens dossiers stockés en "PRÉNOM NOM"
// --------------------------------------------------------------------------
if ($profCode !== '') {
    $stFolder = sqlsrv_query(
        $conn,
        "SELECT TOP 1
            UPPER(LTRIM(RTRIM(CONCAT(Contact_FirstName,' ',Contact_Name)))) AS folder_label
         FROM dbo.Colleague
         WHERE Id = ?",
        [$profCode]
    );

    if ($stFolder && ($rf = sqlsrv_fetch_array($stFolder, SQLSRV_FETCH_ASSOC))) {
        $candidate = trim((string)($rf['folder_label'] ?? ''));
        if ($candidate !== '') {
            $profDiskLabel = $candidate;
        }
    }
    if ($stFolder) {
        sqlsrv_free_stmt($stFolder);
    }
}

// --------------------------------------------------------------------------
// 4b) Statut prof (TNS / salarié) — utilisé pour l’UX
// --------------------------------------------------------------------------
$isTNS = false;
$stStatut = sqlsrv_query(
    $conn,
    "SELECT TOP 1 xx_Statut_prof
       FROM dbo.Colleague
      WHERE Id = ?
         OR UPPER(LTRIM(RTRIM(CONCAT(Contact_FirstName,' ',Contact_Name)))) = UPPER(?)",
    [$profCode, $profDisplay]
);
if ($stStatut && ($rs = sqlsrv_fetch_array($stStatut, SQLSRV_FETCH_ASSOC))) {
    $stat = (string)($rs['xx_Statut_prof'] ?? '');
    $su = strtoupper(trim($stat));
    $isTNS = (strpos($su, 'TNS') !== false) || (strpos($su, 'HONORAIRE') !== false);
}
if ($stStatut) {
    sqlsrv_free_stmt($stStatut);
}

// --------------------------------------------------------------------------
// Helpers
// --------------------------------------------------------------------------
function audra_sanitize_filename(string $name): string {
    $name = basename($name);
    $name = str_replace(
        ['"', "'", '\\', '/', '?', '*', ':', '|', '<', '>'],
        '_',
        $name
    );
    return $name;
}

/**
 * Détecte le type de document (à partir du nom) pour appliquer les règles métier.
 * Retourne: 'PRESENCE' | 'FACTURE' | 'AUTRE'
 */
function audra_prof_doc_kind(string $safeName): string {
    $n = mb_strtolower($safeName, 'UTF-8');
    $n = preg_replace('/\.[^.]+$/u', '', $n);

    if (function_exists('transliterator_transliterate')) {
        $n = transliterator_transliterate('Any-Latin; Latin-ASCII', $n);
    } elseif (function_exists('iconv')) {
        $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $n);
        if ($tmp !== false) $n = $tmp;
    }

    $n = preg_replace('/[^a-z0-9]+/i', ' ', (string)$n);
    $n = trim((string)preg_replace('/\s+/', ' ', $n));

    if ($n === '') {
        return 'AUTRE';
    }

    $tokens = explode(' ', $n);

    // PRESENCE : on accepte plusieurs variantes naturelles
  $presenceVariants = [
    'presence', 'presences', 'presense', 'presance', 'prsence',
    'feuille presence', 'feuille de presence', 'feuilles de presence',
    'emargement', 'emargements',
    'feuille emargement', 'feuille d emargement',
    'feuille heures', 'heure', 'heures',
    'fp', 'fdp',
    'attendance'
];

    foreach ($presenceVariants as $v) {
        if ($v !== '' && strpos($n, $v) !== false) {
            return 'PRESENCE';
        }
    }

    // Cas supplémentaire : "feuille" + "presence" séparés dans le nom
    if (in_array('feuille', $tokens, true) && in_array('presence', $tokens, true)) {
        return 'PRESENCE';
    }

    // FACTURE : variantes naturelles
    $factureVariants = [
        'facture', 'factures', 'fact',
        'invoice', 'fattura',
        'honoraire', 'honoraires'
    ];

    foreach ($factureVariants as $v) {
        if ($v !== '' && strpos($n, $v) !== false) {
            return 'FACTURE';
        }
    }

    return 'AUTRE';
}

/**
 * Génère un nom de stockage unique (anti-écrasement / anti-verrouillage UNC).
 */
function audra_prof_unique_filename(string $kind, string $originalSafeName): string {
    $ext = strtolower((string)pathinfo($originalSafeName, PATHINFO_EXTENSION));
    $origBase = (string)pathinfo($originalSafeName, PATHINFO_FILENAME);

    $slug = mb_strtolower($origBase, 'UTF-8');

    if (function_exists('transliterator_transliterate')) {
        $slug = transliterator_transliterate('Any-Latin; Latin-ASCII', $slug);
    } elseif (function_exists('iconv')) {
        $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
        if ($tmp !== false) $slug = $tmp;
    }

    $slug = preg_replace('/[^a-z0-9]+/i', '_', (string)$slug);
    $slug = trim((string)$slug, '_');

    if ($slug === '') $slug = 'fichier';
    if (strlen($slug) > 40) $slug = substr($slug, 0, 40);

    $stamp = date('Ymd_His');
    $rand  = function_exists('random_bytes') ? bin2hex(random_bytes(4)) : substr(uniqid('', true), -8);

    $base = $kind . '__' . $slug . '__' . $stamp . '__' . $rand;
    return $ext !== '' ? ($base . '.' . $ext) : $base;
}

// Nom de dossier pour le prof (centralisé) : on utilise le libellé canonique disque
$targetDir = audra_prof_dir($profDiskLabel, $annee, $mois);

// --------------------------------------------------------------------------
// 5) Traitement upload / suppression
// --------------------------------------------------------------------------
$uploadMessage = null;
$uploadErrors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {

    $fileToDelete = basename((string)($_POST['delete_file'] ?? ''));
    $fullPath     = $targetDir . DIRECTORY_SEPARATOR . $fileToDelete;

    // 1) Suppression disque
    $diskDeleted = true;
    if (is_file($fullPath)) {
        $diskDeleted = @unlink($fullPath);
    }

    if (!$diskDeleted) {
        $uploadErrors[] = "Impossible de supprimer « $fileToDelete » (le fichier est peut-être ouvert). Fermez-le puis réessayez.";
    } else {
        // 2) Suppression trace en base
        $sqlDel = "
            DELETE FROM dbo.AudraWeb_Documents
            WHERE prof = ? AND annee = ? AND mois = ? AND filename = ?
        ";
        $delParams = [$profDb, $annee, $mois, $fileToDelete];

        $stDel = sqlsrv_query($conn, $sqlDel, $delParams);
        if ($stDel === false) {
            $err = sqlsrv_errors(SQLSRV_ERR_ERRORS);
            $msg = $err[0]['message'] ?? 'Erreur SQL inconnue';
            $uploadErrors[] = "Fichier supprimé, mais erreur SQL lors de la suppression de « $fileToDelete » : " . $msg;
        } else {
            sqlsrv_free_stmt($stDel);
            $uploadMessage = "🗑 Fichier « $fileToDelete » supprimé.";
        }
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['justifs'])) {

    $files     = $_FILES['justifs'];
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        $uploadMessage = "Impossible de créer le dossier de stockage.";
    } else {
        $successCount = 0;

        for ($i = 0; $i < $fileCount; $i++) {
            $name    = is_array($files['name'])     ? $files['name'][$i]     : $files['name'];
            $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $error   = is_array($files['error'])    ? $files['error'][$i]    : $files['error'];
            $size    = is_array($files['size'])     ? $files['size'][$i]     : $files['size'];

            if ($error === UPLOAD_ERR_NO_FILE) continue;
            if ($error !== UPLOAD_ERR_OK) {
                $uploadErrors[] = "Erreur upload pour $name (code=$error)";
                continue;
            }

            if ($size > 20 * 1024 * 1024) {
                $uploadErrors[] = "Fichier trop volumineux : $name (max 20 Mo)";
                continue;
            }

            $safeName = audra_sanitize_filename($name);

            // PRESENCE / FACTURE : nom unique
            $kind = audra_prof_doc_kind($safeName);
            if ($kind === 'PRESENCE' || $kind === 'FACTURE') {
                $safeName = audra_prof_unique_filename($kind, $safeName);
            }

            $destPath = $targetDir . DIRECTORY_SEPARATOR . $safeName;

            if (!move_uploaded_file($tmpName, $destPath)) {
                $uploadErrors[] = "Impossible de déplacer $name";
                continue;
            }

            $pathForDb = audra_prof_relpath($profDiskLabel, $annee, $mois, $safeName);

            // Vérifier existence en base
            $sqlCheck = "
                SELECT 1
                FROM dbo.AudraWeb_Documents
                WHERE prof = ? AND annee = ? AND mois = ? AND filename = ?
            ";
            $exists = false;
            $stCheck = sqlsrv_query($conn, $sqlCheck, [$profDb, $annee, $mois, $safeName]);
            if ($stCheck) {
                $exists = (sqlsrv_fetch_array($stCheck, SQLSRV_FETCH_ASSOC) !== null);
                sqlsrv_free_stmt($stCheck);
            }

            if ($exists) {
                $sqlUpdate = "
                    UPDATE dbo.AudraWeb_Documents
                    SET path = ?
                    WHERE prof = ? AND annee = ? AND mois = ? AND filename = ?
                ";
                $stUpd = sqlsrv_query($conn, $sqlUpdate, [$pathForDb, $profDb, $annee, $mois, $safeName]);
                if ($stUpd === false) {
                    $uploadErrors[] = "Erreur SQL lors update de $safeName";
                    continue;
                }
                sqlsrv_free_stmt($stUpd);
            } else {
                $sqlInsert = "
                    INSERT INTO dbo.AudraWeb_Documents (prof, annee, mois, filename, path)
                    VALUES (?, ?, ?, ?, ?)
                ";
                $stmtIns = sqlsrv_query($conn, $sqlInsert, [$profDb, $annee, $mois, $safeName, $pathForDb]);
                if ($stmtIns === false) {
                    $uploadErrors[] = "Erreur SQL insert $safeName";
                    continue;
                }
                sqlsrv_free_stmt($stmtIns);
            }

            $successCount++;
        }

        if ($successCount > 0 && empty($uploadErrors)) {
            $uploadMessage = "✅ $successCount fichier(s) enregistré(s) avec succès.";
        } elseif ($successCount > 0) {
            $uploadMessage = "⚠️ $successCount fichier(s) enregistré(s) mais avec des erreurs.";
        } else {
            $uploadMessage = "❌ Aucun fichier enregistré.";
        }
    }
}

// --------------------------------------------------------------------------
// 6) Lecture des fichiers existants directement depuis le dossier partagé
// --------------------------------------------------------------------------
$docs = [];
if (is_dir($targetDir)) {
    $scan = @scandir($targetDir);
    if ($scan !== false) {
        foreach ($scan as $f) {
            if ($f === '.' || $f === '..') continue;
            $path = $targetDir . DIRECTORY_SEPARATOR . $f;
            if (is_file($path)) {
                $docs[] = [
                    'filename' => (string)$f,
                    'mtime'    => (int)@filemtime($path),
                    'kind'     => audra_prof_doc_kind((string)$f),
                ];
            }
        }
    }
}

// Tri du plus récent au plus ancien
usort($docs, function ($a, $b) {
    return (($b['mtime'] ?? 0) <=> ($a['mtime'] ?? 0));
});

// --------------------------------------------------------------------------
// 6b) Groupes + "actifs"
// --------------------------------------------------------------------------
$presenceDocs = [];
$factureDocs  = [];
$otherDocs    = [];

foreach ($docs as $d) {
    $k = (string)($d['kind'] ?? 'AUTRE');
    if ($k === 'PRESENCE') {
        $presenceDocs[] = $d;
    } elseif ($k === 'FACTURE') {
        $factureDocs[] = $d;
    } else {
        $otherDocs[] = $d;
    }
}

$presenceActive = $presenceDocs[0] ?? null;
$factureActive  = $factureDocs[0] ?? null;

$hasPresenceUploaded = ($presenceActive !== null);
$hasFactureUploaded  = ($factureActive !== null);

// --------------------------------------------------------------------------
// 7) Affichage HTML
// --------------------------------------------------------------------------
$moisLabel = DateTime::createFromFormat('!m', sprintf('%02d', $mois))->format('F');
$moisLabelFr = strtr($moisLabel, [
    'January'   => 'Janvier',
    'February'  => 'Février',
    'March'     => 'Mars',
    'April'     => 'Avril',
    'May'       => 'Mai',
    'June'      => 'Juin',
    'July'      => 'Juillet',
    'August'    => 'Août',
    'September' => 'Septembre',
    'October'   => 'Octobre',
    'November'  => 'Novembre',
    'December'  => 'Décembre',
]);

$stepParam = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($stepParam < 1 || $stepParam > 4) $stepParam = 1;

$adminBackUrl = "../../admin_controle_saisie.php"
    . "?annee=" . urlencode((string)$annee)
    . "&mois=" . urlencode((string)$mois)
    . "&prof=" . urlencode($profLabel)
    . "&step=" . urlencode((string)$stepParam);

if ($profCode !== '') {
    $adminBackUrl .= "&code=" . urlencode($profCode);
}


?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Envoyer les justificatifs — <?php echo htmlspecialchars($profLabel); ?></title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f3f4f6;
            margin: 0;
            padding: 0;
        }
        .banner-admin {
            background: #fef3c7;
            color: #7c2d12;
            padding: 8px 16px;
            font-size: 14px;
        }
        .wrapper {
            max-width: 1120px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 6px;
            padding: 20px 24px 30px;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
        }
        h1 {
            font-size: 20px;
            margin-top: 0;
            margin-bottom: 12px;
        }
        .panel-consignes {
            background: #fffbeb;
            border: 1px solid #facc15;
            border-radius: 4px;
            padding: 12px 14px;
            margin-bottom: 16px;
        }
        .panel-consignes strong {
            display: block;
            margin-bottom: 6px;
        }
        ul {
            margin: 4px 0 4px 20px;
        }
        .section-title {
            font-size: 16px;
            margin-top: 20px;
            margin-bottom: 8px;
        }
        .message {
            margin-top: 10px;
            padding: 8px 10px;
            border-radius: 4px;
            font-size: 14px;
        }
        .message-ok {
            background: #dcfce7;
            border: 1px solid #16a34a;
            color: #14532d;
        }
        .message-warn {
            background: #fef9c3;
            border: 1px solid #eab308;
            color: #713f12;
        }
        .message-error {
            background: #fee2e2;
            border: 1px solid #ef4444;
            color: #7f1d1d;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 6px;
        }
        th, td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            font-size: 14px;
        }
		
		.docs-compact td,
.docs-compact th {
    font-size: 12px;
    padding: 5px 6px;
    vertical-align: top;
}

.docs-compact .btn {
    padding: 4px 8px;
    font-size: 12px;
}

.docs-compact .doc-card {
    padding: 4px 6px !important;
    margin: 0 !important;
}

.docs-compact .doc-meta {
    font-size: 11px !important;
    margin-top: 2px !important;
}

.docs-compact .type-badge {
    font-size: 11px !important;
    padding: 1px 6px !important;
}

.docs-compact .nowrap {
    white-space: nowrap;
}
		
		
		
		
        th {
            background: #f9fafb;
            text-align: left;
        }
        .empty {
            font-style: italic;
            color: #6b7280;
            margin-top: 4px;
        }
        .btn {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            border: 1px solid transparent;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-primary {
            background: #2563eb;
            border-color: #1d4ed8;
            color: #ffffff;
        }
        .btn-secondary {
            background: #6b7280;
            border-color: #4b5563;
            color: #ffffff;
        }
        input[type="file"] {
            margin-top: 5px;
            margin-bottom: 12px;
        }
        .actions-bottom {
            margin-top: 18px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn:disabled {
            opacity: .45;
            cursor: not-allowed;
        }
        .btn-disabled {
            opacity: .45;
            cursor: not-allowed;
            pointer-events: none;
        }
        .btn-primary:disabled,
        .btn-primary.btn-disabled {
            background: #94a3b8;
            border-color: #94a3b8;
            color: #ffffff;
        }
		
		.file-picker-box{
    margin-top:10px;
    padding:14px 16px;
    border:2px solid #d1d5db;
    border-radius:10px;
    background:#f9fafb;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.9), 0 1px 4px rgba(0,0,0,.06);
}

.file-picker-box label{
    display:block;
    font-weight:700;
    margin-bottom:8px;
}

.file-picker-help{
    font-size:14px;
    color:#374151;
    margin-bottom:10px;
    font-weight:600;
}

.file-picker-box input[type="file"]{
    display:inline-block;
    padding:10px 12px;
    border:1px solid #cbd5e1;
    border-radius:8px;
    background:#ffffff;
    box-shadow:0 2px 5px rgba(0,0,0,.08);
}
    </style>
</head>
<body>

<?php if ($isAdmin): ?>
<div class="banner-admin">
    🔍 Mode Contrôle Admin — Espace de <?php echo htmlspecialchars($profLabel); ?> — <?php echo $moisLabelFr . ' ' . $annee; ?>
</div>
<?php endif; ?>

<div class="wrapper">
    <h1>ÉCRAN D’ENVOI DES JUSTIFICATIFS</h1>
<p style="margin:0 0 14px 0; font-size:16px; color:#1d4ed8; font-weight:bold;">
  Envoyer les justificatifs du mois — <?php echo $moisLabelFr . ' ' . $annee; ?>
</p>

    <div class="panel-consignes">
        <strong>Important : Consignes à respecter :</strong>
        <p>Vous pouvez joindre tous les fichiers que vous voulez mais vous devez obligatoirement joindre :</p>
        <ul>
            <li><strong>Pour les salariés</strong> : le fichier nommé <code>PRESENCE</code> contenant vos feuilles de présence.</li>
            <li><strong>Pour les TNS</strong> : le fichier nommé <code>PRESENCE</code> et un fichier nommé <code>FACTURE</code>.</li>
        </ul>
        <p>
            ✅ Si vous renvoyez une nouvelle <code>PRESENCE</code> ou <code>FACTURE</code>, la dernière version devient automatiquement la version active.
            Les anciennes versions restent consultables dans “Historique”.
        </p>
        <p>
            ℹ️ Pour éviter les blocages, le système peut renommer automatiquement les fichiers <code>PRESENCE</code>/<code>FACTURE</code> après l’envoi.
        </p>
        <p>Formats acceptés : PDF, JPG, PNG, Word, Excel, ZIP/RAR — Max 20 Mo.</p>
    </div>

    <?php if ($uploadMessage !== null): ?>
        <?php $cls = !empty($uploadErrors) ? 'message-warn' : 'message-ok'; ?>
        <div class="message <?php echo $cls; ?>">
            <?php echo htmlspecialchars($uploadMessage); ?>
            <?php if (!empty($uploadErrors)): ?>
                <br><span style="font-size:13px;"><?php echo htmlspecialchars(implode(' | ', $uploadErrors)); ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <h2 style="font-size:18px; margin-top:18px; text-decoration: underline;">
        Instructions pour envoyer vos fichiers (feuilles de présence, factures, documents)
    </h2>

    <details style="margin-top:10px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; padding:10px 12px;">
        <summary style="cursor:pointer; font-weight:700; display:flex; align-items:center; gap:10px;">
            <span style="font-size:18px;">📌</span>
            <span>Lisez attentivement les instructions avant de cliquer sur Select</span>
        </summary>

        <div style="margin-top:10px; line-height:1.5;">
            <p><strong>Étape 1 — Téléverser vos fichiers</strong></p>
            <ol style="margin-top:6px;">
                <li>Cliquez sur <strong>“Sélect fichiers" en bas de la page</strong>.</li>
                <li>Attendez que les fichiers apparaissent juste en dessous.</li>
                <li>Cliquez sur <strong>“Uploader”</strong> uniquement quand le bouton devient <strong>bleu</strong>.</li>
            </ol>

            <p style="margin-top:12px;"><strong>Étape 2 — Vérifier et confirmer</strong></p>
            <p>
                Après l’upload, cliquez sur <strong>“Continuer pour vérifier vos justificatifs →”</strong> puis confirmez (bouton bleu en bas).
            </p>

            <hr style="margin:14px 0; border:none; border-top:1px solid #e5e7eb;">

            <p><strong>Règles importantes</strong></p>
            <ul>
                <li>✅ <strong>Vous pouvez envoyer plusieurs fichiers de présence</strong> (un par cours si besoin).</li>
                <li>✅ <strong>Mettez le mot <code>presence</code> dans le nom</strong> (ex : <code>presence_20192.jpg</code>, <code>presence20192.pdf</code>).</li>
                <li>✅ <strong>Le système peut renommer automatiquement</strong> : c’est normal (anti-écrasement + historique).</li>
                <li>✅ <strong>La dernière version devient “ACTIVE”</strong>, les anciennes restent en “Historique”.</li>
                <li>📷 Photos acceptées : JPG/JPEG/PNG (photo du téléphone).</li>
            </ul>
        </div>
    </details>

<?php if (empty($docs)): ?>
    <p class="empty">Aucun fichier pour ce mois.</p>
<?php else: ?>

    <?php
    $fmtMtime = function ($t) {
        $t = (int)$t;
        return $t > 0 ? date('d/m/Y H:i', $t) : '';
    };

    $presenceHistory = array_slice($presenceDocs, 1);
    $factureHistory  = array_slice($factureDocs, 1);

    $docUrl = function (string $fn) use ($profDiskLabel, $annee, $mois) {
        return '/audra_portail_prod/actions/get_doc_prof.php?prof=' . urlencode($profDiskLabel)
            . '&annee=' . (int)$annee
            . '&mois=' . (int)$mois
            . '&file=' . urlencode($fn);
    };
    ?>

    <h2 class="section-title">Documents requis — dernières versions</h2>
    <table class="docs-compact">
        <thead>
<tr>
    <th>Type</th>
    <th>Fichier</th>
    <th>Modifié le</th>
</tr>
</thead>
        <tbody>

        <tr>
    <td>
        <tr>
    <td>
        <strong>PRESENCE</strong>
        <?php if (!empty($presenceDocs)): ?>
            <span style="margin-left:8px;font-size:12px;padding:2px 8px;border-radius:999px;background:#dcfce7;border:1px solid #16a34a;color:#14532d;">
                <?= count($presenceDocs) ?> fichier(s)
            </span>
        <?php endif; ?>
    </td>
    <td>
        <?php if (!empty($presenceDocs)): ?>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php foreach ($presenceDocs as $idx => $doc): ?>
                    <?php $filename = (string)$doc['filename']; ?>
                    <div style="padding:8px 10px;border:1px solid #e5e7eb;border-radius:6px;background:#f9fafb;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                            <div style="min-width:0;flex:1;">
                                <div style="font-weight:600;word-break:break-word;">
                                    <?= htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($idx === 0): ?>
                                        <span style="margin-left:8px;font-size:12px;padding:2px 8px;border-radius:999px;background:#dcfce7;border:1px solid #16a34a;color:#14532d;">référence affichée</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:12px;color:#64748b;margin-top:4px;">
                                    Modifié le <?= htmlspecialchars($fmtMtime($doc['mtime'] ?? 0), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>

                            <div style="display:flex;gap:6px;align-items:center;flex-shrink:0;">
                                <a class="btn btn-secondary"
                                   href="<?= htmlspecialchars($docUrl($filename), ENT_QUOTES, 'UTF-8') ?>"
                                   target="_blank">Voir</a>

                                <form method="post"
                                      action="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>"
                                      style="display:inline"
                                      onsubmit="return confirm('Supprimer ce fichier PRESENCE ?');">
                                    <input type="hidden" name="annee" value="<?= (int)$annee; ?>">
                                    <input type="hidden" name="mois"  value="<?= (int)$mois; ?>">
                                    <input type="hidden" name="code"  value="<?= htmlspecialchars($profCode, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="delete_file" value="<?= htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" class="btn btn-secondary">🗑</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <span style="color:#b91c1c;font-weight:600;">MANQUANTE</span>
        <?php endif; ?>
    </td>
    <td>
    <?php if (!empty($presenceDocs)): ?>
        <?= htmlspecialchars($fmtMtime($presenceDocs[0]['mtime'] ?? 0), ENT_QUOTES, 'UTF-8') ?>
    <?php endif; ?>
</td>
</tr>

        <tr>
            <td>
                <strong>FACTURE</strong>
                <?php if ($factureActive): ?>
                    <span style="margin-left:8px;font-size:12px;padding:2px 8px;border-radius:999px;background:#dcfce7;border:1px solid #16a34a;color:#14532d;">ACTIVE</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($factureActive): ?>
                    <div style="padding:8px 10px;border:1px solid #e5e7eb;border-radius:6px;background:#f9fafb;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                            <div style="min-width:0;flex:1;">
                                <div style="font-weight:600;word-break:break-word;">
                                    <?= htmlspecialchars((string)$factureActive['filename'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div style="font-size:12px;color:#64748b;margin-top:4px;">
                                    Modifié le <?= htmlspecialchars($fmtMtime($factureActive['mtime'] ?? 0), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>

                            <div style="display:flex;gap:6px;align-items:center;flex-shrink:0;">
                                <a class="btn btn-secondary"
                                   href="<?= htmlspecialchars($docUrl((string)$factureActive['filename']), ENT_QUOTES, 'UTF-8') ?>"
                                   target="_blank">Voir</a>

                                <form method="post"
                                      action="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>"
                                      style="display:inline"
                                      onsubmit="return confirm('Supprimer la FACTURE active ?');">
                                    <input type="hidden" name="annee" value="<?= (int)$annee; ?>">
                                    <input type="hidden" name="mois"  value="<?= (int)$mois; ?>">
                                    <input type="hidden" name="code"  value="<?= htmlspecialchars($profCode, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="delete_file" value="<?= htmlspecialchars((string)$factureActive['filename'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" class="btn btn-secondary">🗑</button>
                                </form>
                            </div>
                        </div>
                    </div>
                  <?php else: ?>
                    <?php if ($isTNS): ?>
                        <span style="color:#b91c1c;font-weight:600;">MANQUANTE (TNS)</span>
                    <?php else: ?>
                        <span style="color:#065f46;">Non requise (salarié)</span>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($factureActive): ?>
                    <?= htmlspecialchars($fmtMtime($factureActive['mtime'] ?? 0), ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </td>
        </tr>

        </tbody>
    </table>

    <h2 class="section-title">Autres documents</h2>
    <p style="color:#6b7280;font-size:13px;margin:4px 0 10px;">
        Autres documents facultatifs (ex : URSSAF, CV, etc.).
    </p>

    <?php if (empty($otherDocs)): ?>
        <p class="empty">Aucun autre document.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Nom du fichier</th>
                <th>Modifié le</th>
                <th>Ouvrir</th>
                <th>Supprimer</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($otherDocs as $doc): ?>
                <?php $filename = (string)$doc['filename']; ?>
                <tr>
                    <td><?= htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($fmtMtime($doc['mtime'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <a class="btn btn-secondary" href="<?= htmlspecialchars($docUrl($filename), ENT_QUOTES, 'UTF-8') ?>" target="_blank">Voir</a>
                    </td>
                    <td>
                        <form method="post"
                              action="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>"
                              style="display:inline"
                              onsubmit="return confirm('Supprimer ce fichier ?');">
                            <input type="hidden" name="annee" value="<?= (int)$annee; ?>">
                            <input type="hidden" name="mois"  value="<?= (int)$mois; ?>">
                            <input type="hidden" name="code"  value="<?= htmlspecialchars($profCode, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="delete_file" value="<?= htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-secondary">🗑</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (!empty($presenceHistory) || !empty($factureHistory)): ?>
        <details style="margin-top:14px;">
            <summary style="cursor:pointer; font-weight:600;">
                Afficher l’historique (<?= count($presenceHistory) ?> ancienne(s) PRESENCE, <?= count($factureHistory) ?> ancienne(s) FACTURE)
            </summary>

            <table style="margin-top:10px;">
                <thead>
                <tr>
                    <th>Type</th>
                    <th>Nom du fichier</th>
                    <th>Modifié le</th>
                    <th>Ouvrir</th>
                    <th>Supprimer</th>
                </tr>
                </thead>
                <tbody>

                <?php foreach ($presenceHistory as $doc): ?>
                    <?php $filename = (string)$doc['filename']; ?>
                    <tr>
                        <td>PRESENCE</td>
                        <td><?= htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($fmtMtime($doc['mtime'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><a class="btn btn-secondary" href="<?= htmlspecialchars($docUrl($filename), ENT_QUOTES, 'UTF-8') ?>" target="_blank">Voir</a></td>
                        <td>
                            <form method="post"
                                  action="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>"
                                  style="display:inline"
                                  onsubmit="return confirm('Supprimer ce fichier ?');">
                                <input type="hidden" name="annee" value="<?= (int)$annee; ?>">
                                <input type="hidden" name="mois"  value="<?= (int)$mois; ?>">
                                <input type="hidden" name="code"  value="<?= htmlspecialchars($profCode, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="delete_file" value="<?= htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="btn btn-secondary">🗑</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php foreach ($factureHistory as $doc): ?>
                    <?php $filename = (string)$doc['filename']; ?>
                    <tr>
                        <td>FACTURE</td>
                        <td><?= htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($fmtMtime($doc['mtime'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><a class="btn btn-secondary" href="<?= htmlspecialchars($docUrl($filename), ENT_QUOTES, 'UTF-8') ?>" target="_blank">Voir</a></td>
                        <td>
                            <form method="post"
                                  action="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>"
                                  style="display:inline"
                                  onsubmit="return confirm('Supprimer ce fichier ?');">
                                <input type="hidden" name="annee" value="<?= (int)$annee; ?>">
                                <input type="hidden" name="mois"  value="<?= (int)$mois; ?>">
                                <input type="hidden" name="code"  value="<?= htmlspecialchars($profCode, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="delete_file" value="<?= htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="btn btn-secondary">🗑</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>
        </details>
    <?php endif; ?>

<?php endif; ?>

    <h2 class="section-title">Ajouter de nouveaux fichiers :</h2>

    <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>" method="post" enctype="multipart/form-data">
        <input type="hidden" name="annee" value="<?php echo (int)$annee; ?>">
        <input type="hidden" name="mois"  value="<?php echo (int)$mois; ?>">

        <div class="file-picker-box">
    <label for="justifs">Sélectionner un ou plusieurs fichiers :</label>
    <div class="file-picker-help">Clique ici pour choisir tes fichiers de présence, facture ou autres documents.</div>
    <input type="file" name="justifs[]" id="justifs" multiple>
</div>

        <div id="filesList" class="empty" style="margin-top:-6px;">
            Aucun fichier sélectionné.
        </div>

        <div id="uploadHint" class="message message-warn" style="display:none; margin-top:10px;"></div>

        <button type="submit" id="btnUpload" class="btn btn-primary" disabled title="Sélectionnez d'abord vos fichiers">
            Uploader
        </button>
    </form>

    <div class="actions-bottom">
        <?php if ($isAdmin): ?>
            <a href="<?= htmlspecialchars($adminBackUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary">
                ⬅ Retour contrôle
            </a>
        <?php else: ?>
            <a href="form_prof_recap.php?prof=<?= urlencode($profDisplay) ?>&code=<?= urlencode($profCode) ?>&annee=<?= (int)$annee ?>&mois=<?= (int)$mois ?>"
               class="btn btn-secondary">
                ⬅ Retour contrôle
            </a>
        <?php endif; ?>

        <?php if (!$isAdmin): ?>
            <a id="btnCheck"
               href="form_prof_upload_check.php?prof=<?= urlencode($profLabel) ?>&code=<?= urlencode($profCode) ?>&annee=<?= (int)$annee ?>&mois=<?= (int)$mois ?>"
               class="btn btn-primary btn-disabled"
               title="Vous devez d'abord uploader vos fichiers.">
                Passez à l'étape suivante   →
            </a>
        <?php endif; ?>
    </div>

</div>

<script>
(function () {
  const input    = document.getElementById('justifs');
  const btnUp    = document.getElementById('btnUpload');
  const btnCheck = document.getElementById('btnCheck');
  const list     = document.getElementById('filesList');
  const hint     = document.getElementById('uploadHint');

  if (!input || !btnUp) return;

  const isTNS = <?= json_encode($isTNS ?? false) ?>;
  const hasUploadedDocs = <?= json_encode($hasPresenceUploaded && (!$isTNS || $hasFactureUploaded)) ?>;

  function normFilename(s) {
    let n = (s || '').toString().toLowerCase();
    if (typeof n.normalize === 'function') {
      n = n.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    return n;
  }

  function tokensFromName(name){
    let n = normFilename(name);
    n = n.replace(/\.[^.]+$/, '');
    n = n.replace(/[^a-z0-9]+/g, ' ').trim().replace(/\s+/g, ' ');
    return n ? n.split(' ') : [];
  }

  function isPresenceFile(name) {
  const n = normFilename(name).replace(/\.[^.]+$/, '');
  const t = tokensFromName(name);

  const variants = [
  'presence', 'presences', 'presense', 'presance', 'prsence',
  'feuille presence', 'feuille de presence', 'feuilles de presence',
  'emargement', 'emargements',
  'feuille emargement', 'feuille d emargement',
  'feuille heures',
  'fp', 'fdp',
  'attendance'
];

  if (variants.some(v => n.includes(v))) return true;

  if (t.includes('feuille') && t.includes('presence')) return true;

  return false;
}

  function isFactureFile(name) {
    const n = normFilename(name).replace(/\.[^.]+$/, '');
    const t = tokensFromName(name);
    const variants = ['facture','factures','fact','invoice','fattura','honoraire','honoraires'];
    if (t.some(x => variants.includes(x))) return true;
    if (variants.some(v => n.includes(v))) return true;
    return false;
  }

  function setHint(text, ok = false) {
    if (!hint) return;
    hint.textContent = text || '';
    hint.className = 'message ' + (ok ? 'message-ok' : 'message-warn');
    hint.style.display = text ? 'block' : 'none';
  }

  function renderList(files) {
    if (!list) return;
    const n = files ? files.length : 0;
    if (n === 0) {
      list.innerHTML = '<em>Aucun fichier sélectionné.</em>';
      return;
    }
    const items = Array.from(files).map(f => {
      const kb = Math.round((f.size || 0) / 1024);
      return `<li>${f.name} <span style="opacity:.65">(${kb} Ko)</span></li>`;
    }).join('');
    list.innerHTML = `<ul style="margin:6px 0 0 18px;">${items}</ul>`;
  }

  btnUp.disabled = true;
  btnUp.title = "Sélectionnez d'abord vos fichiers";
  renderList(input.files);

  if (btnCheck) {
    if (hasUploadedDocs) {
      btnCheck.classList.remove('btn-disabled');
      btnCheck.title = "";
    } else {
      btnCheck.classList.add('btn-disabled');
      btnCheck.title = "Vous devez d'abord uploader vos fichiers.";
    }
  }

  if (hasUploadedDocs) {
    setHint("✅ Documents requis déposés. Vous pouvez cliquer sur « Vérifier mes justificatifs et confirmer ».", true);
  } else {
    setHint(
      isTNS
        ? "Sélectionnez vos fichiers. En tant que TNS, vous devez fournir un fichier PRESENCE et un fichier FACTURE (soit déjà envoyés, soit dans la sélection)."
        : "Sélectionnez vos fichiers. Le bouton Uploader s’activera dès qu’ils seront listés."
    );
  }

  input.addEventListener('change', () => {
    renderList(input.files);

    const n = input.files ? input.files.length : 0;

    if (n === 0) {
      btnUp.disabled = true;
      btnUp.title = "Aucun fichier sélectionné";
      if (hasUploadedDocs) {
        setHint("✅ Documents requis déjà déposés. Vous pouvez cliquer sur « Vérifier mes justificatifs et confirmer ».", true);
      } else {
        setHint("Aucun fichier sélectionné.");
      }

      if (btnCheck) {
        if (hasUploadedDocs) {
          btnCheck.classList.remove('btn-disabled');
          btnCheck.title = "";
        } else {
          btnCheck.classList.add('btn-disabled');
          btnCheck.title = "Vous devez d'abord uploader vos fichiers.";
        }
      }
      return;
    }

    if (isTNS) {
      const hasPresenceUploaded = <?= json_encode($hasPresenceUploaded) ?>;
      const hasFactureUploaded  = <?= json_encode($hasFactureUploaded) ?>;

      const selectedFiles    = Array.from(input.files || []);
      const selectedPresence = selectedFiles.some(f => isPresenceFile(f.name));
      const selectedFacture  = selectedFiles.some(f => isFactureFile(f.name));

      const willHavePresence = hasPresenceUploaded || selectedPresence;
      const willHaveFacture  = hasFactureUploaded  || selectedFacture;

      if (!willHavePresence || !willHaveFacture) {
        btnUp.disabled = true;
        btnUp.title = "PRESENCE + FACTURE requis (TNS)";
        setHint("⚠️ En tant que TNS, vous devez avoir un fichier PRESENCE et un fichier FACTURE (soit déjà envoyés, soit dans la sélection).");
        return;
      }
    }

    btnUp.disabled = false;
    btnUp.title = "";
    setHint(`✅ ${n} fichier(s) prêt(s) à être envoyé(s). Cliquez sur “Uploader”.`, true);

    if (btnCheck) {
      btnCheck.classList.add('btn-disabled');
      btnCheck.title = "Cliquez d'abord sur Uploader, puis vous pourrez vérifier et confirmer.";
    }
  });

  function validateSelectionForUpload() {
    const n = input.files ? input.files.length : 0;

    if (n === 0) {
      return { ok: false, msg: "⚠️ Aucun fichier sélectionné." };
    }

    if (isTNS) {
      const hasPresenceUploaded = <?= json_encode($hasPresenceUploaded) ?>;
      const hasFactureUploaded  = <?= json_encode($hasFactureUploaded) ?>;

      const selectedFiles    = Array.from(input.files || []);
      const selectedPresence = selectedFiles.some(f => isPresenceFile(f.name));
      const selectedFacture  = selectedFiles.some(f => isFactureFile(f.name));

      const willHavePresence = hasPresenceUploaded || selectedPresence;
      const willHaveFacture  = hasFactureUploaded  || selectedFacture;

      if (!willHavePresence || !willHaveFacture) {
        const missing = [];
        if (!willHavePresence) missing.push('PRESENCE');
        if (!willHaveFacture)  missing.push('FACTURE');
        return {
          ok: false,
          msg: "⚠️ Upload impossible : il manque " + missing.join(' et ') + " (TNS)."
        };
      }
    }

    return { ok: true, msg: "" };
  }

  btnUp.addEventListener('click', (e) => {
    const v = validateSelectionForUpload();
    if (!v.ok) {
      e.preventDefault();
      e.stopPropagation();
      setHint(v.msg);
      return false;
    }
  });

  const form = btnUp.closest('form');
  if (form) {
    form.addEventListener('submit', (e) => {
      const v = validateSelectionForUpload();
      if (!v.ok) {
        e.preventDefault();
        setHint(v.msg);
        return;
      }

      btnUp.disabled = true;
      if (btnCheck) btnCheck.classList.add('btn-disabled');
      setHint("⏳ Upload en cours… merci de patienter.", true);
    });
  }
})();
</script>

<script>
// ============================================================================
// PATCH UX — sélection cumulée des fichiers
// ============================================================================
(function(){
  const input = document.getElementById('justifs');
  if (!input) return;

  input.multiple = true;

  let dt = new DataTransfer();
  let isReplaying = false;

  function keyOf(f){
    return [f.name, f.size, f.lastModified].join('|');
  }

  input.addEventListener('change', function(){
    if (isReplaying) return;

    const incoming = Array.from(input.files || []);
    const existing = new Set(Array.from(dt.files).map(keyOf));

    incoming.forEach(f => {
      const k = keyOf(f);
      if (!existing.has(k)){
        dt.items.add(f);
        existing.add(k);
      }
    });

    input.files = dt.files;

    let hint = document.getElementById('audra_hint_files');
    if (!hint){
      hint = document.createElement('div');
      hint.id = 'audra_hint_files';
      hint.style.marginTop = '6px';
      hint.style.fontSize = '12px';
      hint.style.opacity = '0.85';
      input.insertAdjacentElement('afterend', hint);
    }
    hint.textContent = 'Sélection actuelle : ' + dt.files.length + ' fichier(s). Vous pouvez en ajouter un autre (la sélection s’additionne).';

    isReplaying = true;
    setTimeout(() => {
      input.dispatchEvent(new Event('change', { bubbles: true }));
      isReplaying = false;
    }, 0);

  }, true);
})();
</script>

</body>
</html>