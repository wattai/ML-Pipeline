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
$nbEtudiants = count($repo->etudiantsActifs((int) $annee['id']));

$titrePage = 'Accueil';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-4">Tableau de bord</h1>

<form class="row g-2 mb-4 align-items-end" method="get">
  <div class="col-auto">
    <label class="form-label mb-0">Année universitaire</label>
    <select name="annee_id" class="form-select" onchange="this.form.submit()">
      <?php foreach ($repo->anneesUniversitaires() as $a): ?>
        <option value="<?= $a['id'] ?>" <?= (int) $a['id'] === (int) $annee['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($a['libelle']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <label class="form-label mb-0">Semestre</label>
    <select name="semestre_id" class="form-select" onchange="this.form.submit()">
      <?php foreach ($repo->semestres() as $s): ?>
        <option value="<?= $s['id'] ?>" <?= (int) $s['id'] === (int) $semestre['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($s['libelle']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
</form>

<div class="row mb-4">
  <div class="col-md-4">
    <div class="card text-bg-primary">
      <div class="card-body">
        <div class="fs-1 fw-bold"><?= $nbEtudiants ?></div>
        <div>Étudiants inscrits (<?= htmlspecialchars($annee['libelle']) ?>)</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card text-bg-secondary">
      <div class="card-body">
        <div class="fs-1 fw-bold"><?= count($modules) ?></div>
        <div>Modules — <?= htmlspecialchars($semestre['libelle']) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card text-bg-dark">
      <div class="card-body d-flex flex-column justify-content-center h-100">
        <a class="btn btn-light" href="pv_semestre.php?annee_id=<?= $annee['id'] ?>&semestre_id=<?= $semestre['id'] ?>">
          Voir le PV du semestre
        </a>
      </div>
    </div>
  </div>
</div>

<h2 class="h5 mb-3">Modules du semestre</h2>
<div class="row row-cols-1 row-cols-md-3 g-3">
  <?php foreach ($modules as $m): ?>
    <div class="col">
      <div class="card card-module h-100">
        <div class="card-body">
          <h3 class="h6"><?= htmlspecialchars($m['code']) ?> — <?= htmlspecialchars($m['intitule']) ?></h3>
          <a class="btn btn-sm btn-outline-primary me-1 mt-2"
             href="saisie_notes.php?module_id=<?= $m['id'] ?>&annee_id=<?= $annee['id'] ?>">Saisie des notes</a>
          <a class="btn btn-sm btn-outline-warning mt-2"
             href="saisie_rattrapage.php?module_id=<?= $m['id'] ?>&annee_id=<?= $annee['id'] ?>">Rattrapage</a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
