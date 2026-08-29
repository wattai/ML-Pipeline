<?php
declare(strict_types=1);

/**
 * Lecture des seuils configurables (table `parametres`).
 * Voir REGLES_GESTION.md §3.5 et §4.3.
 */
final class Parametres
{
    private static ?array $cache = null;

    private static function charger(PDO $pdo): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            $stmt = $pdo->query('SELECT cle, valeur FROM parametres');
            foreach ($stmt->fetchAll() as $row) {
                self::$cache[$row['cle']] = $row['valeur'];
            }
        }
        return self::$cache;
    }

    public static function seuilValidation(PDO $pdo): float
    {
        $p = self::charger($pdo);
        return isset($p['seuil_validation']) ? (float) $p['seuil_validation'] : SEUIL_VALIDATION_DEFAUT;
    }

    public static function seuilEliminatoire(PDO $pdo): float
    {
        $p = self::charger($pdo);
        return isset($p['seuil_eliminatoire']) ? (float) $p['seuil_eliminatoire'] : SEUIL_ELIMINATOIRE_DEFAUT;
    }

    public static function seuilCompensation(PDO $pdo): int
    {
        $p = self::charger($pdo);
        return isset($p['seuil_compensation']) ? (int) $p['seuil_compensation'] : SEUIL_COMPENSATION_DEFAUT;
    }
}
