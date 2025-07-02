<?php

namespace App\Services;

use App\Models\RapportEtudiant;
use App\Models\Reclamation;
use App\Models\SectionRapport;
use App\Models\Approuver;
use App\Models\ConformiteRapportDetail;
use App\Models\VoteCommission;
use App\Models\CompteRendu;
use App\Models\SessionValidation;
use App\Models\SessionRapport;
use App\Models\Affecter;
use App\Models\StatutRapportRef; // Pour les statuts de rapport
use App\Models\CritereConformiteRef; // Pour les critères de conformité
use App\Models\RapportModele; // Pour les modèles de rapport
use App\Models\RapportModeleSection; // Pour les sections de modèle de rapport
use App\Utils\IdGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Exceptions\{ElementNotFoundException, OperationFailedException, PermissionDeniedException};

class DefenseWorkflowService
{
    protected $idGenerator;
    protected $communicationService;
    protected $documentService;
    protected $supervisionService;
    protected $systemService;
    protected $academicJourneyService;

    public function __construct(
        IdGenerator $idGenerator,
        CommunicationService $communicationService,
        DocumentService $documentService,
        SupervisionService $supervisionService,
        SystemService $systemService,
        AcademicJourneyService $academicJourneyService
    ) {
        $this->idGenerator = $idGenerator;
        $this->communicationService = $communicationService;
        $this->documentService = $documentService;
        $this->supervisionService = $supervisionService;
        $this->systemService = $systemService;
        $this->academicJourneyService = $academicJourneyService;
    }


    // ====================================================================
    // PHASE 1: GESTION DU RAPPORT PAR L'ÉTUDIANT
    // ====================================================================

    /**
     * Crée ou met à jour un brouillon de rapport étudiant.
     *
     * @param string $studentId L'ID de l'étudiant.
     * @param array $metadata Les métadonnées du rapport (libelle, theme, resume, nombre_pages).
     * @param array $sections Les sections du rapport (titre => contenu).
     * @return string L'ID du rapport créé ou mis à jour.
     * @throws OperationFailedException Si l'opération échoue.
     */
    public function createOrUpdateDraft(string $studentId, array $metadata, array $sections): string
    {
        return DB::transaction(function () use ($studentId, $metadata, $sections) {
            $draft = RapportEtudiant::where('numero_carte_etudiant', $studentId)
                ->where('id_statut_rapport', 'RAP_BROUILLON')
                ->first();

            $reportId = null;
            $metadata['date_derniere_modif'] = now();

            if ($draft) {
                $reportId = $draft->id_rapport_etudiant;
                $draft->update($metadata);
            } else {
                $reportId = $this->idGenerator->generateUniqueId('RAP');
                $metadata['id_rapport_etudiant'] = $reportId;
                $metadata['numero_carte_etudiant'] = $studentId;
                $metadata['id_statut_rapport'] = 'RAP_BROUILLON';
                RapportEtudiant::create($metadata);
            }

            // Supprimer les sections existantes avant de les recréer/mettre à jour
            SectionRapport::where('id_rapport_etudiant', $reportId)->delete();

            foreach ($sections as $title => $content) {
                SectionRapport::create([
                    'id_rapport_etudiant' => $reportId,
                    'titre_section' => $title,
                    'contenu_section' => $content,
                    'ordre' => 0, // L'ordre devrait être géré par l'étudiant/modèle
                    'date_creation' => now(),
                    'date_derniere_modif' => now(),
                ]);
            }

            return $reportId;
        });
    }

    /**
     * Soumet un rapport pour validation.
     *
     * @param string $reportId L'ID du rapport.
     * @param string $studentId L'ID de l'étudiant.
     * @return bool
     * @throws PermissionDeniedException Si l'étudiant n'est pas autorisé ou le rapport n'est pas un brouillon.
     * @throws OperationFailedException Si la soumission échoue.
     */
    public function submitReport(string $reportId, string $studentId): bool
    {
        $report = RapportEtudiant::find($reportId);
        if (!$report || $report->numero_carte_etudiant !== $studentId) {
            throw new PermissionDeniedException("Action non autorisée sur ce rapport.");
        }
        if ($report->id_statut_rapport !== 'RAP_BROUILLON') {
            throw new OperationFailedException("Seul un brouillon peut être soumis.");
        }

        // Vérifier l'éligibilité avant soumission
        if (!$this->academicJourneyService->isStudentEligibleForSubmission($studentId)) {
            throw new OperationFailedException("L'étudiant n'est pas éligible pour soumettre son rapport (paiement, stage, pénalités...).");
        }

        $this->supervisionService->recordAction($studentId, 'SOUMISSION_RAPPORT', $reportId, 'RapportEtudiant');
        return $this->changeReportStatus($reportId, 'RAP_SOUMIS', 'GRP_AGENT_CONFORMITE', 'NOUVEAU_RAPPORT_A_VERIFIER');
    }

    /**
     * Soumet les corrections demandées pour un rapport.
     *
     * @param string $reportId L'ID du rapport.
     * @param string $studentId L'ID de l'étudiant.
     * @param array $sections Les sections corrigées du rapport.
     * @param string $explanatoryNote La note explicative des corrections.
     * @return bool
     * @throws PermissionDeniedException Si l'étudiant n'est pas autorisé ou le rapport n'est pas en correction.
     * @throws OperationFailedException Si la soumission des corrections échoue.
     */
    public function submitCorrections(string $reportId, string $studentId, array $sections, string $explanatoryNote): bool
    {
        $report = RapportEtudiant::find($reportId);
        if (!$report || $report->numero_carte_etudiant !== $studentId) {
            throw new PermissionDeniedException("Action non autorisée sur ce rapport.");
        }
        if ($report->id_statut_rapport !== 'RAP_CORRECT') {
            throw new OperationFailedException("Ce rapport n'est pas en attente de corrections.");
        }

        // Mise à jour du contenu du rapport
        $this->createOrUpdateDraft($studentId, [], $sections); // Met à jour les sections du rapport

        $this->supervisionService->recordAction($studentId, 'SOUMISSION_CORRECTIONS', $reportId, 'RapportEtudiant', ['explanatory_note' => $explanatoryNote]);

        // Le rapport est automatiquement validé car il a été revu par le président
        return $this->changeReportStatus($reportId, 'RAP_VALID', null, 'RAPPORT_CORRIGE_ET_VALIDE');
    }

    /**
     * Lit un rapport complet avec ses sections, détails de conformité et votes.
     *
     * @param string $reportId L'ID du rapport.
     * @return RapportEtudiant|null
     */
    public function readCompleteReport(string $reportId): ?RapportEtudiant
    {
        return RapportEtudiant::with(['sections', 'conformiteRapportDetails', 'voteCommissions'])->find($reportId);
    }

    /**
     * Liste les rapports avec les détails de l'étudiant et le statut.
     *
     * @param array $filters Les filtres à appliquer.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listReports(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = RapportEtudiant::query()
            ->with(['etudiant', 'statutRapportRef']);

        foreach ($filters as $key => $value) {
            if (empty($value)) continue;
            if ($key === 'id_statut_rapport') {
                $query->where('id_statut_rapport', $value);
            } elseif ($key === 'votant') {
                // Filtrer les rapports où l'enseignant est affecté comme membre du jury
                $query->whereHas('affectations', function ($q) use ($value) {
                    $q->where('numero_enseignant', $value);
                });
            } else {
                $query->where($key, $value);
            }
        }

        return $query->get();
    }

    /**
     * Force le changement de statut d'un rapport par un administrateur.
     *
     * @param string $reportId L'ID du rapport.
     * @param string $newStatus Le nouveau statut.
     * @param string $adminId L'ID de l'administrateur.
     * @param string $justification La justification du changement.
     * @return bool
     * @throws ElementNotFoundException Si le rapport n'est pas trouvé.
     */
    public function forceReportStatusChange(string $reportId, string $newStatus, string $adminId, string $justification): bool
    {
        $report = RapportEtudiant::find($reportId);
        if (!$report) {
            throw new ElementNotFoundException("Rapport non trouvé.");
        }

        $oldStatus = $report->id_statut_rapport;
        $report->id_statut_rapport = $newStatus;
        $success = $report->save();

        if ($success) {
            $this->supervisionService->recordAction($adminId, 'FORCER_CHANGEMENT_STATUT_RAPPORT', $reportId, 'RapportEtudiant', ['old_status' => $oldStatus, 'new_status' => $newStatus, 'justification' => $justification]);
            $this->communicationService->sendInternalNotification($report->numero_carte_etudiant, 'STATUT_RAPPORT_FORCE', ['id_rapport' => $reportId, 'nouveau_statut' => $newStatus, 'justification' => $justification]);
        }
        return $success;
    }

    /**
     * Lit le rapport de l'étudiant pour l'année académique active.
     *
     * @param string $studentId L'ID de l'étudiant.
     * @return RapportEtudiant|null
     */
    public function readReportForActiveAcademicYear(string $studentId): ?RapportEtudiant
    {
        $activeYear = $this->systemService->getActiveAcademicYear();
        if (!$activeYear) {
            return null;
        }

        // Cherche le rapport le plus récent de l'étudiant pour l'année active
        // ou un rapport en brouillon/correction s'il existe
        return RapportEtudiant::where('numero_carte_etudiant', $studentId)
            ->whereIn('id_statut_rapport', ['RAP_BROUILLON', 'RAP_SOUMIS', 'RAP_NON_CONF', 'RAP_CONF', 'RAP_EN_COMMISSION', 'RAP_CORRECT', 'RAP_VALID', 'RAP_REFUSE'])
            ->orderByDesc('date_derniere_modif')
            ->first();
    }

    /**
     * Retourne les étapes du workflow pour un rapport donné, avec leur statut.
     *
     * @param string|null $reportId L'ID du rapport.
     * @return array
     */
    public function getWorkflowStepsForReport(?string $reportId): array
    {
        $steps = StatutRapportRef::orderBy('etape_workflow')->get();
        $currentReportStatus = null;
        if ($reportId) {
            $report = RapportEtudiant::find($reportId);
            $currentReportStatus = $report->id_statut_rapport ?? null;
        }

        $workflow = [];
        foreach ($steps as $step) {
            if (Str::startsWith($step->id_statut_rapport, 'RAP_') && $step->etape_workflow !== null) {
                $workflow[$step->etape_workflow] = [
                    'id' => $step->id_statut_rapport,
                    'label' => $step->libelle_statut_rapport,
                    'completed' => false,
                    'current' => false
                ];
            }
        }

        ksort($workflow);

        $completed = true;
        foreach ($workflow as $key => &$step) {
            if ($step['id'] === $currentReportStatus) {
                $step['current'] = true;
                $completed = false;
            }
            if ($completed) {
                $step['completed'] = true;
            }
        }
        unset($step);

        return array_values($workflow);
    }

    /**
     * Liste les modèles de rapport disponibles pour la création.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listAvailableReportModels(): \Illuminate\Database\Eloquent\Collection
    {
        return RapportModele::where('statut', 'Publié')->get();
    }

    /**
     * Crée un nouveau rapport pour un étudiant à partir d'un modèle.
     *
     * @param string $studentId L'ID de l'étudiant.
     * @param string $modelId L'ID du modèle de rapport.
     * @return string L'ID du nouveau rapport créé.
     * @throws ElementNotFoundException Si le modèle n'est pas trouvé.
     * @throws OperationFailedException Si la création échoue.
     */
    public function createReportFromModel(string $studentId, string $modelId): string
    {
        $model = RapportModele::find($modelId);
        if (!$model) {
            throw new ElementNotFoundException("Modèle de rapport '{$modelId}' non trouvé.");
        }

        $modelSections = RapportModeleSection::where('id_modele', $modelId)
            ->orderBy('ordre')
            ->get();

        $metadata = [
            'libelle_rapport_etudiant' => "Nouveau rapport basé sur le modèle : " . $model->nom_modele,
            'theme' => 'Thème à définir',
            'resume' => '<p>Résumé du rapport...</p>',
            'nombre_pages' => 0
        ];

        $sections = [];
        foreach ($modelSections as $section) {
            $sections[$section->titre_section] = $section->contenu_par_defaut ?? '';
        }

        return $this->createOrUpdateDraft($studentId, $metadata, $sections);
    }

    // ====================================================================
    // PHASE 2: VÉRIFICATION DE CONFORMITÉ
    // ====================================================================

    /**
     * Traite la vérification de conformité d'un rapport.
     *
     * @param string $reportId L'ID du rapport.
     * @param string $personnelId L'ID du personnel administratif.
     * @param bool $isConform Indique si le rapport est conforme.
     * @param array $checklistDetails Les détails de la checklist (id, statut, commentaire).
     * @param string|null $generalComment Le commentaire général de conformité.
     * @return bool
     * @throws OperationFailedException Si l'opération échoue.
     */
    public function processComplianceVerification(string $reportId, string $personnelId, bool $isConform, array $checklistDetails, ?string $generalComment): bool
    {
        return DB::transaction(function () use ($reportId, $personnelId, $isConform, $checklistDetails, $generalComment) {
            // Supprimer les anciens détails de conformité pour ce rapport avant de les recréer
            ConformiteRapportDetail::where('id_rapport_etudiant', $reportId)->delete();

            Approuver::create([
                'numero_personnel_administratif' => $personnelId,
                'id_rapport_etudiant' => $reportId,
                'id_statut_conformite' => $isConform ? 'CONF_OK' : 'CONF_NOK',
                'commentaire_conformite' => $generalComment,
                'date_verification_conformite' => now()
            ]);

            foreach ($checklistDetails as $criterion) {
                ConformiteRapportDetail::create([
                    'id_conformite_detail' => $this->idGenerator->generateUniqueId('CRD'),
                    'id_rapport_etudiant' => $reportId,
                    'id_critere' => $criterion['id'],
                    'statut_validation' => $criterion['statut'],
                    'commentaire' => $criterion['commentaire']
                ]);
            }

            if ($isConform) {
                return $this->changeReportStatus($reportId, 'RAP_CONF', 'GRP_COMMISSION', 'RAPPORT_CONFORME_A_EVALUER');
            } else {
                return $this->changeReportStatus($reportId, 'RAP_NON_CONF', null, 'CORRECTIONS_REQUISES');
            }
        });
    }

    // ====================================================================
    // PHASE 3: GESTION DE LA SESSION DE VALIDATION
    // ====================================================================

    /**
     * Crée une nouvelle session de validation.
     *
     * @param string $presidentId L'ID du président de session.
     * @param array $sessionData Les données de la session.
     * @return string L'ID de la session créée.
     * @throws OperationFailedException Si la création échoue.
     */
    public function createSession(string $presidentId, array $sessionData): string
    {
        $sessionId = $this->idGenerator->generateUniqueId('SESS');
        $sessionData['id_session'] = $sessionId;
        $sessionData['id_president_session'] = $presidentId;
        $sessionData['statut_session'] = 'planifiee';
        $sessionData['date_creation'] = now();

        if (!SessionValidation::create($sessionData)) {
            throw new OperationFailedException("Échec de la création de la session.");
        }
        return $sessionId;
    }

    /**
     * Modifie une session de validation existante.
     *
     * @param string $sessionId L'ID de la session.
     * @param array $data Les données à mettre à jour.
     * @return bool
     * @throws ElementNotFoundException Si la session n'est pas trouvée.
     * @throws OperationFailedException Si la session n'est pas planifiée.
     */
    public function modifySession(string $sessionId, array $data): bool
    {
        $session = SessionValidation::find($sessionId);
        if (!$session) {
            throw new ElementNotFoundException("Session non trouvée.");
        }
        if ($session->statut_session !== 'planifiee') {
            throw new OperationFailedException("Seule une session planifiée peut être modifiée.");
        }

        return $session->update($data);
    }

    /**
     * Compose une session en y associant des rapports.
     *
     * @param string $sessionId L'ID de la session.
     * @param array $reportIds Les IDs des rapports à associer.
     * @return bool
     * @throws OperationFailedException Si l'opération échoue.
     */
    public function composeSession(string $sessionId, array $reportIds): bool
    {
        return DB::transaction(function () use ($sessionId, $reportIds) {
            // Utilisation du Query Builder pour la suppression en masse sur clé composite
            DB::table('session_rapport')->where('id_session', $sessionId)->delete();

            foreach ($reportIds as $reportId) {
                // Utilisation du Query Builder pour l'insertion sur clé composite
                if (!DB::table('session_rapport')->insert([
                    'id_session' => $sessionId,
                    'id_rapport_etudiant' => $reportId
                ])) {
                    throw new OperationFailedException("Échec de l'association du rapport {$reportId} à la session {$sessionId}.");
                }
            }
            return true;
        });
    }

    /**
     * Démarre une session de validation.
     *
     * @param string $sessionId L'ID de la session.
     * @return bool
     * @throws ElementNotFoundException Si la session n'est pas trouvée.
     */
    public function startSession(string $sessionId): bool
    {
        $session = SessionValidation::find($sessionId);
        if (!$session) {
            throw new ElementNotFoundException("Session non trouvée.");
        }
        $session->statut_session = 'en_cours';
        return $session->save();
    }

    /**
     * Suspend une session de validation.
     *
     * @param string $sessionId L'ID de la session.
     * @return bool
     * @throws ElementNotFoundException Si la session n'est pas trouvée.
     */
    public function suspendSession(string $sessionId): bool
    {
        $session = SessionValidation::find($sessionId);
        if (!$session) {
            throw new ElementNotFoundException("Session non trouvée.");
        }
        $session->statut_session = 'suspendue';
        return $session->save();
    }

    /**
     * Reprend une session de validation suspendue.
     *
     * @param string $sessionId L'ID de la session.
     * @return bool
     * @throws ElementNotFoundException Si la session n'est pas trouvée.
     * @throws OperationFailedException Si la session n'est pas suspendue.
     */
    public function resumeSession(string $sessionId): bool
    {
        $session = SessionValidation::find($sessionId);
        if (!$session) {
            throw new ElementNotFoundException("Session non trouvée.");
        }
        if ($session->statut_session !== 'suspendue') {
            throw new OperationFailedException("Seule une session suspendue peut être reprise.");
        }
        $session->statut_session = 'en_cours';
        return $session->save();
    }

    /**
     * Clôture une session de validation.
     *
     * @param string $sessionId L'ID de la session.
     * @return bool
     * @throws ElementNotFoundException Si la session n'est pas trouvée.
     * @throws OperationFailedException Si des rapports sont encore en délibération.
     */
    public function closeSession(string $sessionId): bool
    {
        $session = SessionValidation::find($sessionId);
        if (!$session) {
            throw new ElementNotFoundException("Session non trouvée.");
        }

        $reportsInProgress = SessionRapport::where('id_session', $sessionId)
            ->whereHas('rapportEtudiant', function ($query) {
                $query->whereIn('id_statut_rapport', ['RAP_EN_COMMISSION']);
            })->exists();

        if ($reportsInProgress) {
            throw new OperationFailedException("Impossible de clôturer : des rapports sont encore en délibération.");
        }
        $session->statut_session = 'cloturee';
        return $session->save();
    }

    /**
     * Liste les sessions de validation pour la commission.
     *
     * @param array $filters Les filtres à appliquer.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listCommissionSessions(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = SessionValidation::query();

        foreach ($filters as $key => $value) {
            if (empty($value)) continue;
            $query->where($key, $value);
        }

        return $query->orderByDesc('date_creation')->get();
    }

    /**
     * Lit une session complète avec ses rapports associés.
     *
     * @param string $sessionId L'ID de la session.
     * @return SessionValidation|null
     */
    public function readCompleteSession(string $sessionId): ?SessionValidation
    {
        return SessionValidation::with(['rapportsEtudiant.etudiant', 'rapportsEtudiant.statutRapportRef'])->find($sessionId);
    }

    /**
     * Désigne un rapporteur pour un rapport.
     *
     * @param string $reportId L'ID du rapport.
     * @param string $reporterTeacherId L'ID de l'enseignant rapporteur.
     * @return bool
     * @throws OperationFailedException Si l'affectation échoue.
     */
    public function assignReporter(string $reportId, string $reporterTeacherId): bool
    {
        // Utilisation du Query Builder pour les clés composites de Affecter
        $existing = DB::table('affecter')
            ->where('numero_enseignant', $reporterTeacherId)
            ->where('id_rapport_etudiant', $reportId)
            ->where('id_statut_jury', 'JURY_RAPPORTEUR')
            ->first();

        if ($existing) {
            return true; // Déjà désigné
        }

        return (bool) DB::table('affecter')->insert([
            'numero_enseignant' => $reporterTeacherId,
            'id_rapport_etudiant' => $reportId,
            'id_statut_jury' => 'JURY_RAPPORTEUR',
            'directeur_memoire' => false,
            'date_affectation' => now()
        ]);
    }

    /**
     * Récuse un membre de la commission pour une session.
     *
     * @param string $sessionId L'ID de la session.
     * @param string $teacherId L'ID de l'enseignant.
     * @param string $justification La justification de la récusation.
     * @return bool
     */
    public function recuseMember(string $sessionId, string $teacherId, string $justification): bool
    {
        // Logique pour marquer un membre comme récusé pour une session spécifique.
        // Cela pourrait impliquer une table de liaison session_membre_recuse ou une mise à jour dans affecter.
        // Pour l'exemple, nous allons juste enregistrer l'action.
        $this->supervisionService->recordAction(
            Auth::id(),
            'RECUSATION_MEMBRE_COMMISSION',
            $sessionId,
            'SessionValidation',
            ['member_recused' => $teacherId, 'justification' => $justification]
        );
        return true;
    }

    // ====================================================================
    // PHASE 4: ÉVALUATION ET VOTE
    // ====================================================================

    /**
     * Enregistre le vote d'un membre de la commission pour un rapport.
     *
     * @param string $reportId L'ID du rapport.
     * @param string $sessionId L'ID de la session.
     * @param string $teacherId L'ID de l'enseignant votant.
     * @param string $decision La décision de vote.
     * @param string|null $comment Le commentaire associé au vote.
     * @return bool
     * @throws OperationFailedException Si l'enregistrement du vote échoue.
     */
    public function recordVote(string $reportId, string $sessionId, string $teacherId, string $decision, ?string $comment): bool
    {
        return DB::transaction(function () use ($reportId, $sessionId, $teacherId, $decision, $comment) {
            $currentTour = VoteCommission::where('id_rapport_etudiant', $reportId)
                ->max('tour_vote') ?? 1;

            $existingVote = VoteCommission::where('id_rapport_etudiant', $reportId)
                ->where('numero_enseignant', $teacherId)
                ->where('tour_vote', $currentTour)
                ->first();

            if ($existingVote) {
                $success = $existingVote->update([
                    'id_decision_vote' => $decision,
                    'commentaire_vote' => $comment,
                    'date_vote' => now()
                ]);
            } else {
                $voteId = $this->idGenerator->generateUniqueId('VOTE');
                $success = (bool) VoteCommission::create([
                    'id_vote' => $voteId,
                    'id_session' => $sessionId,
                    'id_rapport_etudiant' => $reportId,
                    'numero_enseignant' => $teacherId,
                    'id_decision_vote' => $decision,
                    'commentaire_vote' => $comment,
                    'date_vote' => now(),
                    'tour_vote' => $currentTour
                ]);
            }

            if ($success) {
                $this->verifyAndFinalizeVote($reportId, $sessionId);
                $this->supervisionService->recordAction($teacherId, 'ENREGISTREMENT_VOTE', $reportId, 'RapportEtudiant', ['decision' => $decision, 'session' => $sessionId, 'tour' => $currentTour]);
            }
            return $success;
        });
    }

    /**
     * Lance un nouveau tour de vote pour un rapport dans une session.
     *
     * @param string $reportId L'ID du rapport.
     * @param string $sessionId L'ID de la session.
     * @return bool
     */
    public function startNewVoteRound(string $reportId, string $sessionId): bool
    {
        $currentTour = VoteCommission::where('id_rapport_etudiant', $reportId)->max('tour_vote') ?? 1;
        $newTour = $currentTour + 1;

        $this->supervisionService->recordAction(Auth::id(), 'NOUVEAU_TOUR_VOTE', $reportId, 'RapportEtudiant', ['session' => $sessionId, 'tour' => $newTour]);
        // Le statut du rapport pourrait être mis à jour pour indiquer qu'il est en attente d'un nouveau tour de vote
        // $this->changeReportStatus($reportId, 'RAP_EN_COMMISSION', 'GRP_COMMISSION', 'NOUVEAU_TOUR_VOTE');
        return true;
    }

    /**
     * Consulte l'état des votes pour une session.
     *
     * @param string $sessionId L'ID de la session.
     * @return array
     */
    public function consultVoteStatus(string $sessionId): array
    {
        return VoteCommission::select('id_rapport_etudiant', 'id_decision_vote', DB::raw('COUNT(*) as count'))
            ->where('id_session', $sessionId)
            ->groupBy('id_rapport_etudiant', 'id_decision_vote')
            ->get()
            ->toArray();
    }

    // ====================================================================
    // PHASE 5: GESTION DES PROCÈS-VERBAUX (PV)
    // ====================================================================

    /**
     * Initie la rédaction d'un procès-verbal.
     *
     * @param string $sessionId L'ID de la session.
     * @param string $redactorId L'ID du rédacteur.
     * @return string L'ID du PV créé.
     * @throws OperationFailedException Si la création échoue.
     */
    public function initiatePvDraft(string $sessionId, string $redactorId): string
    {
        $pvId = $this->idGenerator->generateUniqueId('PV');
        if (!CompteRendu::create([
            'id_compte_rendu' => $pvId,
            'type_pv' => 'Session',
            'libelle_compte_rendu' => "PV de la session {$sessionId}",
            'id_statut_pv' => 'PV_BROUILLON',
            'id_redacteur' => $redactorId,
            'date_creation_pv' => now()
        ])) {
            throw new OperationFailedException("Échec de l'initiation du PV.");
        }
        return $pvId;
    }

    /**
     * Réassigne la rédaction d'un PV.
     *
     * @param string $pvId L'ID du PV.
     * @param string $newRedactorId L'ID du nouveau rédacteur.
     * @return bool
     * @throws ElementNotFoundException Si le PV n'est pas trouvé.
     */
    public function reassignPvRedaction(string $pvId, string $newRedactorId): bool
    {
        $pv = CompteRendu::find($pvId);
        if (!$pv) {
            throw new ElementNotFoundException("PV non trouvé.");
        }
        $pv->id_redacteur = $newRedactorId;
        return $pv->save();
    }

    /**
     * Met à jour le contenu d'un PV.
     *
     * @param string $pvId L'ID du PV.
     * @param string $content Le nouveau contenu du PV.
     * @return bool
     * @throws ElementNotFoundException Si le PV n'est pas trouvé.
     */
    public function updatePvContent(string $pvId, string $content): bool
    {
        $pv = CompteRendu::find($pvId);
        if (!$pv) {
            throw new ElementNotFoundException("PV non trouvé.");
        }
        $pv->libelle_compte_rendu = $content; // Assurez-vous que c'est la bonne colonne pour le contenu
        return $pv->save();
    }

    /**
     * Soumet un PV pour approbation.
     *
     * @param string $pvId L'ID du PV.
     * @return bool
     * @throws ElementNotFoundException Si le PV n'est pas trouvé.
     */
    public function submitPvForApproval(string $pvId): bool
    {
        $pv = CompteRendu::find($pvId);
        if (!$pv) {
            throw new ElementNotFoundException("PV non trouvé.");
        }
        $pv->id_statut_pv = 'PV_ATTENTE_APPROBATION';
        return $pv->save();
    }

    /**
     * Approuve un PV.
     *
     * @param string $pvId L'ID du PV.
     * @param string $presidentId L'ID du président qui approuve.
     * @return bool
     * @throws ElementNotFoundException If PV not found.
     */
    public function approvePv(string $pvId, string $presidentId): bool
    {
        $pv = CompteRendu::find($pvId);
        if (!$pv) {
            throw new ElementNotFoundException("PV non trouvé.");
        }
        $pv->id_statut_pv = 'PV_VALIDE';
        $success = $pv->save();

        if ($success) {
            $this->supervisionService->recordAction($presidentId, 'APPROBATION_PV', $pvId, 'CompteRendu');
        }
        return $success;
    }

    /**
     * Force la validation d'un PV par le président.
     *
     * @param string $pvId L'ID du PV.
     * @param string $presidentId L'ID du président.
     * @param string $justification La justification.
     * @return bool
     * @throws ElementNotFoundException Si le PV n'est pas trouvé.
     */
    public function forcePvValidation(string $pvId, string $presidentId, string $justification): bool
    {
        $pv = CompteRendu::find($pvId);
        if (!$pv) {
            throw new ElementNotFoundException("PV non trouvé.");
        }
        $pv->id_statut_pv = 'PV_VALIDE';
        $success = $pv->save();

        if ($success) {
            $this->supervisionService->recordAction($presidentId, 'FORCER_VALIDATION_PV', $pvId, 'CompteRendu', ['justification' => $justification]);
        }
        return $success;
    }

    /**
     * Liste les PV en attente d'approbation pour un utilisateur donné.
     *
     * @param string $userId L'ID de l'utilisateur.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listPendingPvForApproval(string $userId): \Illuminate\Database\Eloquent\Collection
    {
        // On suppose que seuls les membres de la commission peuvent approuver les PV
        // et que le statut est 'PV_ATTENTE_APPROBATION'
        return CompteRendu::where('id_statut_pv', 'PV_ATTENTE_APPROBATION')
            ->where('id_redacteur', '!=', $userId) // Exclure le rédacteur lui-même
            ->with('sessionValidation') // Si la relation existe
            ->get();
    }

    // ====================================================================
    // PHASE 6: FINALISATION POST-VALIDATION
    // ====================================================================

    /**
     * Désigne un directeur de mémoire pour un rapport.
     *
     * @param string $reportId L'ID du rapport.
     * @param string $directorTeacherId L'ID de l'enseignant directeur.
     * @return bool
     * @throws OperationFailedException Si l'affectation échoue.
     */
    public function assignThesisDirector(string $reportId, string $directorTeacherId): bool
    {
        // Utilisation du Query Builder pour les clés composites de Affecter
        $existing = DB::table('affecter')
            ->where('numero_enseignant', $directorTeacherId)
            ->where('id_rapport_etudiant', $reportId)
            ->where('directeur_memoire', true)
            ->first();

        if ($existing) {
            return true; // Déjà désigné
        }

        return (bool) DB::table('affecter')->insert([
            'numero_enseignant' => $directorTeacherId,
            'id_rapport_etudiant' => $reportId,
            'id_statut_jury' => 'JURY_DIRECTEUR', // Assurez-vous que ce statut existe dans statut_jury
            'directeur_memoire' => true,
            'date_affectation' => now()
        ]);
    }
    // ====================================================================
    // PHASE 7: GESTION DES RÉCLAMATIONS
    // ====================================================================

    /**
     * Crée une nouvelle réclamation.
     *
     * @param string $studentId L'ID de l'étudiant.
     * @param string $category La catégorie de la réclamation.
     * @param string $subject Le sujet de la réclamation.
     * @param string $description La description de la réclamation.
     * @return string L'ID de la réclamation créée.
     * @throws OperationFailedException Si la création échoue.
     */
    public function createComplaint(string $studentId, string $category, string $subject, string $description): string
    {
        $complaintId = $this->idGenerator->generateUniqueId('RECLA');
        if (!Reclamation::create([
            'id_reclamation' => $complaintId,
            'numero_carte_etudiant' => $studentId,
            'categorie_reclamation' => $category, // Assurez-vous que cette colonne existe dans votre table reclamation
            'sujet_reclamation' => $subject,
            'description_reclamation' => $description,
            'id_statut_reclamation' => 'RECLA_OUVERTE',
            'date_soumission' => now()
        ])) {
            throw new OperationFailedException("Échec de la création de la réclamation.");
        }

        $this->communicationService->sendGroupNotification('GRP_RS', 'NOUVELLE_RECLAMATION', ['sujet_reclamation' => $subject]);
        return $complaintId;
    }

    /**
     * Liste les réclamations en fonction de filtres.
     *
     * @param array $filters Les filtres à appliquer.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listComplaints(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Reclamation::query();

        foreach ($filters as $key => $value) {
            if (empty($value)) continue;
            $query->where($key, $value);
        }

        return $query->orderByDesc('date_soumission')->get();
    }

    /**
     * Lit une réclamation spécifique avec ses détails.
     *
     * @param string $complaintId L'ID de la réclamation.
     * @return Reclamation|null
     */
    public function readComplaint(string $complaintId): ?Reclamation
    {
        return Reclamation::with(['etudiant', 'statutReclamationRef', 'personnelTraitant'])->find($complaintId);
    }

    /**
     * Traite une réclamation en y apportant une réponse et en la résolvant.
     *
     * @param string $complaintId L'ID de la réclamation.
     * @param string $response La réponse à la réclamation.
     * @param string $personnelId L'ID du personnel traitant.
     * @return bool
     * @throws ElementNotFoundException Si la réclamation n'est pas trouvée.
     */
    public function processComplaint(string $complaintId, string $response, string $personnelId): bool
    {
        $complaint = Reclamation::find($complaintId);
        if (!$complaint) {
            throw new ElementNotFoundException("Réclamation non trouvée.");
        }

        $complaint->reponse_reclamation = $response;
        $complaint->date_reponse = now();
        $complaint->numero_personnel_traitant = $personnelId;
        $complaint->id_statut_reclamation = 'RECLA_RESOLUE';
        $success = $complaint->save();

        if ($success) {
            $this->communicationService->sendInternalNotification($complaint->numero_carte_etudiant, 'RECLAMATION_REPONDU', ['sujet_reclamation' => $complaint->sujet_reclamation]);
        }
        return $success;
    }

    /**
     * Permet de répondre à une réclamation sans forcément la clôturer, la marquant comme "en cours".
     *
     * @param string $complaintId L'ID de la réclamation.
     * @param string $response La réponse à la réclamation.
     * @param string $personnelId L'ID du personnel traitant.
     * @return bool
     * @throws ElementNotFoundException Si la réclamation n'est pas trouvée.
     */
    public function respondToComplaint(string $complaintId, string $response, string $personnelId): bool
    {
        $complaint = Reclamation::find($complaintId);
        if (!$complaint) {
            throw new ElementNotFoundException("Réclamation non trouvée.");
        }

        $complaint->reponse_reclamation = $response;
        $complaint->date_reponse = now();
        $complaint->numero_personnel_traitant = $personnelId;
        $complaint->id_statut_reclamation = 'RECLA_EN_COURS';
        $success = $complaint->save();

        if ($success) {
            $this->communicationService->sendInternalNotification($complaint->numero_carte_etudiant, 'RECLAMATION_REPONDU', ['sujet_reclamation' => $complaint->sujet_reclamation]);
        }
        return $success;
    }

    // --- Méthode privée utilitaire ---

    /**
     * Change le statut d'un rapport et envoie les notifications associées.
     *
     * @param string $reportId L'ID du rapport.
     * @param string $newStatus Le nouveau statut (ID de statut_rapport_ref).
     * @param string|null $groupToNotify Le groupe à notifier (ID de groupe_utilisateur).
     * @param string|null $notificationTemplate Le template de notification (ID de notification).
     * @return bool
     * @throws ElementNotFoundException Si le rapport n'est pas trouvé.
     */
    protected function changeReportStatus(string $reportId, string $newStatus, ?string $groupToNotify, ?string $notificationTemplate): bool
    {
        $report = RapportEtudiant::find($reportId);
        if (!$report) {
            throw new ElementNotFoundException("Rapport non trouvé.");
        }

        $oldStatus = $report->id_statut_rapport;
        $report->id_statut_rapport = $newStatus;
        $success = $report->save();

        if ($success) {
            $this->supervisionService->recordAction(Auth::id() ?? 'SYSTEM', 'CHANGEMENT_STATUT_RAPPORT', $reportId, 'RapportEtudiant', ['old_status' => $oldStatus, 'new_status' => $newStatus]);

            // Notification à l'étudiant
            $this->communicationService->sendInternalNotification($report->numero_carte_etudiant, 'STATUT_RAPPORT_MAJ', ['nouveau_statut' => $newStatus]);

            // Notification au groupe concerné
            if ($groupToNotify && $notificationTemplate) {
                $this->communicationService->sendGroupNotification($groupToNotify, $notificationTemplate, ['id_rapport' => $reportId]);
            }
        }
        return $success;
    }

    /**
     * Vérifie l'état des votes pour un rapport et finalise la décision si tous les votes sont là.
     *
     * @param string $reportId L'ID du rapport.
     * @param string $sessionId L'ID de la session.
     * @return void
     */
    protected function verifyAndFinalizeVote(string $reportId, string $sessionId): void
    {
        $session = SessionValidation::find($sessionId);
        if (!$session) return;

        $requiredVotersCount = (int) $session->nombre_votants_requis;
        $votes = VoteCommission::where('id_rapport_etudiant', $reportId)
            ->where('id_session', $sessionId)
            ->get();

        if ($votes->count() < $requiredVotersCount) return; // Pas assez de votes pour prendre une décision

        $decisionCounts = $votes->groupBy('id_decision_vote')->map->count();

        if ($decisionCounts->has('VOTE_REFUSE') && $decisionCounts->get('VOTE_REFUSE') > 0) {
            $this->changeReportStatus($reportId, 'RAP_REFUSE', null, 'RAPPORT_REFUSE');
        } elseif ($decisionCounts->has('VOTE_APPROUVE_RESERVE') && $decisionCounts->get('VOTE_APPROUVE_RESERVE') > 0) {
            $this->changeReportStatus($reportId, 'RAP_CORRECT', null, 'CORRECTIONS_REQUISES');
        } elseif ($decisionCounts->has('VOTE_APPROUVE') && $decisionCounts->get('VOTE_APPROUVE') === $requiredVotersCount) {
            $this->changeReportStatus($reportId, 'RAP_VALID', null, 'RAPPORT_VALIDE');
        }
    }
}
