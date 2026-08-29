<?php
declare(strict_types=1);
/** @var string $titrePage */
$titrePage = $titrePage ?? APP_NOM;
$user = utilisateurConnecte();
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($titrePage) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php if ($user): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">SVT — Gestion des notes</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav1">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav1">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="index.php">Accueil</a></li>
        <li class="nav-item"><a class="nav-link" href="etudiants.php">Étudiants</a></li>
        <li class="nav-item"><a class="nav-link" href="modules.php">Modules</a></li>
        <li class="nav-item"><a class="nav-link" href="pv_semestre.php">PV de semestre</a></li>
      </ul>
      <span class="navbar-text text-light me-3"><?= htmlspecialchars($user['nom_complet'] ?? $user['identifiant']) ?></span>
      <a class="btn btn-outline-light btn-sm" href="logout.php">Déconnexion</a>
    </div>
  </div>
</nav>
<?php endif; ?>
<main class="container pb-5">
