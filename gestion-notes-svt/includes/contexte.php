<?php
declare(strict_types=1);

/**
 * Résout l'année universitaire et le semestre actifs à partir des paramètres
 * GET (?annee_id=&semestre_id=), avec des valeurs par défaut raisonnables
 * (année la plus récente, semestre S1).
 *
 * @return array{0:array,1:array} [annee, semestre]
 */
function resoudreContexte(Repository $repo): array
{
    $annees = $repo->anneesUniversitaires();
    $semestres = $repo->semestres();

    if (!$annees || !$semestres) {
        throw new RuntimeException("Aucune donnée de référence : exécutez sql/schema.sql puis sql/seed_s1_2024_2025.sql.");
    }

    $anneeId = isset($_GET['annee_id']) ? (int) $_GET['annee_id'] : (int) $annees[0]['id'];
    $semestreId = isset($_GET['semestre_id']) ? (int) $_GET['semestre_id'] : (int) $semestres[0]['id'];

    $annee = current(array_filter($annees, fn ($a) => (int) $a['id'] === $anneeId)) ?: $annees[0];
    $semestre = current(array_filter($semestres, fn ($s) => (int) $s['id'] === $semestreId)) ?: $semestres[0];

    return [$annee, $semestre];
}
