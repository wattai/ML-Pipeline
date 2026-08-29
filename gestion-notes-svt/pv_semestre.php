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

$titrePage = 'PV de semestre';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0">Procès-verbal — <?= htmlspecialchars($semestre['libelle']) ?> — <?= htmlspecialchars($annee['libelle']) ?></h1>
  <a class="btn btn-outline-secondary" href="export_pv_csv.php?annee_id=<?= $annee['id'] ?>&semestre_id=<?= $semestre['id'] ?>">
    Exporter en CSV
  </a>
</div>

<div class="table-responsive">
<table class="table table-bordered table-sm bg-white align-middle">
  <thead class="table-light">
    <tr>
      <th rowspan="2">N°</th>
      <th rowspan="2">Nom &amp; Prénom</th>
      <?php foreach ($modules as $m): ?>
        <th colspan="2" class="text-center"><?= htmlspecialchars($m['code']) ?></th>
      <?php endforeach; ?>
      <th rowspan="2" class="text-center">Moyenne<br>Semestre</th>
      <th rowspan="2" class="text-center">Modules<br>NV</th>
      <th rowspan="2" class="text-center">Décision<br>Semestre</th>
    </tr>
    <tr>
      <?php foreach ($modules as $m): ?>
        <th class="text-center small">Note</th><th class="text-center small">Déc.</th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($pv as $eid => $ligne): $e = $ligne['etudiant']; ?>
      <tr>
        <td><?= (int) ($e['numero_ordre'] ?? 0) ?></td>
        <td><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?></td>
        <?php foreach ($modules as $m): $r = $ligne['modules'][$m['id']] ?? []; ?>
          <td class="text-center"><?= $r['note_finale'] ?? ($r['moyenne'] ?? '—') ?></td>
          <td class="text-center">
            <?php $d = $r['decision_affichage'] ?? null; ?>
            <?php if ($d): ?><span class="badge badge-<?= $d ?>"><?= $d ?></span><?php else: ?>—<?php endif; ?>
          </td>
        <?php endforeach; ?>
        <td class="text-center fw-bold"><?= $ligne['moyenne_semestre'] ?? '—' ?></td>
        <td class="text-center"><?= $ligne['nb_modules_nv'] ?></td>
        <td class="text-center">
          <?php if ($ligne['decision_semestre']): ?>
            <span class="badge badge-<?= $ligne['decision_semestre'] ?>"><?= $ligne['decision_semestre'] ?></span>
          <?php else: ?>
            <span class="text-muted">en attente</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$pv): ?>
      <tr><td colspan="<?= 5 + count($modules) * 2 ?>" class="text-center text-muted">Aucun étudiant.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
</div>

<p class="text-muted small">
  Décisions module : V (validé), VAR (validé après rattrapage), NV (non validé, compensable),
  NE (non validé, éliminatoire). Décision semestre : V (validé, éventuellement par compensation —
  affichage VPC sur les modules NV concernés), R (à rattraper/ajourné).
  Règles détaillées dans <code>REGLES_GESTION.md</code>.
</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
