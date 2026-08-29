<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function utilisateurConnecte(): ?array
{
    return $_SESSION['utilisateur'] ?? null;
}

function exigerConnexion(): void
{
    if (utilisateurConnecte() === null) {
        header('Location: login.php');
        exit;
    }
}
