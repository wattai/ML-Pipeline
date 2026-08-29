<?php
declare(strict_types=1);

/**
 * Accès aux données et orchestration du calcul des résultats.
 * Les résultats (moyennes, décisions) ne sont jamais stockés : ils sont
 * recalculés à la volée par CalculNotes à partir des notes brutes, pour
 * garantir qu'il n'existe qu'une seule source de vérité (les notes saisies).
 */
final class Repository
{
    public function __construct(private PDO $pdo)
    {
    }

    // ---------------------------------------------------------------- refs

    public function anneesUniversitaires(): array
    {
        return $this->pdo->query('SELECT * FROM annees_universitaires ORDER BY libelle DESC')->fetchAll();
    }

    public function semestres(): array
    {
        return $this->pdo->query('SELECT * FROM semestres ORDER BY ordre')->fetchAll();
    }

    public function modulesDuSemestre(int $semestreId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM modules WHERE semestre_id = ? ORDER BY ordre');
        $stmt->execute([$semestreId]);
        return $stmt->fetchAll();
    }

    public function module(int $moduleId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM modules WHERE id = ?');
        $stmt->execute([$moduleId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function elementsDuModule(int $moduleId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM elements_module WHERE module_id = ? ORDER BY ordre');
        $stmt->execute([$moduleId]);
        return $stmt->fetchAll();
    }

    /** @return array<string,float> ex ['TP'=>0.2,'CC'=>0.2,'EXAM'=>0.6] */
    public function ponderationsModule(int $moduleId): array
    {
        $stmt = $this->pdo->prepare('SELECT type_composante, poids FROM ponderations WHERE module_id = ?');
        $stmt->execute([$moduleId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['type_composante']] = (float) $row['poids'];
        }
        return $out;
    }

    public function ponderationsElement(int $elementId): array
    {
        $stmt = $this->pdo->prepare('SELECT type_composante, poids FROM ponderations WHERE element_id = ?');
        $stmt->execute([$elementId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['type_composante']] = (float) $row['poids'];
        }
        return $out;
    }

    // ------------------------------------------------------------ etudiants

    public function etudiantsActifs(int $anneeUniversitaireId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM etudiants WHERE annee_universitaire_id = ? AND actif = 1
             ORDER BY numero_ordre, nom, prenom'
        );
        $stmt->execute([$anneeUniversitaireId]);
        return $stmt->fetchAll();
    }

    public function etudiant(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM etudiants WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function creerEtudiant(array $d): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO etudiants (cin, massar, nom, prenom, annee_universitaire_id, numero_ordre)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $d['cin'] ?: null, $d['massar'], $d['nom'], $d['prenom'],
            $d['annee_universitaire_id'], $d['numero_ordre'] ?: null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function modifierEtudiant(int $id, array $d): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE etudiants SET cin = ?, massar = ?, nom = ?, prenom = ?, numero_ordre = ? WHERE id = ?'
        );
        $stmt->execute([$d['cin'] ?: null, $d['massar'], $d['nom'], $d['prenom'], $d['numero_ordre'] ?: null, $id]);
    }

    public function desactiverEtudiant(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE etudiants SET actif = 0 WHERE id = ?');
        $stmt->execute([$id]);
    }

    // ----------------------------------------------------------------- notes

    /**
     * @return array<int,array{TP:?float,CC:?float,EXAM:?float}> indexé par etudiant_id
     */
    public function notesModule(int $moduleId, string $session = 'normale'): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT etudiant_id, type_composante, valeur FROM notes
             WHERE module_id = ? AND session_evaluation = ?'
        );
        $stmt->execute([$moduleId, $session]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['etudiant_id']][$row['type_composante']] = $row['valeur'] !== null ? (float) $row['valeur'] : null;
        }
        return $out;
    }

    public function notesElement(int $elementId, string $session = 'normale'): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT etudiant_id, type_composante, valeur FROM notes
             WHERE element_id = ? AND session_evaluation = ?'
        );
        $stmt->execute([$elementId, $session]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['etudiant_id']][$row['type_composante']] = $row['valeur'] !== null ? (float) $row['valeur'] : null;
        }
        return $out;
    }

    public function enregistrerNoteModule(
        int $etudiantId,
        int $moduleId,
        string $typeComposante,
        ?float $valeur,
        string $session = 'normale'
    ): void {
        $this->upsertNote($etudiantId, $moduleId, null, $typeComposante, $valeur, $session);
    }

    public function enregistrerNoteElement(
        int $etudiantId,
        int $elementId,
        string $typeComposante,
        ?float $valeur,
        string $session = 'normale'
    ): void {
        $this->upsertNote($etudiantId, null, $elementId, $typeComposante, $valeur, $session);
    }

    private function upsertNote(
        ?int $etudiantId,
        ?int $moduleId,
        ?int $elementId,
        string $typeComposante,
        ?float $valeur,
        string $session
    ): void {
        $sql = $moduleId !== null
            ? 'SELECT id FROM notes WHERE etudiant_id = ? AND module_id = ? AND type_composante = ? AND session_evaluation = ?'
            : 'SELECT id FROM notes WHERE etudiant_id = ? AND element_id = ? AND type_composante = ? AND session_evaluation = ?';
        $cible = $moduleId ?? $elementId;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$etudiantId, $cible, $typeComposante, $session]);
        $existant = $stmt->fetch();

        if ($existant) {
            $upd = $this->pdo->prepare('UPDATE notes SET valeur = ? WHERE id = ?');
            $upd->execute([$valeur, $existant['id']]);
            return;
        }

        if ($valeur === null) {
            return; // rien à insérer
        }

        $ins = $this->pdo->prepare(
            'INSERT INTO notes (etudiant_id, module_id, element_id, type_composante, session_evaluation, valeur)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$etudiantId, $moduleId, $elementId, $typeComposante, $session, $valeur]);
    }

    // -------------------------------------------------------------- utilisateurs

    public function utilisateurParIdentifiant(string $identifiant): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM utilisateurs WHERE identifiant = ?');
        $stmt->execute([$identifiant]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
