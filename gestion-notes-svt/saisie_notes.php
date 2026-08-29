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

$moduleId = (int) ($_GET['module_id'] ?? 0);
$module = $repo->module($moduleId);
if (!$module) {
    http_response_code(404);
    exit('Module introuvable.');
}
$aElements = (int) $module['a_elements'] === 1;
$elements = $aElements ? $repo->elementsDuModule($moduleId) : [];
$poidsModule = $aElements ? [] : $repo->ponderationsModule($moduleId);
$composantes = ['TP', 'CC', 'EXAM'];

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    try {
        foreach ($_POST['notes'] ?? [] as $etudiantId => $valeurs) {
            $etudiantId = (int) $etudiantId;
            if ($aElements) {
                foreach ($valeurs as $elementId => $parType) {
                    foreach ($parType as $type => $brut) {
                        $v = ($brut === '' || $brut === null) ? null : (float) str_replace(',', '.', $brut);
                        $repo->enregistrerNoteElement($etudiantId, (int) $elementId, $type, $v, 'normale');
                    }
                }
            } else {
                foreach ($valeurs as $type => $brut) {
                    $v = ($brut === '' || $brut === null) ? null : (float) str_replace(',', '.', $brut);
                    $repo->enregistrerNoteModule($etudiantId, $moduleId, $type, $v, 'normale');
                }
            }
        }
        $pdo->commit();
        $message = ['type' => 'success', 'texte' => 'Notes enregistrées.'];
    } catch (Throwable $e) {
        $pdo->rollBack();
        $message = ['type' => 'danger', 'texte' => 'Erreur lors de l\'enregistrement : ' . $e->getMessage()];
    }
}

$etudiants = $repo->etudiantsActifs((int) $annee['id']);
$service = new ResultatsService($pdo, $repo);
$resultats = $service->resultatsModule($module, $etudiants);

$notesActuelles = [];
if ($aElements) {
    foreach ($elements as $el) {
        $notesActuelles[$el['id']] = $repo->notesElement((int) $el['id'], 'normale');
    }
} else {
    $notesActuelles = $repo->notesModule($moduleId, 'normale');
}

$titrePage = 'Saisie — ' . $module['code'];
require __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-1"><?= htmlspecialchars($module['code']) ?> — <?= htmlspecialchars($module['intitule']) ?></h1>
<p class="text-muted">Saisie des notes — session normale — <?= htmlspecialchars($annee['libelle']) ?></p>

<?php if ($message): ?>
  <div class="alert alert-<?= $message['type'] ?>"><?= htmlspecialchars($message['texte']) ?></div>
<?php endif; ?>

<form method="post">
<div class="table-responsive">
<table class="table table-bordered bg-white align-middle">
  <thead class="table-light">
    <tr>
      <th rowspan="2">N°</th>
      <th rowspan="2">Nom &amp; Prénom</th>
      <?php if ($aElements): ?>
        <?php foreach ($elements as $el): ?>
          <th colspan="2" class="text-center"><?= htmlspecialchars($el['intitule']) ?></th>
        <?php endforeach; ?>
      <?php else: ?>
        <?php foreach ($composantes as $type): if (!isset($poidsModule[$type])) continue; ?>
          <th rowspan="2" class="text-center"><?= $type ?><br><small class="text-muted"><?= $poidsModule[$type] * 100 ?>%</small></th>
        <?php endforeach; ?>
      <?php endif; ?>
      <th rowspan="2" class="text-center">Moyenne</th>
      <th rowspan="2" class="text-center">Décision</th>
    </tr>
    <?php if ($aElements): ?>
      <tr>
        <?php foreach ($elements as $el): ?>
          <th class="text-center small">CC</th><th class="text-center small">Examen</th>
        <?php endforeach; ?>
      </tr>
    <?php endif; ?>
  </thead>
  <tbody>
  <?php foreach ($etudiants as $e): $eid = (int) $e['id']; $r = $resultats[$eid] ?? []; ?>
    <tr>
      <td><?= (int) ($e['numero_ordre'] ?? 0) ?></td>
      <td><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?></td>
      <?php if ($aElements): ?>
        <?php foreach ($elements as $el): $cur = $notesActuelles[$el['id']][$eid] ?? []; ?>
          <td><input type="text" inputmode="decimal" class="form-control form-control-sm note-input"
                     name="notes[<?= $eid ?>][<?= $el['id'] ?>][CC]" value="<?= htmlspecialchars((string) ($cur['CC'] ?? '')) ?>"></td>
          <td><input type="text" inputmode="decimal" class="form-control form-control-sm note-input"
                     name="notes[<?= $eid ?>][<?= $el['id'] ?>][EXAM]" value="<?= htmlspecialchars((string) ($cur['EXAM'] ?? '')) ?>"></td>
        <?php endforeach; ?>
      <?php else: ?>
        <?php foreach ($composantes as $type): if (!isset($poidsModule[$type])) continue; $cur = $notesActuelles[$eid][$type] ?? null; ?>
          <td><input type="text" inputmode="decimal" class="form-control form-control-sm note-input"
                     name="notes[<?= $eid ?>][<?= $type ?>]" value="<?= htmlspecialchars($cur !== null ? (string) $cur : '') ?>"></td>
        <?php endforeach; ?>
      <?php endif; ?>
      <td class="text-center fw-bold"><?= $r['moyenne'] ?? '—' ?></td>
      <td class="text-center">
        <?php if (!empty($r['decision_initiale'])): ?>
          <span class="badge badge-<?= $r['decision_initiale'] ?>"><?= $r['decision_initiale'] ?></span>
        <?php else: ?>—<?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$etudiants): ?>
    <tr><td colspan="10" class="text-center text-muted">Aucun étudiant. Ajoutez des étudiants d'abord.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
<button type="submit" class="btn btn-primary">Enregistrer les notes</button>
<a href="saisie_rattrapage.php?module_id=<?= $moduleId ?>&annee_id=<?= $annee['id'] ?>" class="btn btn-outline-warning">Saisir le rattrapage &raquo;</a>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
