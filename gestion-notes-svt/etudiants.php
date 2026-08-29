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

$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'creer') {
    try {
        $repo->creerEtudiant([
            'cin' => trim($_POST['cin'] ?? ''),
            'massar' => trim($_POST['massar'] ?? ''),
            'nom' => trim($_POST['nom'] ?? ''),
            'prenom' => trim($_POST['prenom'] ?? ''),
            'annee_universitaire_id' => (int) $annee['id'],
            'numero_ordre' => (int) ($_POST['numero_ordre'] ?? 0) ?: null,
        ]);
        $messages[] = ['type' => 'success', 'texte' => 'Étudiant ajouté.'];
    } catch (PDOException $e) {
        $messages[] = ['type' => 'danger', 'texte' => 'Erreur : code Massar déjà utilisé ou données invalides.'];
    }
}

if (($_GET['action'] ?? '') === 'desactiver' && isset($_GET['id'])) {
    $repo->desactiverEtudiant((int) $_GET['id']);
    header('Location: etudiants.php?annee_id=' . $annee['id']);
    exit;
}

$etudiants = $repo->etudiantsActifs((int) $annee['id']);

$titrePage = 'Étudiants';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-4">Étudiants — <?= htmlspecialchars($annee['libelle']) ?></h1>

<?php foreach ($messages as $m): ?>
  <div class="alert alert-<?= $m['type'] ?>"><?= htmlspecialchars($m['texte']) ?></div>
<?php endforeach; ?>

<div class="card mb-4">
  <div class="card-header">Ajouter un étudiant</div>
  <div class="card-body">
    <form method="post" class="row g-2">
      <input type="hidden" name="action" value="creer">
      <div class="col-md-1">
        <input type="number" name="numero_ordre" class="form-control" placeholder="N°">
      </div>
      <div class="col-md-2">
        <input type="text" name="cin" class="form-control" placeholder="CIN">
      </div>
      <div class="col-md-2">
        <input type="text" name="massar" class="form-control" placeholder="Massar / CNE" required>
      </div>
      <div class="col-md-2">
        <input type="text" name="nom" class="form-control" placeholder="Nom" required>
      </div>
      <div class="col-md-2">
        <input type="text" name="prenom" class="form-control" placeholder="Prénom" required>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">Ajouter</button>
      </div>
    </form>
  </div>
</div>

<table class="table table-striped bg-white">
  <thead>
    <tr>
      <th>N°</th><th>CIN</th><th>Massar / CNE</th><th>Nom</th><th>Prénom</th><th></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($etudiants as $e): ?>
      <tr>
        <td><?= (int) ($e['numero_ordre'] ?? 0) ?></td>
        <td><?= htmlspecialchars($e['cin'] ?? '') ?></td>
        <td><?= htmlspecialchars($e['massar']) ?></td>
        <td><?= htmlspecialchars($e['nom']) ?></td>
        <td><?= htmlspecialchars($e['prenom']) ?></td>
        <td>
          <a class="btn btn-sm btn-outline-danger"
             href="etudiants.php?action=desactiver&id=<?= $e['id'] ?>&annee_id=<?= $annee['id'] ?>"
             onclick="return confirm('Désactiver cet étudiant ?');">Désactiver</a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$etudiants): ?>
      <tr><td colspan="6" class="text-center text-muted">Aucun étudiant pour cette année universitaire.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<?php require __DIR__ . '/includes/footer.php'; ?>
