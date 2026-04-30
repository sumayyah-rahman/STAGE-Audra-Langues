<?php
/* =========================================================
   Suivi CPF — Heures & CA (TNS 80 %)
   Lecture seule (EBP) — utilise _PROG_Analyse_Planning_Prof,
   _PROG_Analyse_Facturation, Colleague, Item.
   Prix horaire = Item.SalePriceVatExcluded (HT).
   Accès : réservé ADMIN (portail_admin).
   ========================================================= */

declare(strict_types=1);
session_start();

// ENV éventuel
$configEnvPath = __DIR__ . '/config_env.php';
if (file_exists($configEnvPath)) {
    require_once $configEnvPath;
}

require_once __DIR__ . '/db_config.php';
$conn = db();
if (!$conn) { die('❌ Connexion SQL impossible'); }

// Garde ADMIN
require_once __DIR__ . '/guards_prof.php';
audra_guard_admin_page($conn);

/* ---------- Filtres ---------- */
$annee   = isset($_GET['annee']) ? (int)$_GET['annee'] : (int)date('Y');
$mois    = isset($_GET['mois'])  ? (int)$_GET['mois']  : 0;   // 0 = toute l'année
$profQ   = isset($_GET['prof'])  ? trim((string)$_GET['prof']) : '';
$tnsOnly = !empty($_GET['tns_only']) ? 1 : 0;
$export  = !empty($_GET['export']) && $_GET['export']==='csv';

$profQ_clean = preg_replace('/[^a-zA-ZÀ-ÿ \'-]/u', '', $profQ);
$profQ_sql   = str_replace("'", "''", $profQ_clean);

/* ---------- SQL : 2 jeux de résultats ---------- */
$sql = "
SET NOCOUNT ON;

DECLARE @annee INT = {$annee};
DECLARE @mois  INT = {$mois};
DECLARE @tns_only BIT = {$tnsOnly};
DECLARE @prof_like NVARCHAR(200) = N'{$profQ_sql}';

DECLARE @date_from DATE, @date_to DATE;
IF @mois = 0
BEGIN
  SET @date_from = DATEFROMPARTS(@annee,1,1);
  SET @date_to   = DATEADD(DAY,1,EOMONTH(DATEFROMPARTS(@annee,12,1)));
END
ELSE
BEGIN
  SET @date_from = DATEFROMPARTS(@annee,@mois,1);
  SET @date_to   = DATEADD(DAY,1,EOMONTH(@date_from));
END;

/* ===============================
   1) Heures CPF validées -> #H
   =============================== */
IF OBJECT_ID('tempdb..#H') IS NOT NULL DROP TABLE #H;

SELECT
  YEAR(p.[Date de planning])    AS annee,
  MONTH(p.[Date de planning])   AS mois,
  LTRIM(RTRIM(p.[Professeur]))  AS professeur,
  CASE WHEN UPPER(ISNULL(c.xx_Statut_prof,'')) LIKE '%TNS%' THEN 'TNS'
       ELSE 'Salarie' END       AS statut,
  CONVERT(NUMERIC(18,2), p.[Heures confirmées]) AS heures,
  CONVERT(NVARCHAR(60), p.[N° cours])           AS no_cours
INTO #H
FROM dbo._PROG_Analyse_Planning_Prof AS p
LEFT JOIN dbo.Colleague AS c
  ON UPPER(LTRIM(RTRIM(c.Contact_Name+' '+c.Contact_FirstName)))
   = UPPER(LTRIM(RTRIM(p.[Professeur])))
WHERE p.[Date de planning] >= @date_from
  AND p.[Date de planning] <  @date_to
  AND p.[Heures confirmées] > 0
  /* CPF = certification renseignée OU libellé CPF/EDOF */
  AND (
       NULLIF(LTRIM(RTRIM(p.[Certification visée])),'') IS NOT NULL
    OR UPPER(p.[Intitule du cours]) LIKE '%CPF%'
    OR UPPER(p.[Intitule du cours]) LIKE '%EDOF%'
  )
  AND UPPER(p.[Professeur]) NOT LIKE '%BRIGHT%TEST%';

/* =========================================
   1bis) Heures globales (sans filtre CPF)
   ========================================= */
IF OBJECT_ID('tempdb..#H_ALL') IS NOT NULL DROP TABLE #H_ALL;

SELECT
  YEAR(p.[Date de planning])    AS annee,
  MONTH(p.[Date de planning])   AS mois,
  LTRIM(RTRIM(p.[Professeur]))  AS professeur,
  CASE WHEN UPPER(ISNULL(c.xx_Statut_prof,'')) LIKE '%TNS%' THEN 'TNS'
       ELSE 'Salarie' END       AS statut,
  CONVERT(NUMERIC(18,2), p.[Heures confirmées]) AS heures
INTO #H_ALL
FROM dbo._PROG_Analyse_Planning_Prof AS p
LEFT JOIN dbo.Colleague AS c
  ON UPPER(LTRIM(RTRIM(c.Contact_Name+' '+c.Contact_FirstName)))
   = UPPER(LTRIM(RTRIM(p.[Professeur])))
WHERE p.[Date de planning] >= @date_from
  AND p.[Date de planning] <  @date_to
  AND p.[Heures confirmées] > 0
  AND UPPER(p.[Professeur]) NOT LIKE '%BRIGHT%TEST%';

/* =========================================
   2) Prix horaire HT (Item + secours factu)
   ========================================= */
IF OBJECT_ID('tempdb..#PrixFactu') IS NOT NULL DROP TABLE #PrixFactu;

SELECT
  CONVERT(NVARCHAR(60), [N° cours]) AS no_cours,
  YEAR([Date])                      AS annee,
  MONTH([Date])                     AS mois,
  AVG(CONVERT(NUMERIC(18,2), [Prix])) AS prix_eur_h
INTO #PrixFactu
FROM dbo._PROG_Analyse_Facturation
GROUP BY CONVERT(NVARCHAR(60), [N° cours]), YEAR([Date]), MONTH([Date]);

DECLARE @KeyCol sysname;
SELECT TOP (1) @KeyCol = c.name
FROM sys.columns AS c
WHERE c.object_id = OBJECT_ID('dbo.Item')
  AND c.name IN (N'Code',N'Id',N'ItemCode',N'Number',N'No',N'No_')
ORDER BY CASE c.name
  WHEN N'Code' THEN 1 WHEN N'Id' THEN 2 WHEN N'ItemCode' THEN 3
  WHEN N'Number' THEN 4 WHEN N'No' THEN 5 WHEN N'No_' THEN 6 ELSE 99 END;

IF @KeyCol IS NULL
BEGIN
  RAISERROR('Impossible de déterminer la clé de dbo.Item (Code/Id/ItemCode/Number/No/No_)',16,1);
  RETURN;
END;

/* #HP (CPF + prix) */
IF OBJECT_ID('tempdb..#HP') IS NOT NULL DROP TABLE #HP;
CREATE TABLE #HP(
  annee INT, mois INT, professeur NVARCHAR(200), statut VARCHAR(10),
  heures NUMERIC(18,2), prix_eur_h NUMERIC(18,6)
);

DECLARE @sql NVARCHAR(MAX) =
N'INSERT INTO #HP(annee,mois,professeur,statut,heures,prix_eur_h)
  SELECT h.annee,h.mois,h.professeur,h.statut,h.heures,
         COALESCE(CONVERT(NUMERIC(18,6), i.SalePriceVatExcluded),
                  pf.prix_eur_h, 0) AS prix_eur_h
  FROM #H h
  LEFT JOIN dbo.Item i
    ON CONVERT(NVARCHAR(60), i.' + QUOTENAME(@KeyCol) + N') = h.no_cours
  LEFT JOIN #PrixFactu pf
    ON pf.no_cours = h.no_cours AND pf.annee = h.annee AND pf.mois = h.mois;';
EXEC sp_executesql @sql;

/* Filtres dynamiques (prof contient + TNS uniquement) */
;WITH HPF AS (
  SELECT * FROM #HP
  WHERE (@tns_only = 0 OR statut='TNS')
    AND (@prof_like = N'' OR UPPER(professeur) LIKE '%' + UPPER(@prof_like) + '%')
),
HAF AS (
  SELECT * FROM #H_ALL
  WHERE (@tns_only = 0 OR statut='TNS')
    AND (@prof_like = N'' OR UPPER(professeur) LIKE '%' + UPPER(@prof_like) + '%')
),
CPF_MONTH AS (
  SELECT
    annee, mois,
    SUM(CASE WHEN statut='TNS'     THEN heures ELSE 0 END) AS heures_cpf_tns,
    SUM(CASE WHEN statut<>'TNS'    THEN heures ELSE 0 END) AS heures_cpf_salaries,
    SUM(heures)                                            AS heures_total_cpf,
    SUM(CASE WHEN statut='TNS'     THEN heures*prix_eur_h ELSE 0 END) AS ca_tns,
    SUM(CASE WHEN statut<>'TNS'    THEN heures*prix_eur_h ELSE 0 END) AS ca_salaries,
    SUM(heures*prix_eur_h)                                  AS ca_total
  FROM HPF
  GROUP BY annee, mois
),
GLOB_MONTH AS (
  SELECT
    annee, mois,
    SUM(heures) AS heures_global,
    SUM(CASE WHEN statut='TNS' THEN heures ELSE 0 END) AS heures_global_tns
  FROM HAF
  GROUP BY annee, mois
)
SELECT
  c.annee, c.mois,
  c.heures_cpf_tns, c.heures_cpf_salaries, c.heures_total_cpf,
  ISNULL(g.heures_global,0)      AS heures_global,
  ISNULL(g.heures_global_tns,0)  AS heures_global_tns,
  c.ca_tns, c.ca_salaries, c.ca_total,
  /* % heures TNS basé CPF (on le garde pour info) */
  CASE WHEN c.heures_total_cpf>0
       THEN CONVERT(NUMERIC(6,2), 100.0*c.heures_cpf_tns/c.heures_total_cpf)
       ELSE CONVERT(NUMERIC(6,2),0) END AS pct_heures_TNS_cpf,
  /* % heures TNS GLOBAL (nouveau) */
  CASE WHEN ISNULL(g.heures_global,0)>0
       THEN CONVERT(NUMERIC(6,2), 100.0*ISNULL(g.heures_global_tns,0)/g.heures_global)
       ELSE CONVERT(NUMERIC(6,2),0) END AS pct_heures_TNS_global,
  CASE WHEN c.ca_total>0
       THEN CONVERT(NUMERIC(6,2), 100.0*c.ca_tns/c.ca_total)
       ELSE CONVERT(NUMERIC(6,2),0) END AS pct_ca_TNS
FROM CPF_MONTH c
LEFT JOIN GLOB_MONTH g ON g.annee=c.annee AND g.mois=c.mois
ORDER BY c.annee, c.mois;

/* ===== Détail par prof (CPF + heures globales) ===== */
WITH CPF_DETAIL AS (
  SELECT
    annee, mois, professeur, statut,
    SUM(heures) AS heures_cpf,
    CONVERT(NUMERIC(18,2), SUM(heures*prix_eur_h)/NULLIF(SUM(heures),0)) AS tarif_moyen_eur_h,
    SUM(heures*prix_eur_h) AS ca_ht
  FROM HPF
  GROUP BY annee, mois, professeur, statut
),
GLOB_DETAIL AS (
  SELECT annee, mois, professeur, SUM(heures) AS heures_global
  FROM HAF
  GROUP BY annee, mois, professeur
)
SELECT
  d.annee, d.mois, d.professeur, d.statut,
  d.heures_cpf,
  d.tarif_moyen_eur_h, d.ca_ht,
  ISNULL(g.heures_global,0) AS heures_global
FROM CPF_DETAIL d
LEFT JOIN GLOB_DETAIL g
  ON g.annee=d.annee AND g.mois=d.mois
 AND UPPER(g.professeur)=UPPER(d.professeur)
ORDER BY d.annee, d.mois, d.professeur;
";

/* ---------- Exécution ---------- */
$st = sqlsrv_query($conn, $sql);
if (!$st) {
  header('Content-Type: text/plain; charset=utf-8');
  print_r(sqlsrv_errors());
  exit;
}

/* Synthèse par mois */
$synth = [];
while ($row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) { $synth[] = $row; }

/* Détail par prof */
sqlsrv_next_result($st);
$detail = [];
while ($row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) { $detail[] = $row; }
sqlsrv_free_stmt($st);

/* Agrégats pour les tuiles */
$ca_total=0.0; $ca_tns=0.0;
$heures_total_cpf=0.0; $heures_cpf_tns=0.0;
$heures_global_total=0.0; $heures_global_tns=0.0;

foreach ($synth as $r) {
  $ca_total            += (float)$r['ca_total'];
  $ca_tns              += (float)$r['ca_tns'];
  $heures_total_cpf    += (float)$r['heures_total_cpf'];
  $heures_cpf_tns      += (float)$r['heures_cpf_tns'];
  $heures_global_total += (float)$r['heures_global'];
  $heures_global_tns   += (float)$r['heures_global_tns'];
}
$pct_ca_tns            = $ca_total>0 ? 100.0*$ca_tns/$ca_total : 0.0;
$pct_heures_tns_cpf    = $heures_total_cpf>0 ? 100.0*$heures_cpf_tns/$heures_total_cpf : 0.0;
$pct_heures_tns_global = $heures_global_total>0 ? 100.0*$heures_global_tns/$heures_global_total : 0.0;

$seuil_80       = 0.80 * $ca_total;
$marge_restante = max(0.0, $seuil_80 - $ca_tns);

/* ---------- Export CSV ---------- */
if ($export) {
  header('Content-Type: text/csv; charset=utf-8');
  $fn = sprintf('suivi_cpf_%d_%s.csv', $annee, str_pad((string)$mois, 2, '0', STR_PAD_LEFT));
  header('Content-Disposition: attachment; filename="'.$fn.'"');
  echo "\xEF\xBB\xBF";
  $out=fopen('php://output','w');
  fputcsv($out, ['Année','Mois','Professeur','Statut','Heures CPF','Heures Globales','Tarif moyen €/h','CA HT'], ';');
  foreach($detail as $r){
    fputcsv($out, [
      $r['annee'],
      $r['mois'],
      $r['professeur'],
      $r['statut'],
      number_format((float)$r['heures_cpf'],2,',',' '),
      number_format((float)$r['heures_global'],2,',',' '),
      number_format((float)$r['tarif_moyen_eur_h'],2,',',' '),
      number_format((float)$r['ca_ht'],2,',',' ')
    ], ';');
  }
  fclose($out); exit;
}

/* ---------- UI ---------- */
function nf($v,$d=2){ return number_format((float)$v,$d,',',' '); }
$moisNoms=[1=>'Jan',2=>'Fév',3=>'Mar',4=>'Avr',5=>'Mai',6=>'Juin',7=>'Juil',8=>'Août',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Déc'];
$yNow=(int)date('Y');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Suivi CPF — Heures & CA (TNS 80%)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--bg:#f8fafc;--card:#fff;--bd:#e5e7eb;--txt:#0f172a;--muted:#64748b;--head:#f1f5f9;--accent:#1d4ed8}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--txt);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Noto Sans",sans-serif}
.container{max-width:1200px;margin:0 auto;padding:18px 14px 40px}
h1{margin:0 0 12px;font-size:20px}
.toolbar{display:flex;flex-wrap:wrap;gap:10px 12px;align-items:end;margin-bottom:12px}
.toolbar .g{display:flex;flex-direction:column;gap:4px}
label{font-size:12px;color:#475569}
select,input[type=text]{border:1px solid var(--bd);border-radius:8px;padding:6px 10px;background:#fff;font-size:14px}
button,a.btn{border:1px solid var(--bd);border-radius:8px;background:var(--accent);color:#fff;padding:7px 12px;cursor:pointer;text-decoration:none}
a.btn{background:#6b7280}
.kpis{display:flex;flex-wrap:wrap;gap:8px;margin:6px 0 14px}
.chip{display:inline-block;border:1px solid #dbe2ea;background:#eef2ff;border-radius:999px;padding:4px 10px;font-size:12px}
.card{background:var(--card);border:1px solid var(--bd);border-radius:10px;padding:12px}
table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:14px}
thead th{background:var(--head);border:1px solid var(--bd);padding:7px 8px;text-align:left}
tbody td{border:1px solid var(--bd);padding:7px 8px;vertical-align:top}
.num{ text-align:right; font-variant-numeric: tabular-nums; font-feature-settings:"tnum" 1,"lnum" 1}
.hint{color:var(--muted);font-size:12px;margin-top:6px}
.badge{display:inline-block;padding:2px 6px;border-radius:6px;border:1px solid var(--bd);background:#fff;font-size:12px}
.badge.TNS{background:#fef3c7}
.badge.Salarie{background:#ecfeff}
</style>
</head>
<body><div class="container">
  <h1>📊 Suivi CPF — règle des 80 % (TNS)</h1>

  <form class="toolbar" method="get">
    <div class="g"><label>Année</label>
      <select name="annee">
        <?php for($y=$yNow;$y>=$yNow-4;$y--): ?>
          <option value="<?=$y?>" <?=$annee===$y?'selected':''?>><?=$y?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="g"><label>Mois</label>
      <select name="mois">
        <option value="0" <?=$mois===0?'selected':''?>>Tous</option>
        <?php for($m=1;$m<=12;$m++): ?>
          <option value="<?=$m?>" <?=$mois===$m?'selected':''?>><?=sprintf('%02d — %s',$m,$moisNoms[$m])?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="g" style="min-width:260px"><label>Prof (contient)</label>
      <input name="prof" value="<?=htmlspecialchars($profQ_clean,ENT_QUOTES,'UTF-8')?>" placeholder="Nom ou prénom">
    </div>

    <div class="g"><label>&nbsp;</label>
      <label style="display:flex;gap:6px;align-items:center;color:#334155">
        <input type="checkbox" name="tns_only" value="1" <?=$tnsOnly?'checked':''?>> TNS uniquement
      </label>
    </div>

    <div class="g"><label>&nbsp;</label>
      <button type="submit">Filtrer</button>
    </div>
    <div class="g"><label>&nbsp;</label>
      <a class="btn" href="?<?=htmlspecialchars(http_build_query(array_merge($_GET,['export'=>'csv'])))?>">Exporter CSV</a>
    </div>
  </form>

  <div class="kpis">
    <span class="chip">CA total (sélection) : <strong><?=nf($ca_total)?> €</strong></span>
    <span class="chip">CA attribué aux TNS : <strong><?=nf($ca_tns)?> €</strong></span>
    <span class="chip">Part TNS (CA) : <strong><?=nf($pct_ca_tns,2)?> %</strong></span>
    <span class="chip">Seuil 80 % : <strong><?=nf($seuil_80)?> €</strong></span>
    <span class="chip">Marge restante : <strong><?=nf($marge_restante)?> €</strong></span>
    <span class="chip">Part TNS (heures — global) : <strong><?=nf($pct_heures_tns_global,2)?> %</strong></span>
  </div>

  <div class="card" style="margin-bottom:14px">
    <h3 style="margin:0 0 8px;font-size:16px">Synthèse par mois</h3>
    <table>
      <thead><tr>
        <th>Année</th><th>Mois</th>
        <th class="num">Heures CPF — TNS</th>
        <th class="num">Heures CPF — salariés</th>
        <th class="num">Heures total — CPF</th>
        <th class="num">Heures — Global</th>
        <th class="num">CA TNS (€)</th>
        <th class="num">CA salariés (€)</th>
        <th class="num">CA total (€)</th>
        <th class="num">% heures TNS (CPF)</th>
        <th class="num">% heures TNS (global)</th>
        <th class="num">% CA TNS</th>
      </tr></thead>
      <tbody>
        <?php if(!$synth): ?>
          <tr><td colspan="12" class="hint">Aucune donnée pour ces filtres.</td></tr>
        <?php else: foreach($synth as $r): ?>
          <tr>
            <td><?=$r['annee']?></td>
            <td><?=sprintf('%02d — %s', $r['mois'], $moisNoms[(int)$r['mois']] ?? $r['mois'])?></td>
            <td class="num"><?=nf($r['heures_cpf_tns'])?></td>
            <td class="num"><?=nf($r['heures_cpf_salaries'])?></td>
            <td class="num"><?=nf($r['heures_total_cpf'])?></td>
            <td class="num"><?=nf($r['heures_global'])?></td>
            <td class="num"><?=nf($r['ca_tns'])?></td>
            <td class="num"><?=nf($r['ca_salaries'])?></td>
            <td class="num"><?=nf($r['ca_total'])?></td>
            <td class="num"><?=nf($r['pct_heures_TNS_cpf'],2)?></td>
            <td class="num"><?=nf($r['pct_heures_TNS_global'],2)?></td>
            <td class="num"><?=nf($r['pct_ca_TNS'],2)?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    <div class="hint">
      Prix horaire = <b>Item.SalePriceVatExcluded</b> (HT). Secours via <b>_PROG_Analyse_Facturation</b> (prix moyen par cours/mois).<br>
      Heures CPF = <b>heures confirmées</b> de l’agenda EBP (séances validées) avec CPF.<br>
      Heures globales = toutes les heures validées (CPF + non-CPF).
    </div>
  </div>

  <div class="card">
    <h3 style="margin:0 0 8px;font-size:16px">Détail par professeur</h3>
    <table>
      <thead><tr>
        <th>Année</th><th>Mois</th><th>Professeur</th><th>Statut</th>
        <th class="num">Heures CPF</th>
        <th class="num">Heures — Global</th>
        <th class="num">Tarif moyen (€/h)</th>
        <th class="num">CA HT (€)</th>
      </tr></thead>
      <tbody>
        <?php if(!$detail): ?>
          <tr><td colspan="8" class="hint">Aucun professeur pour ces filtres.</td></tr>
        <?php else: foreach($detail as $r): ?>
          <tr>
            <td><?=$r['annee']?></td>
            <td><?=sprintf('%02d — %s', $r['mois'], $moisNoms[(int)$r['mois']] ?? $r['mois'])?></td>
            <td><?=$r['professeur']?></td>
            <td><span class="badge <?=$r['statut']?>"><?=$r['statut']?></span></td>
            <td class="num"><?=nf($r['heures_cpf'])?></td>
            <td class="num"><?=nf($r['heures_global'])?></td>
            <td class="num"><?=nf($r['tarif_moyen_eur_h'],2)?></td>
            <td class="num"><?=nf($r['ca_ht'])?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div></body></html>
