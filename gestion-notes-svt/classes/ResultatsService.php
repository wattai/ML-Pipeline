<?php
declare(strict_types=1);

require_once __DIR__ . '/CalculNotes.php';
require_once __DIR__ . '/Repository.php';
require_once __DIR__ . '/Parametres.php';

/**
 * Calcule les résultats (moyenne, décision, note finale) pour un module et
 * pour l'ensemble d'un semestre, en s'appuyant sur CalculNotes.
 */
final class ResultatsService
{
    private float $seuilValidation;
    private float $seuilEliminatoire;
    private int $seuilCompensation;

    public function __construct(private PDO $pdo, private Repository $repo)
    {
        $this->seuilValidation = Parametres::seuilValidation($pdo);
        $this->seuilEliminatoire = Parametres::seuilEliminatoire($pdo);
        $this->seuilCompensation = Parametres::seuilCompensation($pdo);
    }

    /**
     * Résultats d'un module simple (M1, M2, M3, M4, M5, M6) pour tous les
     * étudiants passés en paramètre.
     *
     * @param array $etudiants liste d'étudiants (id => ...)
     * @return array<int,array> indexé par etudiant_id
     */
    public function resultatsModuleSimple(array $module, array $etudiants): array
    {
        $poids = $this->repo->ponderationsModule((int) $module['id']);
        $notesNormale = $this->repo->notesModule((int) $module['id'], 'normale');
        $notesRattrapage = $this->repo->notesModule((int) $module['id'], 'rattrapage');

        $out = [];
        foreach ($etudiants as $e) {
            $eid = (int) $e['id'];
            $moyenne = CalculNotes::moyenneModule($poids, $notesNormale[$eid] ?? []);
            $decisionInit = CalculNotes::decisionInitiale($moyenne, $this->seuilValidation);
            $noteRattrapage = $notesRattrapage[$eid]['EXAM'] ?? null;
            $noteFinale = CalculNotes::noteFinale($moyenne, $decisionInit, $noteRattrapage, $this->seuilValidation);
            $decisionFinale = CalculNotes::decisionFinaleModule(
                $moyenne,
                $noteFinale,
                $this->seuilValidation,
                $this->seuilEliminatoire
            );

            $out[$eid] = [
                'notes' => $notesNormale[$eid] ?? [],
                'moyenne' => $moyenne,
                'decision_initiale' => $decisionInit,
                'note_rattrapage' => $noteRattrapage,
                'note_finale' => $noteFinale,
                'decision_finale' => $decisionFinale,
            ];
        }
        return $out;
    }

    /**
     * Résultats du module M7 (composé de 3 éléments, le 3e étant lui-même
     * la moyenne Langue Française / Langue Anglaise géré comme 2 "éléments
     * virtuels" côté saisie -- ici on suppose 3 éléments en base, le
     * troisième recevant directement la moyenne des 2 langues au moment de
     * la saisie, voir saisie_notes.php).
     */
    public function resultatsModuleAElements(array $module, array $etudiants): array
    {
        $elements = $this->repo->elementsDuModule((int) $module['id']);

        $moyennesParElement = [];
        foreach ($elements as $el) {
            $poids = $this->repo->ponderationsElement((int) $el['id']);
            $notes = $this->repo->notesElement((int) $el['id'], 'normale');
            foreach ($etudiants as $e) {
                $eid = (int) $e['id'];
                $moyennesParElement[$eid][$el['id']] = CalculNotes::moyenneModule($poids, $notes[$eid] ?? []);
            }
        }

        $notesRattrapage = $this->repo->notesModule((int) $module['id'], 'rattrapage');

        $out = [];
        foreach ($etudiants as $e) {
            $eid = (int) $e['id'];
            $moyenne = CalculNotes::moyenneModuleAElements($moyennesParElement[$eid] ?? []);
            $decisionInit = CalculNotes::decisionInitiale($moyenne, $this->seuilValidation);
            $noteRattrapage = $notesRattrapage[$eid]['EXAM'] ?? null;
            $noteFinale = CalculNotes::noteFinale($moyenne, $decisionInit, $noteRattrapage, $this->seuilValidation);
            $decisionFinale = CalculNotes::decisionFinaleModule(
                $moyenne,
                $noteFinale,
                $this->seuilValidation,
                $this->seuilEliminatoire
            );

            $out[$eid] = [
                'moyennes_elements' => $moyennesParElement[$eid] ?? [],
                'moyenne' => $moyenne,
                'decision_initiale' => $decisionInit,
                'note_rattrapage' => $noteRattrapage,
                'note_finale' => $noteFinale,
                'decision_finale' => $decisionFinale,
            ];
        }
        return $out;
    }

    public function resultatsModule(array $module, array $etudiants): array
    {
        return ((int) $module['a_elements']) === 1
            ? $this->resultatsModuleAElements($module, $etudiants)
            : $this->resultatsModuleSimple($module, $etudiants);
    }

    /**
     * PV du semestre : pour chaque étudiant, le détail des résultats de
     * chaque module + la synthèse du semestre.
     *
     * @param array $modules  modules du semestre, ordonnés
     * @param array $etudiants
     * @return array<int,array> indexé par etudiant_id
     */
    public function pvSemestre(array $modules, array $etudiants): array
    {
        $resultatsParModule = [];
        foreach ($modules as $module) {
            $resultatsParModule[$module['id']] = $this->resultatsModule($module, $etudiants);
        }

        $pv = [];
        foreach ($etudiants as $e) {
            $eid = (int) $e['id'];

            $notesFinales = [];
            $decisions = [];
            $detailModules = [];
            foreach ($modules as $module) {
                $r = $resultatsParModule[$module['id']][$eid] ?? null;
                $notesFinales[] = $r['note_finale'] ?? null;
                $decisions[] = $r['decision_finale'] ?? null;
                $detailModules[$module['id']] = $r;
            }

            $moyenneSemestre = CalculNotes::moyenneSemestre($notesFinales);
            $decisionSemestre = CalculNotes::decisionSemestre(
                $moyenneSemestre,
                $decisions,
                $this->seuilValidation,
                $this->seuilCompensation
            );
            $nbModulesNV = CalculNotes::nbModulesNV($decisions);

            foreach ($modules as $module) {
                $r = $detailModules[$module['id']];
                $detailModules[$module['id']]['decision_affichage'] =
                    CalculNotes::decisionAffichagePv($r['decision_finale'] ?? null, $decisionSemestre);
            }

            $pv[$eid] = [
                'etudiant' => $e,
                'modules' => $detailModules,
                'moyenne_semestre' => $moyenneSemestre,
                'nb_modules_nv' => $nbModulesNV,
                'decision_semestre' => $decisionSemestre,
            ];
        }

        return $pv;
    }
}
