<?php

namespace App\Services;

use App\Models\Inscrire;
use App\Models\Evaluer;
use App\Models\FaireStage;
use App\Models\Penalite;
use App\Models\Ue;
use App\Models\Ecue;
use App\Models\AnneeAcademique; // Ajouté pour les relations
use App\Models\Etudiant; // Ajouté pour les relations
use App\Utils\IdGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Exceptions\{ElementNotFoundException, OperationFailedException, DuplicateEntryException};

class AcademicJourneyService
{
    protected $idGenerator;
    protected $supervisionService;
    protected $systemService;

    public function __construct(
        IdGenerator $idGenerator,
        SupervisionService $supervisionService,
        SystemService $systemService
    ) {
        $this->idGenerator = $idGenerator;
        $this->supervisionService = $supervisionService;
        $this->systemService = $systemService;
    }

    // --- CRUD Inscriptions ---

    /**
     * Crée une nouvelle inscription.
     *
     * @param array $data Les données de l'inscription.
     * @return bool
     * @throws DuplicateEntryException Si l'inscription existe déjà.
     * @throws OperationFailedException Si la création échoue.
     */
    public function createInscription(array $data): bool
    {
        // Vérifier si une inscription similaire existe déjà pour éviter les doublons logiques
        if (Inscrire::where('numero_carte_etudiant', $data['numero_carte_etudiant'])
            ->where('id_niveau_etude', $data['id_niveau_etude'])
            ->where('id_annee_academique', $data['id_annee_academique'])
            ->exists()) {
            throw new DuplicateEntryException("L'étudiant est déjà inscrit à ce niveau pour cette année académique.");
        }

        $data['date_inscription'] = now();
        if (!Inscrire::create($data)) {
            throw new OperationFailedException("Échec de la création de l'inscription.");
        }
        return true;
    }

    /**
     * Lit une inscription spécifique.
     *
     * @param string $studentId L'ID de l'étudiant.
     * @param string $levelId L'ID du niveau d'étude.
     * @param string $yearId L'ID de l'année académique.
     * @return Inscrire|null
     */
    public function readInscription(string $studentId, string $levelId, string $yearId): ?Inscrire
    {
        // Utilisation du Query Builder pour les clés composites si nécessaire, ou Eloquent avec where
        return Inscrire::where('numero_carte_etudiant', $studentId)
            ->where('id_niveau_etude', $levelId)
            ->where('id_annee_academique', $yearId)
            ->first();
    }

    /**
     * Met à jour une inscription existante.
     *
     * @param string $studentId L'ID de l'étudiant.
     * @param string $levelId L'ID du niveau d'étude.
     * @param string $yearId L'ID de l'année académique.
     * @param array $data Les données à mettre à jour.
     * @return bool
     * @throws ElementNotFoundException Si l'inscription n'est pas trouvée.
     */
    public function updateInscription(string $studentId, string $levelId, string $yearId, array $data): bool
    {
        $inscription = $this->readInscription($studentId, $levelId, $yearId);
        if (!$inscription) {
            throw new ElementNotFoundException("Inscription non trouvée.");
        }
        return $inscription->update($data);
    }

    /**
     * Supprime une inscription.
     *
     * @param string $studentId L'ID de l'étudiant.
     * @param string $levelId L'ID du niveau d'étude.
     * @param string $yearId L'ID de l'année académique.
     * @return bool
     * @throws OperationFailedException Si la suppression échoue.
     */
    public function deleteInscription(string $studentId, string $levelId, string $yearId): bool
    {
        $inscription = $this->readInscription($studentId, $levelId, $yearId);
        if (!$inscription) {
            return false; // Ou lancer ElementNotFoundException
        }
        return $inscription->delete();
    }

    /**
     * Liste les inscriptions en fonction de filtres.
     *
     * @param array $filters Les filtres à appliquer.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listInscriptions(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Inscrire::query();
        foreach ($filters as $key => $value) {
            if (empty($value)) continue;
            $query->where($key, $value);
        }
        return $query->get();
    }

    // --- CRUD Notes ---

    /**
     * Crée ou met à jour une note pour un ECUE.
     *
     * @param array $data Les données de la note (numero_carte_etudiant, id_ecue, id_annee_academique, note).
     * @return bool
     * @throws OperationFailedException Si l'opération échoue.
     */
    public function createOrUpdateNote(array $data): bool
    {
        $existingNote = Evaluer::where('numero_carte_etudiant', $data['numero_carte_etudiant'])
            ->where('id_ecue', $data['id_ecue'])
            ->where('id_annee_academique', $data['id_annee_academique'])
            ->first();

        if ($existingNote) {
            return $existingNote->update(['note' => $data['note']]);
        } else {
            $data['date_evaluation'] = now();
            if (!Evaluer::create($data)) {
                throw new OperationFailedException("Échec de la création de la note.");
            }
            return true;
        }
    }

    /**
     * Lit une note spécifique.
     *
     * @param string $studentId L'ID de l'étudiant.
     * @param string $ecueId L'ID de l'ECUE.
     * @param string $yearId L'ID de l'année académique.
     * @return Evaluer|null
     */
    public function readNote(string $studentId, string $ecueId, string $yearId): ?Evaluer
    {
        return Evaluer::where('numero_carte_etudiant', $studentId)
            ->where('id_ecue', $ecueId)
            ->where('id_annee_academique', $yearId)
            ->first();
    }

    /**
     * Supprime une note.
     *
     * @param string $studentId L'ID de l'étudiant.
     * @param string $ecueId L'ID de l'ECUE.
     * @param string $yearId L'ID de l'année académique.
     * @return bool
     */
    public function deleteNote(string $studentId, string $ecueId, string $yearId): bool
    {
        $note = $this->readNote($studentId, $ecueId, $yearId);
        if (!$note) {
            return false;
        }
        return $note->delete();
    }

    /**
     * Liste les notes en fonction de filtres.
     *
     * @param array $filters Les filtres à appliquer.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listNotes(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Evaluer::query();
        foreach ($filters as $key => $value) {
            if (empty($value)) continue;
            $query->where($key, $value);
        }
        return $query->get();
    }

    // --- CRUD Stages ---

    /**
     * Crée un nouveau stage.
     *
     * @param array $data Les données du stage.
     * @return bool
     * @throws DuplicateEntryException Si le stage existe déjà.
     * @throws OperationFailedException Si la création échoue.
     */
    public function createStage(array $data): bool
    {
        if (FaireStage::where('numero_carte_etudiant', $data['numero_carte_etudiant'])
            ->where('id_entreprise', $data['id_entreprise'])
            ->exists()) {
            throw new DuplicateEntryException("Cet étudiant a déjà un stage enregistré avec cette entreprise.");
        }
        if (!FaireStage::create($data)) {
            throw new OperationFailedException("Échec de la création du stage.");
        }
        return true;
    }

    /**
     * Lit un stage spécifique.
     *
     * @param string $studentId L'ID de l'étudiant.
     * @param string $companyId L'ID de l'entreprise.
     * @return FaireStage|null
     */
    public function readStage(string $studentId, string $companyId): ?FaireStage
    {
        return FaireStage::where('numero_carte_etudiant', $studentId)
            ->where('id_entreprise', $companyId)
            ->first();
    }

    /**
     * Met à jour un stage existant.
     *
     * @param string $studentId L'ID de l'étudiant.
     * @param string $companyId L'ID de l'entreprise.
     * @param array $data Les données à mettre à jour.
     * @return bool
     * @throws ElementNotFoundException Si le stage n'est pas trouvé.
     */
    public function updateStage(string $studentId, string $companyId, array $data): bool
    {
        $stage = $this->readStage($studentId, $companyId);
        if (!$stage) {
            throw new ElementNotFoundException("Stage non trouvé.");
        }
        return $stage->update($data);
    }

    /**
     * Supprime un stage.
     *
     * @param string $studentId L'ID de l'étudiant.
     * @param string $companyId L'ID de l'entreprise.
     * @return bool
     */
    public function deleteStage(string $studentId, string $companyId): bool
    {
        $stage = $this->readStage($studentId, $companyId);
        if (!$stage) {
            return false;
        }
        return $stage->delete();
    }

    /**
     * Valide un stage.
     *
     * @param string $studentId L'ID de l'étudiant.
     * @param string $companyId L'ID de l'entreprise.
     * @return bool
     */
    public function validateStage(string $studentId, string $companyId): bool
    {
        // Ici, vous pourriez ajouter une colonne 'est_valide' à la table faire_stage
        // ou simplement enregistrer l'action.
        $this->supervisionService->recordAction(Auth::id() ?? 'SYSTEM', 'VALIDATION_STAGE', $studentId, 'Etudiant', ['company_id' => $companyId]);
        return true;
    }

    /**
     * Liste les stages en fonction de filtres.
     *
     * @param array $filters Les filtres à appliquer.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listStages(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = FaireStage::query();
        foreach ($filters as $key => $value) {
            if (empty($value)) continue;
            $query->where($key, $value);
        }
        return $query->get();
    }

    // --- CRUD Pénalités ---

    /**
     * Crée une nouvelle pénalité.
     *
     * @param array $data Les données de la pénalité.
     * @return string L'ID de la pénalité créée.
     * @throws OperationFailedException Si la création échoue.
     */
    public function createPenalite(array $data): string
    {
        $data['id_penalite'] = $this->idGenerator->generateUniqueId('PEN');
        $data['id_statut_penalite'] = 'PEN_DUE';
        $data['date_creation'] = now();

        if (!Penalite::create($data)) {
            throw new OperationFailedException("Échec de la création de la pénalité.");
        }
        return $data['id_penalite'];
    }

    /**
     * Lit une pénalité spécifique.
     *
     * @param string $penaltyId L'ID de la pénalité.
     * @return Penalite|null
     */
    public function readPenalite(string $penaltyId): ?Penalite
    {
        return Penalite::find($penaltyId);
    }

    /**
     * Met à jour une pénalité existante.
     *
     * @param string $penaltyId L'ID de la pénalité.
     * @param array $data Les données à mettre à jour.
     * @return bool
     * @throws ElementNotFoundException Si la pénalité n'est pas trouvée.
     */
    public function updatePenalite(string $penaltyId, array $data): bool
    {
        $penalty = Penalite::find($penaltyId);
        if (!$penalty) {
            throw new ElementNotFoundException("Pénalité non trouvée.");
        }
        return $penalty->update($data);
    }

    /**
     * Régularise une pénalité.
     *
     * @param string $penaltyId L'ID de la pénalité.
     * @param string $personnelId L'ID du personnel traitant.
     * @return bool
     * @throws ElementNotFoundException Si la pénalité n'est pas trouvée.
     */
    public function regularizePenalite(string $penaltyId, string $personnelId): bool
    {
        $penalty = Penalite::find($penaltyId);
        if (!$penalty) {
            throw new ElementNotFoundException("Pénalité non trouvée.");
        }
        return $penalty->update([
            'id_statut_penalite' => 'PEN_REGLEE',
            'date_regularisation' => now(),
            'numero_personnel_traitant' => $personnelId
        ]);
    }

    /**
     * Liste les pénalités en fonction de filtres.
     *
     * @param array $filters Les filtres à appliquer.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listPenalites(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Penalite::query();
        foreach ($filters as $key => $value) {
            if (empty($value)) continue;
            $query->where($key, $value);
        }
        return $query->get();
    }

    // --- Logique Métier ---

    /**
     * Vérifie si un étudiant est éligible pour soumettre son rapport.
     *
     * @param string $studentId L'ID de l'étudiant.
     * @return bool
     */
    public function isStudentEligibleForSubmission(string $studentId): bool
    {
        $activeYear = $this->systemService->getActiveAcademicYear();
        if (!$activeYear) {
            return false;
        }

        // Vérifier l'inscription et le statut de paiement pour l'année active
        $latestInscription = Inscrire::where('numero_carte_etudiant', $studentId)
            ->where('id_annee_academique', $activeYear->id_annee_academique)
            ->orderByDesc('date_inscription')
            ->first();

        if (!$latestInscription || $latestInscription->id_statut_paiement !== 'PAIE_OK') {
            return false;
        }

        // Vérifier si un stage est enregistré (et validé si une colonne de validation existe)
        $stage = FaireStage::where('numero_carte_etudiant', $studentId)->first();
        if (!$stage /* || !$stage->is_validated */) { // Décommenter si vous avez une colonne de validation de stage
            return false;
        }

        // Vérifier les pénalités non réglées
        $pendingPenalties = Penalite::where('numero_carte_etudiant', $studentId)
            ->where('id_statut_penalite', 'PEN_DUE')
            ->exists();
        if ($pendingPenalties) {
            return false;
        }

        return true;
    }

    /**
     * Enregistre la décision de passage pour un étudiant.
     *
     * @param string $studentId L'ID de l'étudiant.
     * @param string $yearId L'ID de l'année académique.
     * @param string $decisionId L'ID de la décision de passage.
     * @return bool
     * @throws ElementNotFoundException Si l'inscription n'est pas trouvée.
     * @throws OperationFailedException Si l'opération échoue.
     */
    public function recordPassageDecision(string $studentId, string $yearId, string $decisionId): bool
    {
        $inscription = Inscrire::where('numero_carte_etudiant', $studentId)
            ->where('id_annee_academique', $yearId)
            ->first();
        if (!$inscription) {
            throw new ElementNotFoundException("Aucune inscription trouvée pour cet étudiant pour l'année spécifiée.");
        }

        return DB::transaction(function () use ($inscription, $decisionId, $studentId, $yearId) {
            $inscription->id_decision_passage = $decisionId;
            if (!$inscription->save()) {
                throw new OperationFailedException("Échec de l'enregistrement de la décision de passage.");
            }

            if ($decisionId === 'DEC_REDOUBLANT') {
                $currentYearLabel = $inscription->anneeAcademique->libelle_annee_academique;
                $startYear = (int) substr($currentYearLabel, 0, 4);
                $nextYearLabel = ($startYear + 1) . '-' . ($startYear + 2);
                $nextAcademicYearId = "ANNEE-{$nextYearLabel}";

                // Assurez-vous que l'année académique suivante existe ou créez-la
                $nextAcademicYear = AnneeAcademique::find($nextAcademicYearId);
                if (!$nextAcademicYear) {
                    // Logique pour créer la prochaine année si elle n'existe pas
                    $this->systemService->createAcademicYear($nextYearLabel, now()->addYear()->startOfYear()->toDateString(), now()->addYear()->endOfYear()->toDateString());
                }

                $this->createInscription([
                    'numero_carte_etudiant' => $studentId,
                    'id_niveau_etude' => $inscription->id_niveau_etude,
                    'id_annee_academique' => $nextAcademicYearId,
                    'montant_inscription' => $inscription->montant_inscription,
                    'id_statut_paiement' => 'PAIE_ATTENTE',
                    'id_decision_passage' => null
                ]);
            }

            $this->supervisionService->recordAction(Auth::id(), 'ENREGISTREMENT_DECISION_PASSAGE', $studentId, 'Etudiant', ['decision' => $decisionId, 'year' => $yearId]);
            return true;
        });
    }

    /**
     * Calcule les moyennes d'un étudiant pour une année académique donnée.
     *
     * @param string $studentId L'ID de l'étudiant.
     * @param string $yearId L'ID de l'année académique.
     * @return array Contenant la moyenne générale, les crédits validés et les détails par UE.
     */
    public function calculateGrades(string $studentId, string $yearId): array
    {
        $notes = Evaluer::where('numero_carte_etudiant', $studentId)
            ->where('id_annee_academique', $yearId)
            ->with(['ecue.ue']) // Charger les relations ECUE et UE
            ->get();

        if ($notes->isEmpty()) {
            return ['general_average' => 0, 'validated_credits' => 0, 'ue_details' => []];
        }

        $ueAverages = [];
        $totalWeightedSum = 0;
        $totalCredits = 0;
        $totalValidatedCredits = 0;

        foreach ($notes as $note) {
            $ecue = $note->ecue;
            $ue = $ecue->ue;

            if (!isset($ueAverages[$ue->id_ue])) {
                $ueAverages[$ue->id_ue] = [
                    'label' => $ue->libelle_ue,
                    'total_weighted_notes' => 0,
                    'total_ecue_credits' => 0,
                    'ue_credits' => (float) $ue->credits_ue,
                    'average' => 0
                ];
            }
            $ueAverages[$ue->id_ue]['total_weighted_notes'] += (float) $note->note * (float) $ecue->credits_ecue;
            $ueAverages[$ue->id_ue]['total_ecue_credits'] += (float) $ecue->credits_ecue;
        }

        foreach ($ueAverages as $ueId => &$ueData) {
            if ($ueData['total_ecue_credits'] > 0) {
                $ueData['average'] = $ueData['total_weighted_notes'] / $ueData['total_ecue_credits'];
            }

            $totalWeightedSum += $ueData['average'] * $ueData['ue_credits'];
            $totalCredits += $ueData['ue_credits'];

            if ($ueData['average'] >= 10.0) { // Critère de validation de l'UE
                $totalValidatedCredits += $ueData['ue_credits'];
            }
        }
        unset($ueData); // Rompre la référence

        $generalAverage = ($totalCredits > 0) ? $totalWeightedSum / $totalCredits : 0;

        return [
            'general_average' => round($generalAverage, 2),
            'validated_credits' => $totalValidatedCredits,
            'ue_details' => array_values($ueAverages)
        ];
    }
}
