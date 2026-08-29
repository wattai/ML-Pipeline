<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
exigerConnexion();

require_once __DIR__ . '/classes/Repository.php';
require_once __DIR__ . '/classes/ResultatsService.php';
require_once __DIR__ . '/includes/contexte.php';

$pdo = getPDO();
$repo = new Repository($pdo);
[$annee, $semestre] = resoudreContexte($repo);

$modules = $repo->modulesDuSemestre((int) $semestre['id']);
$etudiants = $repo->etudiantsActifs((int) $annee['id']);

$service = new ResultatsService($pdo, $repo);
$pv = $service->pvSemestre($modules, $etudiants);

$nomFichier = sprintf('PV_%s_%s.csv', $semestre['code'], $annee['libelle']);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nomFichier . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel

$entetes = ['N°', 'Massar', 'Nom', 'Prénom'];
foreach ($modules as $m) {
    $entetes[] = $m['code'] . ' - Note';
    $entetes[] = $m['code'] . ' - Décision';
}
$entetes[] = 'Moyenne Semestre';
$entetes[] = 'Modules NV';
$entetes[] = 'Décision Semestre';
fputcsv($out, $entetes, ';');

foreach ($pv as $ligne) {
    $e = $ligne['etudiant'];
    $row = [$e['numero_ordre'], $e['massar'], $e['nom'], $e['prenom']];
    foreach ($modules as $m) {
        $r = $ligne['modules'][$m['id']] ?? [];
        $row[] = $r['note_finale'] ?? $r['moyenne'] ?? '';
        $row[] = $r['decision_affichage'] ?? '';
    }
    $row[] = $ligne['moyenne_semestre'] ?? '';
    $row[] = $ligne['nb_modules_nv'];
    $row[] = $ligne['decision_semestre'] ?? '';
    fputcsv($out, $row, ';');
}

fclose($out);
