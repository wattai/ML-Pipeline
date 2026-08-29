<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
exigerConnexion();

require_once __DIR__ . '/classes/Repository.php';
require_once __DIR__ . '/includes/contexte.php';

$pdo = getPDO();
$repo = new Repository($pdo);
[$annee, $semestre] = resoudreContexte($repo);
$modules = $repo->modulesDuSemestre((int) $semestre['id']);

$titrePage = 'Modules';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-4">Modules — <?= htmlspecialchars($semestre['libelle']) ?></h1>

<table class="table table-striped bg-white">
  <thead>
    <tr><th>Code</th><th>Intitulé</th><th>Pondérations</th><th></th></tr>
  </thead>
  <tbody>
    <?php foreach ($modules as $m): ?>
      <tr>
        <td><?= htmlspecialchars($m['code']) ?></td>
        <td><?= htmlspecialchars($m['intitule']) ?></td>
        <td>
          <?php if ((int) $m['a_elements'] === 1): ?>
            <span class="text-muted">3 éléments (CC 40% / Examen 60% chacun)</span>
          <?php else: ?>
            <?php foreach ($repo->ponderationsModule((int) $m['id']) as $type => $poids): ?>
              <span class="badge text-bg-secondary"><?= $type ?> <?= $poids * 100 ?>%</span>
            <?php endforeach; ?>
          <?php endif; ?>
        </td>
        <td>
          <a class="btn btn-sm btn-outline-primary" href="saisie_notes.php?module_id=<?= $m['id'] ?>&annee_id=<?= $annee['id'] ?>">Saisie</a>
          <a class="btn btn-sm btn-outline-warning" href="saisie_rattrapage.php?module_id=<?= $m['id'] ?>&annee_id=<?= $annee['id'] ?>">Rattrapage</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php require __DIR__ . '/includes/footer.php'; ?>
