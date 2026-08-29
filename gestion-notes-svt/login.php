<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/classes/Repository.php';

if (utilisateurConnecte() !== null) {
    header('Location: index.php');
    exit;
}

$erreur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifiant = trim($_POST['identifiant'] ?? '');
    $motDePasse = $_POST['mot_de_passe'] ?? '';

    $repo = new Repository(getPDO());
    $u = $repo->utilisateurParIdentifiant($identifiant);

    if ($u && password_verify($motDePasse, $u['mot_de_passe'])) {
        $_SESSION['utilisateur'] = [
            'id' => $u['id'],
            'identifiant' => $u['identifiant'],
            'nom_complet' => $u['nom_complet'],
        ];
        header('Location: index.php');
        exit;
    }
    $erreur = "Identifiant ou mot de passe incorrect.";
}

$titrePage = 'Connexion';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center mt-5">
  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h1 class="h4 mb-3 text-center">Connexion</h1>
        <?php if ($erreur): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>
        <form method="post">
          <div class="mb-3">
            <label class="form-label">Identifiant</label>
            <input type="text" name="identifiant" class="form-control" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Mot de passe</label>
            <input type="password" name="mot_de_passe" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary w-100">Se connecter</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
