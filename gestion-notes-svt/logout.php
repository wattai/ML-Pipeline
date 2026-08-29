<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;
