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

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    try {
        foreach ($_POST['rattrapage'] ?? [] as $etudiantId => $brut) {
            $etudiantId = (int) $etudiantId;
            $v = ($brut === '' || $brut === null) ? null : (float) str_replace(',', '.', $brut);
            // Le rattrapage est saisi comme une note "EXAM" de session rattrapage,
            // rattachée au module (voir REGLES_GESTION.md §4bis pour M7).
            $repo->enregistrerNoteModule($etudiantId, $moduleId, 'EXAM', $v, 'rattrapage');
        }
        $pdo->commit();
        $message = ['type' => 'success', 'texte' => 'Notes de rattrapage enregistrées.'];
    } catch (Throwable $e) {
        $pdo->rollBack();
        $message = ['type' => 'danger', 'texte' => 'Erreur : ' . $e->getMessage()];
    }
}

$etudiants = $repo->etudiantsActifs((int) $annee['id']);
$service = new ResultatsService($pdo, $repo);
$resultats = $service->resultatsModule($module, $etudiants);
$notesRattrapage = $repo->notesModule($moduleId, 'rattrapage');

// Seuls les étudiants en décision "R" (moyenne < seuil) sont concernés par le rattrapage.
$concernes = array_filter($etudiants, fn ($e) => ($resultats[(int) $e['id']]['decision_initiale'] ?? null) === 'R');

$titrePage = 'Rattrapage — ' . $module['code'];
require __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-1"><?= htmlspecialchars($module['code']) ?> — <?= htmlspecialchars($module['intitule']) ?></h1>
<p class="text-muted">Session de rattrapage — <?= htmlspecialchars($annee['libelle']) ?></p>

<?php if ($message): ?>
  <div class="alert alert-<?= $message['type'] ?>"><?= htmlspecialchars($message['texte']) ?></div>
<?php endif; ?>

<form method="post">
<div class="table-responsive">
<table class="table table-bordered bg-white align-middle">
  <thead class="table-light">
    <tr>
      <th>N°</th><th>Nom &amp; Prénom</th><th class="text-center">Moyenne session normale</th>
      <th class="text-center">Note de rattrapage</th>
      <th class="text-center">Note finale</th><th class="text-center">Décision finale</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($concernes as $e): $eid = (int) $e['id']; $r = $resultats[$eid] ?? []; ?>
    <tr>
      <td><?= (int) ($e['numero_ordre'] ?? 0) ?></td>
      <td><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?></td>
      <td class="text-center"><?= $r['moyenne'] ?? '—' ?></td>
      <td>
        <input type="text" inputmode="decimal" class="form-control form-control-sm note-input"
               name="rattrapage[<?= $eid ?>]"
               value="<?= htmlspecialchars((string) ($notesRattrapage[$eid]['EXAM'] ?? '')) ?>">
      </td>
      <td class="text-center fw-bold"><?= $r['note_finale'] ?? '—' ?></td>
      <td class="text-center">
        <?php if (!empty($r['decision_finale'])): ?>
          <span class="badge badge-<?= $r['decision_finale'] ?>"><?= $r['decision_finale'] ?></span>
        <?php else: ?>—<?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$concernes): ?>
    <tr><td colspan="6" class="text-center text-muted">Aucun étudiant en rattrapage pour ce module.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
<button type="submit" class="btn btn-primary">Enregistrer le rattrapage</button>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
