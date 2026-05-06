<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/security/firewall.php';
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

// Module planning salles : indépendant de l’ouverture mensuelle du portail prof.
// La session prof est déjà contrôlée juste au-dessus.
// On n’appelle donc pas audra_guard_prof_page() ici.

$PROF   = strtoupper(trim((string)($_SESSION['display'] ?? '')));
$prenom = (string)($_SESSION['firstname'] ?? '');
$nom    = (string)($_SESSION['lastname'] ?? '');

$profId = (isset($_SESSION['prof_code']) && $_SESSION['prof_code'] !== '')
    ? strtoupper(trim((string)$_SESSION['prof_code']))
    : '';

$tz = new DateTimeZone('Europe/Paris');
$today = new DateTimeImmutable('now', $tz);

/*
|--------------------------------------------------------------------------
| Règle métier planning salles
|--------------------------------------------------------------------------
| Le prof réserve les salles pour la semaine suivante.
| Il peut revenir autant de fois qu'il veut jusqu'au vendredi 15h00.
| Après vendredi 15h00, la saisie est fermée automatiquement.
|--------------------------------------------------------------------------
*/

$mondayThisWeek = $today->modify('monday this week');
$fridayThisWeek = $mondayThisWeek->modify('+4 days');
$deadline = $fridayThisWeek->setTime(15, 0, 0);

$mondayNextWeek = $today->modify('next monday');
$fridayNextWeek = $mondayNextWeek->modify('+4 days');

$saisieFermee = ($today >= $deadline);

$semaineLundiSql = $mondayNextWeek->format('Y-m-d');
$semaineVendrediSql = $fridayNextWeek->format('Y-m-d');

$semaineLundiFr = $mondayNextWeek->format('d/m/Y');
$semaineVendrediFr = $fridayNextWeek->format('d/m/Y');

$messageCloture = '';

if ($saisieFermee) {
    $messageCloture =
        "🔒 Les réservations de salles pour la semaine du "
        . $semaineLundiFr
        . " au "
        . $semaineVendrediFr
        . " sont clôturées depuis le vendredi à 15h00. "
        . "Aucune modification n’est désormais possible depuis le portail. "
        . "Pour toute demande tardive, merci de contacter le bureau.";
}

$planningExistant = false;
$planningId = 0;
$planningStatut = '';
$lignesPlanningJs = [];

/*
|--------------------------------------------------------------------------
| Chargement du planning déjà enregistré
|--------------------------------------------------------------------------
| On charge le dernier planning du prof pour cette semaine, qu'il soit
| BROUILLON ou VALIDE. Désormais, VALIDE ne veut plus dire "bloqué".
| Le vrai verrou est uniquement vendredi 15h00.
|--------------------------------------------------------------------------
*/

if ($profId !== '') {
    $sqlCheckPlanning = "
        SELECT TOP 1
            P.id,
            P.statut
        FROM dbo.AudraWeb_Planning_Salles P
        WHERE P.prof_code = ?
          AND P.semaine_lundi = ?
          AND UPPER(LTRIM(RTRIM(P.statut))) IN ('BROUILLON', 'VALIDE')
          AND EXISTS (
              SELECT 1
              FROM dbo.AudraWeb_Planning_Salles_Lignes L
              WHERE L.planning_id = P.id
          )
        ORDER BY
            CASE
                WHEN UPPER(LTRIM(RTRIM(P.statut))) = 'VALIDE' THEN 0
                ELSE 1
            END,
            P.id DESC
    ";

    $stmtCheckPlanning = sqlsrv_query($conn, $sqlCheckPlanning, [$profId, $semaineLundiSql]);

    if ($stmtCheckPlanning && ($rowP = sqlsrv_fetch_array($stmtCheckPlanning, SQLSRV_FETCH_ASSOC))) {
        $planningExistant = true;
        $planningId = (int)($rowP['id'] ?? 0);
        $planningStatut = strtoupper(trim((string)($rowP['statut'] ?? '')));
    }

    if ($stmtCheckPlanning) {
        sqlsrv_free_stmt($stmtCheckPlanning);
    }

    if ($planningExistant && $planningId > 0) {
        $sqlLignesPlanning = "
            SELECT
                id,
                id_cours,
                eleve,
                date_cours,
                heure_debut,
                heure_fin,
                duree
            FROM dbo.AudraWeb_Planning_Salles_Lignes
            WHERE planning_id = ?
            ORDER BY date_cours ASC, heure_debut ASC, id ASC
        ";

        $stmtLignesPlanning = sqlsrv_query($conn, $sqlLignesPlanning, [$planningId]);

        if ($stmtLignesPlanning) {
            while ($rowL = sqlsrv_fetch_array($stmtLignesPlanning, SQLSRV_FETCH_ASSOC)) {
                $dateCours = '';

                if (isset($rowL['date_cours'])) {
                    if ($rowL['date_cours'] instanceof DateTimeInterface) {
                        $dateCours = $rowL['date_cours']->format('d/m/Y');
                    } elseif (is_string($rowL['date_cours']) && trim($rowL['date_cours']) !== '') {
                        $tmp = date_create((string)$rowL['date_cours']);
                        $dateCours = $tmp ? $tmp->format('d/m/Y') : (string)$rowL['date_cours'];
                    }
                }

                $heureDebut = '';

                if (isset($rowL['heure_debut'])) {
                    if ($rowL['heure_debut'] instanceof DateTimeInterface) {
                        $heureDebut = $rowL['heure_debut']->format('H:i');
                    } else {
                        $heureDebut = substr((string)$rowL['heure_debut'], 0, 5);
                    }
                }

                $heureFin = '';

                if (isset($rowL['heure_fin'])) {
                    if ($rowL['heure_fin'] instanceof DateTimeInterface) {
                        $heureFin = $rowL['heure_fin']->format('H:i');
                    } else {
                        $heureFin = substr((string)$rowL['heure_fin'], 0, 5);
                    }
                }

                $lignesPlanningJs[] = [
                    'id'       => (int)($rowL['id'] ?? 0),
                    'id_cours' => (int)($rowL['id_cours'] ?? 0),
                    'eleve'    => (string)($rowL['eleve'] ?? ''),
                    'date'     => $dateCours,
                    'debut'    => $heureDebut,
                    'fin'      => $heureFin,
                    'duree'    => (float)($rowL['duree'] ?? 0),
                    'etat'     => 'lock',
                ];
            }

            sqlsrv_free_stmt($stmtLignesPlanning);
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Réservation de salle</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{
    font-family:sans-serif;
    background:#f9fafb;
    margin:2rem;
  }
  .panel{
    background:#fff;
    padding:1.5rem;
    margin:auto;
    border-radius:10px;
    box-shadow:0 2px 6px rgba(0,0,0,.1);
    max-width:1000px;
  }
  .topbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:12px;
  }
  .back-btn{
    display:inline-block;
    padding:8px 14px;
    border-radius:8px;
    background:#6b7280;
    color:#fff;
    text-decoration:none;
    font-weight:bold;
    box-shadow:0 2px 6px rgba(0,0,0,.12);
  }
  .title-main{
    flex:1;
    text-align:center;
    font-size:24px;
    font-weight:800;
    color:#16a34a;
    letter-spacing:.3px;
  }
  .prof-banner{
    padding:.7rem 1rem;
    border-radius:6px;
    margin:1rem 0;
    font-weight:bold;
    font-size:16px;
    background:#dcfce7;
    color:#065f46;
  }
  h1{
    margin-top:0;
    font-size:22px;
    color:#16a34a;
  }
  .subline{
    margin:0 0 14px 0;
    font-size:18px;
    color:#111827;
    font-weight:bold;
  }
  .notice{
    margin:12px 0 18px 0;
    padding:12px 14px;
    background:#f0fdf4;
    border:1px solid #bbf7d0;
    border-radius:8px;
    color:#14532d;
    line-height:1.5;
    font-size:14px;
  }
  select,
  input{
    padding:.4rem;
    border-radius:6px;
    border:1px solid #ccc;
  }
  #cours{
    min-width:320px;
    max-width:100%;
  }
  button:disabled{
    opacity:.6;
    cursor:not-allowed !important;
  }
</style>
</head>
<body>

<div class="panel">

  <div class="topbar">
    <a href="/modules/portail_prof/choix_declaration_ou_salle.php" class="back-btn">⬅ Retour écran précédent</a>

    <div class="title-main">
      RÉSERVATION DE SALLE
    </div>

    <div style="width:190px;"></div>
  </div>

  <div class="prof-banner">
    👤 Espace de <?= htmlspecialchars($PROF) ?>
  </div>

  <h1>RÉSERVATION DE SALLE</h1>
  <p class="subline">Semaine du <?= htmlspecialchars($semaineLundiFr) ?> au <?= htmlspecialchars($semaineVendrediFr) ?></p>

  <div class="notice" style="background:#fef2f2; border:2px solid #dc2626; color:#991b1b; font-size:16px; font-weight:bold;">
    ⚠️ IMPORTANT — CET ÉCRAN EST EXCLUSIVEMENT RÉSERVÉ À LA RÉSERVATION DES SALLES À L'ÉCOLE.<br><br>
    Vous devez saisir ici uniquement les cours de la semaine prochaine qui ont lieu <u>à l’école / dans les locaux Audra</u> et qui nécessitent une salle.<br><br>
    ❌ Ne pas saisir les cours en entreprise.<br>
    ❌ Ne pas saisir les visios faites hors des locaux Audra.<br>
    ✅ Une visio réalisée depuis une salle Audra doit être saisie.
  </div>

  <?php if ($saisieFermee): ?>
    <div style="margin:12px 0 18px 0; padding:12px 14px; background:#fff7ed; border:2px solid #ea580c; border-radius:8px; color:#9a3412; line-height:1.5; font-size:15px; font-weight:bold;">
      <?= htmlspecialchars($messageCloture) ?>
    </div>
  <?php else: ?>
    <div style="margin:12px 0 18px 0; padding:12px 14px; background:#eff6ff; border:2px solid #2563eb; border-radius:8px; color:#1e3a8a; line-height:1.5; font-size:15px; font-weight:bold;">
      ✅ Vous pouvez saisir, corriger, ajouter ou supprimer vos réservations de salles pour cette semaine.<br>
      Vous pouvez revenir autant de fois que nécessaire jusqu’au <u>vendredi à 15h00</u>.<br>
      Après ce délai, la saisie sera automatiquement fermée pour la semaine du <?= htmlspecialchars($semaineLundiFr) ?> au <?= htmlspecialchars($semaineVendrediFr) ?>.
    </div>
  <?php endif; ?>

  <?php if ($planningExistant): ?>
    <div style="margin:12px 0 18px 0; padding:12px 14px; background:#fffbeb; border:2px solid #f59e0b; border-radius:8px; color:#92400e; line-height:1.5; font-size:15px; font-weight:bold;">
      🟡 Un planning déjà enregistré a été retrouvé pour cette semaine.<br>
      <?php if (!$saisieFermee): ?>
        Vous pouvez le compléter, le corriger ou supprimer des lignes, puis cliquer sur <b>💾 Enregistrer ma réservation de salle</b>.
      <?php else: ?>
        Il est affiché ci-dessous en lecture seule.
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!$saisieFermee): ?>
    <p style="margin:0 0 14px 0; font-size:16px; color:#16a34a; font-weight:bold;">1) Sélectionner un cours</p>
    <select id="cours">
      <option value="">— Sélectionnez un cours —</option>
    </select>
  <?php endif; ?>

  <div id="coursZone" style="display:block; margin-top:18px;">
    <?php if (!$saisieFermee): ?>
      <p style="margin:18px 0 10px 0; font-weight:bold; color:#2563eb;">
        2) Saisir une réservation
      </p>

      <h2 id="titreCours" style="margin:0 0 12px 0; font-size:20px; color:#16a34a;"></h2>

      <div id="blocSaisie" style="padding:12px; border:1px solid #d1d5db; border-radius:8px; background:#f9fafb; margin-bottom:18px;">
        <table id="tabSaisie" style="width:100%; border-collapse:collapse; margin-top:10px;">
          <thead>
            <tr>
              <th style="border:1px solid #ddd; padding:8px; background:#f3f4f6;">N° cours</th>
              <th style="border:1px solid #ddd; padding:8px; background:#f3f4f6;">Élève</th>
              <th style="border:1px solid #ddd; padding:8px; background:#f3f4f6;">Date</th>
              <th style="border:1px solid #ddd; padding:8px; background:#f3f4f6;">Heure début</th>
              <th style="border:1px solid #ddd; padding:8px; background:#f3f4f6;">Heure fin</th>
              <th style="border:1px solid #ddd; padding:8px; background:#f3f4f6;">Durée</th>
              <th style="border:1px solid #ddd; padding:8px; background:#f3f4f6;">Action</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <div style="margin-top:12px; margin-bottom:12px;">
        <button type="button" id="btnAdd" onclick="ajouterLigne()" style="cursor:pointer; padding:8px 12px; border-radius:6px; border:none; background:#16a34a; color:#fff; font-weight:bold;">
          + Ajouter une séance
        </button>
      </div>
    <?php endif; ?>

    <div style="margin:0 0 8px 0; font-weight:bold; color:#111827;">
      Planning enregistré :
    </div>

    <div id="blocPlanningSemaine" style="margin-top:18px;">
      <table id="tabPlanningSemaine" style="width:100%; border-collapse:collapse; margin-top:10px;">
        <thead>
          <tr>
            <th style="border:1px solid #ddd; padding:8px; background:#ecfdf5; color:#166534;">N° cours</th>
            <th style="border:1px solid #ddd; padding:8px; background:#ecfdf5; color:#166534;">Élève</th>
            <th style="border:1px solid #ddd; padding:8px; background:#ecfdf5; color:#166534;">Date</th>
            <th style="border:1px solid #ddd; padding:8px; background:#ecfdf5; color:#166534;">Heure début</th>
            <th style="border:1px solid #ddd; padding:8px; background:#ecfdf5; color:#166534;">Heure fin</th>
            <th style="border:1px solid #ddd; padding:8px; background:#ecfdf5; color:#166534;">Durée</th>
            <?php if (!$saisieFermee): ?>
              <th style="border:1px solid #ddd; padding:8px; background:#ecfdf5; color:#166534;">Action</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody></tbody>
      </table>

      <div id="total" style="margin-top:10px; font-weight:bold;">Total : 0 h</div>
      <div id="msg" style="color:#b91c1c; font-weight:bold; margin-top:8px;"></div>

      <?php if (!$saisieFermee): ?>
        <div style="margin-top:20px; display:flex; gap:10px; flex-wrap:wrap;">
          <button type="button" id="btnSavePlanning" onclick="enregistrerPlanningSalles('VALIDE')" style="cursor:pointer; padding:10px 14px; border-radius:6px; border:none; background:#16a34a; color:#fff; font-weight:bold;">
            💾 Enregistrer ma réservation de salle
          </button>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
const SAISIE_FERMEE = <?= $saisieFermee ? 'true' : 'false' ?>;

let currentCours = 0;
let currentEleve = '';
let lignes = <?= json_encode($lignesPlanningJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || [];

function dateFrToIso(dateFr) {
  const s = String(dateFr || '').trim();
  if (!/^\d{2}\/\d{2}\/\d{4}$/.test(s)) return '';
  const [d, m, y] = s.split('/');
  return y + '-' + m + '-' + d;
}

function escapeHtml(str) {
  return String(str || '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function ajouterLigne() {
  if (SAISIE_FERMEE) return;

  if (!currentCours) {
    document.getElementById('msg').textContent = "⚠️ Veuillez d’abord sélectionner un cours.";
    return;
  }

  const newId = Date.now() * -1;

  lignes.push({
    id: newId,
    id_cours: currentCours,
    eleve: currentEleve,
    date: '',
    debut: '',
    fin: '',
    duree: 0,
    etat: 'edit'
  });

  rafraichirTableau();
}

function modifierLigne(id) {
  if (SAISIE_FERMEE) return;

  const l = lignes.find(x => x.id === id);
  if (!l) return;

  l.etat = 'edit';
  rafraichirTableau();
}

function supprimerLigne(id) {
  if (SAISIE_FERMEE) return;

  lignes = lignes.filter(x => x.id !== id);

  const msgBox = document.getElementById('msg');
  if (msgBox) {
    msgBox.textContent = '';
  }

  rafraichirTableau();
}

function rafraichirTableau() {
  const tbodySaisie = document.querySelector('#tabSaisie tbody');
  const tbodyPlanning = document.querySelector('#tabPlanningSemaine tbody');

  if (!tbodyPlanning) return;

  if (tbodySaisie) {
    tbodySaisie.innerHTML = '';
  }

  tbodyPlanning.innerHTML = '';

  let total = 0;

  lignes.forEach(l => {
    total += parseFloat(l.duree || 0) || 0;

    const tr = document.createElement('tr');

    if (!SAISIE_FERMEE && l.etat === 'edit') {
      const dateIso = dateFrToIso(l.date);

      tr.innerHTML = `
        <td style="border:1px solid #ddd; padding:6px; text-align:center;">${escapeHtml(l.id_cours)}</td>
        <td style="border:1px solid #ddd; padding:6px;">${escapeHtml(l.eleve || '')}</td>
        <td style="border:1px solid #ddd; padding:6px; text-align:center;">
          <input id="d_${l.id}" type="date" min="<?= htmlspecialchars($semaineLundiSql) ?>" max="<?= htmlspecialchars($semaineVendrediSql) ?>" value="${escapeHtml(dateIso)}">
        </td>
        <td style="border:1px solid #ddd; padding:6px; text-align:center;">
          <input id="b_${l.id}" type="text" placeholder="9:00" value="${escapeHtml(l.debut || '')}">
        </td>
        <td style="border:1px solid #ddd; padding:6px; text-align:center;">
          <input id="f_${l.id}" type="text" placeholder="10:30" value="${escapeHtml(l.fin || '')}">
        </td>
        <td style="border:1px solid #ddd; padding:6px; text-align:center;">${(parseFloat(l.duree || 0) || 0).toFixed(2).replace('.', ',')}</td>
        <td style="border:1px solid #ddd; padding:6px; text-align:center;">
          <button type="button" onclick="validerLigne(${l.id})">✅</button>
          <button type="button" onclick="supprimerLigne(${l.id})">🗑</button>
        </td>
      `;

      if (tbodySaisie) {
        tbodySaisie.appendChild(tr);
      }

    } else {
      tr.innerHTML = `
        <td style="border:1px solid #ddd; padding:6px; text-align:center;">${escapeHtml(l.id_cours)}</td>
        <td style="border:1px solid #ddd; padding:6px;">${escapeHtml(l.eleve || '')}</td>
        <td style="border:1px solid #ddd; padding:6px; text-align:center;">${escapeHtml(l.date || '')}</td>
        <td style="border:1px solid #ddd; padding:6px; text-align:center;">${escapeHtml(l.debut || '')}</td>
        <td style="border:1px solid #ddd; padding:6px; text-align:center;">${escapeHtml(l.fin || '')}</td>
        <td style="border:1px solid #ddd; padding:6px; text-align:center;">${(parseFloat(l.duree || 0) || 0).toFixed(2).replace('.', ',')}</td>
        ${SAISIE_FERMEE ? '' : `
          <td style="border:1px solid #ddd; padding:6px; text-align:center;">
            <button type="button" onclick="modifierLigne(${l.id})">✏️ Modifier</button>
            <button type="button" onclick="supprimerLigne(${l.id})">🗑 Supprimer</button>
          </td>
        `}
      `;

      tbodyPlanning.appendChild(tr);
    }
  });

  document.getElementById('total').textContent =
    'Total : ' + total.toFixed(2).replace('.', ',') + ' h';
}

function validerLigne(id) {
  if (SAISIE_FERMEE) return;

  const l = lignes.find(x => x.id === id);
  if (!l) return;

  const dInput = document.getElementById('d_' + id);
  const bInput = document.getElementById('b_' + id);
  const fInput = document.getElementById('f_' + id);
  const msgBox = document.getElementById('msg');

  if (msgBox) {
    msgBox.textContent = '';
  }

  const dateVal = (dInput?.value || '').trim();
  const debutVal = (bInput?.value || '').trim();
  const finVal = (fInput?.value || '').trim();

  if (!dateVal || !debutVal || !finVal) {
    if (msgBox) {
      msgBox.textContent = "⚠️ Merci de renseigner la date, l’heure de début et l’heure de fin.";
    }
    return;
  }

  const minDate = "<?= htmlspecialchars($semaineLundiSql) ?>";
  const maxDate = "<?= htmlspecialchars($semaineVendrediSql) ?>";

  if (dateVal < minDate || dateVal > maxDate) {
    if (msgBox) {
      msgBox.textContent = "⚠️ Vous devez saisir une date comprise entre le <?= htmlspecialchars($semaineLundiFr) ?> et le <?= htmlspecialchars($semaineVendrediFr) ?>.";
    }
    return;
  }

  function normalizeHour(v) {
    let s = String(v || '').trim().toLowerCase().replace('h', ':').replace('.', ':');

    if (/^\d{1,2}$/.test(s)) {
      return s.padStart(2, '0') + ':00';
    }

    if (/^\d{1,2}:\d{2}$/.test(s)) {
      const parts = s.split(':');
      return parts[0].padStart(2, '0') + ':' + parts[1];
    }

    return '';
  }

  const debutNorm = normalizeHour(debutVal);
  const finNorm = normalizeHour(finVal);

  if (!debutNorm || !finNorm) {
    if (msgBox) {
      msgBox.textContent = "⚠️ Format d’heure invalide. Exemple : 9, 9:00, 9.30, 10:30.";
    }
    return;
  }

  const [h1, m1] = debutNorm.split(':').map(Number);
  const [h2, m2] = finNorm.split(':').map(Number);

  const t1 = h1 * 60 + m1;
  const t2 = h2 * 60 + m2;

  if (t2 <= t1) {
    if (msgBox) {
      msgBox.textContent = "⚠️ L’heure de fin doit être postérieure à l’heure de début.";
    }
    return;
  }

  const duree = (t2 - t1) / 60;

  const autresLignes = lignes.filter(x => x.id !== id && x.etat !== 'edit');

  for (const autre of autresLignes) {
    if (!autre.date || !autre.debut || !autre.fin) continue;

    const [jd, jm, jy] = String(autre.date).split('/');
    const autreDateIso = jy + '-' + jm + '-' + jd;

    if (autreDateIso !== dateVal) continue;

    const [ah1, am1] = String(autre.debut).split(':').map(Number);
    const [ah2, am2] = String(autre.fin).split(':').map(Number);

    const a1 = ah1 * 60 + am1;
    const a2 = ah2 * 60 + am2;

    const chevauche = (t1 < a2) && (t2 > a1);

    if (chevauche) {
      if (msgBox) {
        msgBox.textContent =
          "⚠️ Cette séance chevauche une autre réservation déjà saisie pour le "
          + autre.date
          + " ("
          + autre.debut
          + "–"
          + autre.fin
          + ").";
      }
      return;
    }
  }

  const [y, m, d] = dateVal.split('-');

  l.date = d + '/' + m + '/' + y;
  l.debut = debutNorm;
  l.fin = finNorm;
  l.duree = duree;
  l.etat = 'lock';

  rafraichirTableau();
}

async function chargerCours() {
  if (SAISIE_FERMEE) return;

  const sel = document.getElementById('cours');
  if (!sel) return;

  sel.innerHTML = '<option value="">— Sélectionnez un cours —</option>';

  try {
    const res = await fetch('/modules/planning_salles/prof/select_cours_salles.php', {
      cache: 'no-store',
      credentials: 'include'
    });

    const js = await res.json();

    if (!js || js.success !== true || !Array.isArray(js.cours)) {
      document.getElementById('msg').textContent = "⚠️ Impossible de charger la liste des cours.";
      return;
    }

    js.cours.forEach(c => {
      const opt = document.createElement('option');
      opt.value = c.id_cours;
      opt.textContent = c.id_cours + ' — ' + (c.eleve || '');
      sel.appendChild(opt);
    });

  } catch (e) {
    document.getElementById('msg').textContent = "⚠️ Erreur lors du chargement des cours.";
  }
}

const selectCours = document.getElementById('cours');

if (selectCours) {
  selectCours.addEventListener('change', function(){
    const val = this.value;
    const txt = this.options[this.selectedIndex]?.text || '';

    currentEleve = txt.includes(' — ')
      ? txt.split(' — ').slice(1).join(' — ').trim()
      : '';

    if (!val) {
      currentCours = 0;
      document.getElementById('titreCours').textContent = '';
      return;
    }

    currentCours = parseInt(val, 10) || 0;
    document.getElementById('titreCours').textContent = 'Planning du cours ' + txt;
  });
}

async function enregistrerPlanningSalles(mode) {
  if (SAISIE_FERMEE) return;

  const msgBox = document.getElementById('msg');
  const btnSave = document.getElementById('btnSavePlanning');

  if (msgBox) {
    msgBox.textContent = '';
    msgBox.style.color = '#b91c1c';
  }

  const lignesEnEdition = lignes.filter(l => l.etat === 'edit');

  if (lignesEnEdition.length > 0) {
    if (msgBox) {
      msgBox.textContent = "⚠️ Une ou plusieurs lignes sont en cours de modification. Merci de cliquer sur ✅ avant d’enregistrer.";
    }
    return;
  }

  const lignesValidees = lignes.filter(l => l.etat !== 'edit');

  const payload = {
    mode: mode,
    semaine_lundi: "<?= htmlspecialchars($semaineLundiSql) ?>",
    semaine_vendredi: "<?= htmlspecialchars($semaineVendrediSql) ?>",
    lignes: lignesValidees
  };

  if (btnSave) {
    btnSave.disabled = true;
    btnSave.style.opacity = '0.7';
    btnSave.style.cursor = 'not-allowed';
    btnSave.textContent = '⏳ Enregistrement en cours...';
  }

  try {
    const res = await fetch('/actions/save_planning_salles.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    });

    const js = await res.json();

    if (!js || js.success !== true) {
      if (msgBox) {
        msgBox.style.color = '#b91c1c';
        msgBox.textContent = (js && js.error)
          ? js.error
          : "⚠️ Erreur lors de l’enregistrement du planning.";
      }

      if (btnSave) {
        btnSave.disabled = false;
        btnSave.style.opacity = '1';
        btnSave.style.cursor = 'pointer';
        btnSave.textContent = '💾 Enregistrer ma réservation de salle';
      }

      return;
    }

    if (msgBox) {
      msgBox.style.color = '#166534';

      if (lignesValidees.length === 0) {
        msgBox.textContent = "✅ Votre réservation de salle a bien été supprimée pour cette semaine.";
      } else {
        msgBox.textContent = "✅ Votre réservation de salle a bien été enregistrée. Vous pouvez encore la modifier jusqu’au vendredi à 15h00.";
      }
    }

    if (lignesValidees.length === 0) {
      alert(
        "✅ Votre réservation de salle a bien été supprimée pour la semaine du <?= htmlspecialchars($semaineLundiFr) ?> au <?= htmlspecialchars($semaineVendrediFr) ?>."
      );

      window.location.reload();
      return;
    }

    alert(
      "✅ Votre réservation de salle a bien été enregistrée.\n\n" +
      "Vous pouvez encore la modifier jusqu’au vendredi à 15h00 pour la semaine du <?= htmlspecialchars($semaineLundiFr) ?> au <?= htmlspecialchars($semaineVendrediFr) ?>.\n\n" +
      "Après ce délai, aucune modification ne sera possible depuis le portail."
    );

    if (btnSave) {
      btnSave.disabled = false;
      btnSave.style.opacity = '1';
      btnSave.style.cursor = 'pointer';
      btnSave.textContent = '💾 Enregistrer ma réservation de salle';
    }

    console.log('save_planning_salles réponse :', js);

  } catch (e) {
    if (msgBox) {
      msgBox.style.color = '#b91c1c';
      msgBox.textContent = "⚠️ Erreur réseau lors de l’enregistrement du planning.";
    }

    if (btnSave) {
      btnSave.disabled = false;
      btnSave.style.opacity = '1';
      btnSave.style.cursor = 'pointer';
      btnSave.textContent = '💾 Enregistrer ma réservation de salle';
    }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  chargerCours();
  rafraichirTableau();
});
</script>

</body>
</html>
