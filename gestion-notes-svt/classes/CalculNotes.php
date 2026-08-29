<?php
declare(strict_types=1);

/**
 * Moteur de calcul des notes, décisions de module et décisions de semestre.
 *
 * Toutes les règles implémentées ici sont documentées dans REGLES_GESTION.md.
 * Fonctions pures (pas d'accès base de données) afin de rester testables et
 * réutilisables (saisie, PV, export...).
 */
final class CalculNotes
{
    /**
     * Moyenne d'un module/élément à partir de ses notes de composantes.
     *
     * @param array<string,float> $poids  ex: ['TP' => 0.2, 'CC' => 0.2, 'EXAM' => 0.6]
     * @param array<string,?float> $notes ex: ['TP' => 12.0, 'CC' => 16.0, 'EXAM' => null]
     * @return float|null null si aucune composante n'est renseignée (pas encore noté)
     */
    public static function moyenneModule(array $poids, array $notes): ?float
    {
        $auMoinsUneNote = false;
        $total = 0.0;

        foreach ($poids as $type => $p) {
            $valeur = $notes[$type] ?? null;
            if ($valeur !== null) {
                $auMoinsUneNote = true;
                $total += $valeur * $p;
            }
            // Une composante non saisie compte pour 0 dans la moyenne, comme
            // dans le classeur d'origine (cellule vide = 0 dans une somme Excel),
            // tant qu'au moins une composante du module est renseignée.
        }

        return $auMoinsUneNote ? round($total, 2) : null;
    }

    /**
     * Moyenne du module M7, moyenne simple des moyennes de ses éléments.
     *
     * @param array<int,?float> $moyennesElements
     */
    public static function moyenneModuleAElements(array $moyennesElements): ?float
    {
        $valeurs = array_filter($moyennesElements, static fn ($v) => $v !== null);
        if (count($valeurs) === 0) {
            return null;
        }
        return round(array_sum($valeurs) / count($valeurs), 2);
    }

    /**
     * Moyenne d'un sous-élément "Langue" = moyenne de Langue Française et Langue Anglaise.
     */
    public static function moyenneLangue(?float $noteFrancaise, ?float $noteAnglaise): ?float
    {
        return self::moyenneModuleAElements([$noteFrancaise, $noteAnglaise]);
    }

    public static function decisionInitiale(?float $moyenne, float $seuilValidation): ?string
    {
        if ($moyenne === null) {
            return null;
        }
        return $moyenne >= $seuilValidation ? 'V' : 'R';
    }

    /**
     * Note finale après rattrapage.
     *
     * NB : le classeur d'origine contient un bug qui ignore la note de
     * rattrapage saisie (voir REGLES_GESTION.md §2.2). Ce calcul l'implémente
     * correctement : la note de rattrapage est bien prise en compte.
     */
    public static function noteFinale(
        ?float $moyenne,
        ?string $decisionInitiale,
        ?float $noteRattrapage,
        float $seuilValidation
    ): ?float {
        if ($moyenne === null) {
            return null;
        }
        if ($decisionInitiale === 'V') {
            return $moyenne;
        }
        if ($noteRattrapage === null) {
            // Décision "R" mais pas (encore) de note de rattrapage saisie :
            // on conserve la moyenne de la session normale.
            return $moyenne;
        }

        return round(min($seuilValidation, max($moyenne, $noteRattrapage)), 2);
    }

    /**
     * Décision finale d'un module : V / VAR / NV / NE.
     */
    public static function decisionFinaleModule(
        ?float $moyenne,
        ?float $noteFinale,
        float $seuilValidation,
        float $seuilEliminatoire
    ): ?string {
        if ($moyenne === null) {
            return null;
        }
        if ($moyenne >= $seuilValidation) {
            return 'V';
        }
        if ($noteFinale === null) {
            return null;
        }
        if ($noteFinale >= $seuilValidation) {
            return 'VAR';
        }
        if ($noteFinale >= $seuilEliminatoire) {
            return 'NV';
        }
        return 'NE';
    }

    /**
     * Moyenne du semestre : moyenne arithmétique simple des notes finales des modules.
     *
     * @param array<int,?float> $notesFinalesModules
     */
    public static function moyenneSemestre(array $notesFinalesModules): ?float
    {
        $valeurs = array_filter($notesFinalesModules, static fn ($v) => $v !== null);
        if (count($valeurs) === 0) {
            return null;
        }
        return round(array_sum($valeurs) / count($valeurs), 2);
    }

    /**
     * Nombre de modules "problématiques" (NV, NE, ABS, NC).
     * Reproduit la fonction VBA CompterValeursCommunes.
     *
     * @param array<int,?string> $decisionsModules
     */
    public static function nbModulesNV(array $decisionsModules): int
    {
        $codes = ['NV', 'NE', 'ABS', 'NC'];
        $n = 0;
        foreach ($decisionsModules as $d) {
            if ($d !== null && in_array($d, $codes, true)) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * Décision du semestre : V ou R, selon la moyenne et la compensation.
     *
     * @param array<int,?string> $decisionsModules
     */
    public static function decisionSemestre(
        ?float $moyenneSemestre,
        array $decisionsModules,
        float $seuilValidation,
        int $seuilCompensation
    ): ?string {
        if ($moyenneSemestre === null || in_array(null, $decisionsModules, true)) {
            // semestre pas encore entièrement noté
            return null;
        }

        $nbNV = self::nbModulesNV($decisionsModules);
        $hasNE = in_array('NE', $decisionsModules, true);

        if ($moyenneSemestre >= $seuilValidation && $nbNV <= $seuilCompensation && !$hasNE) {
            return 'V';
        }
        return 'R';
    }

    /**
     * Décision affichée pour un module donné dans le PV de semestre :
     * si le module est NV/NE mais que le semestre est validé, il est
     * requalifié "VPC" (Validé Par Compensation).
     */
    public static function decisionAffichagePv(?string $decisionModule, ?string $decisionSemestre): ?string
    {
        if ($decisionModule === null) {
            return null;
        }
        if (in_array($decisionModule, ['NV', 'NE'], true) && $decisionSemestre === 'V') {
            return 'VPC';
        }
        return $decisionModule;
    }
}
