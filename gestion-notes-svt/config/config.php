<?php
declare(strict_types=1);

// Connexion base de données (adapter à votre environnement de déploiement)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'gestion_notes_svt');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Seuils par défaut (surchargés par la table `parametres`, voir classes/Parametres.php)
define('SEUIL_VALIDATION_DEFAUT', 10.0);
define('SEUIL_ELIMINATOIRE_DEFAUT', 7.0);
define('SEUIL_COMPENSATION_DEFAUT', 2);

define('APP_NOM', "Gestion des notes — Licence d'Éducation SVT");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Africa/Casablanca');
