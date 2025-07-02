<?php

namespace App\Http\Controllers\AdministrativePersonnel;

use App\Http\Controllers\Controller;
use App\Services\DefenseWorkflowService;
use App\Services\UserService;
use App\Services\AcademicJourneyService;
use App\Services\SystemService;
use App\Services\DocumentService;
use App\Services\SecurityService;
use App\Services\SupervisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Exceptions\{ElementNotFoundException, OperationFailedException, ValidationException};

class SchoolingController extends Controller
{
    protected $defenseWorkflowService;
    protected $userService;
    protected $academicJourneyService;
    protected $systemService;
    protected $documentService;
    protected $securityService;
    protected $supervisionService;

    public function __construct(
        DefenseWorkflowService $defenseWorkflowService,
        UserService            $userService,
        AcademicJourneyService $academicJourneyService,
        SystemService          $systemService,
        DocumentService        $documentService,
        SecurityService        $securityService,
        SupervisionService     $supervisionService
    )
    {
        $this->defenseWorkflowService = $defenseWorkflowService;
        $this->userService = $userService;
        $this->academicJourneyService = $academicJourneyService;
        $this->systemService = $systemService;
        $this->documentService = $documentService;
        $this->securityService = $securityService;
        $this->supervisionService = $supervisionService;
    }

    // ========== PARTIE AGENT DE CONFORMITÉ ==========

    /**
     * Affiche la file d'attente des rapports pour vérification de conformité.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function complianceQueue(Request $request)
    {
        $this->authorize('TRAIT_PERS_ADMIN_CONFORMITE_LISTER');

        try {
            $reports = $this->defenseWorkflowService->listReports(['id_statut_rapport' => 'RAP_SOUMIS']);
            return view('AdministrativePersonnel.gestion_conformite', [
                'title' => 'File de Vérification de Conformité',
                'reports' => $reports
            ]);
        } catch (\Exception $e) {
            Log::error("Erreur lors du chargement de la file de conformité: " . $e->getMessage());
            return redirect()->route('administrative-personnel.dashboard')->with('error', 'Erreur lors du chargement de la file de conformité.');
        }
    }

    /**
     * Affiche le formulaire de vérification de conformité pour un rapport.
     *
     * @param string $reportId L'ID du rapport.
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showCompliance(string $reportId)
    {
        $this->authorize('TRAIT_PERS_ADMIN_CONFORMITE_VERIFIER');

        try {
            $report = $this->defenseWorkflowService->readCompleteReport($reportId);
            if (!$report) {
                throw new ElementNotFoundException("Rapport non trouvé.");
            }
            $checklist = $this->systemService->manageReferential('list', 'critere_conformite_ref');

            return view('AdministrativePersonnel.form_conformite', [
                'title' => 'Vérification du Rapport ' . $reportId,
                'report' => $report,
                'checklist' => $checklist,
            ]);
        } catch (ElementNotFoundException $e) {
            return redirect()->route('administrative-personnel.compliance.queue')->with('error', 'Erreur lors du chargement du formulaire de conformité : ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors du chargement du formulaire de conformité pour le rapport {$reportId}: " . $e->getMessage());
            return redirect()->route('administrative-personnel.compliance.queue')->with('error', 'Une erreur inattendue est survenue.');
        }
    }

    /**
     * Traite la soumission de la vérification de conformité d'un rapport.
     *
     * @param Request $request L'objet requête HTTP.
     * @param string $reportId L'ID du rapport.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processCompliance(Request $request, string $reportId)
    {
        $this->authorize('TRAIT_PERS_ADMIN_CONFORMITE_VERIFIER');

        $request->validate([
            'decision_conformite' => 'required|in:conforme,non_conforme',
            'commentaire_general' => 'required|string|max:1000',
            'checklist' => 'array',
            'checklist.*.id' => 'required|string',
            'checklist.*.statut' => 'required|in:Conforme,Non Conforme,Non Applicable',
            'checklist.*.commentaire' => 'nullable|string|max:500',
        ]);

        try {
            $personnelId = Auth::id();
            $isConform = ($request->input('decision_conformite') === 'conforme');
            $checklistDetails = $request->input('checklist', []);
            $generalComment = $request->input('commentaire_general');

            $this->defenseWorkflowService->processComplianceVerification($reportId, $personnelId, $isConform, $checklistDetails, $generalComment);
            return redirect()->route('administrative-personnel.compliance.queue')->with('success', 'La vérification de conformité a été enregistrée.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->getErrors())->withInput();
        } catch (ElementNotFoundException|OperationFailedException $e) {
            return back()->with('error', 'Erreur lors du traitement de la conformité: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors du traitement de la conformité pour le rapport {$reportId}: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue lors du traitement de la conformité.');
        }
    }

    // ========== PARTIE RESPONSABLE SCOLARITÉ (RS) ==========

    /**
     * Affiche la liste des dossiers étudiants pour le RS.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function index(Request $request)
    {
        $this->authorize('TRAIT_PERS_ADMIN_SCOLARITE_ACCEDER');

        try {
            $students = $this->userService->listCompleteUsers(['id_type_utilisateur' => 'TYPE_ETUD']);

            return view('AdministrativePersonnel.schooling_management', [
                'title' => 'Gestion des Dossiers Étudiants',
                'students' => $students,
            ]);
        } catch (\Exception $e) {
            Log::error("Erreur lors du chargement des dossiers étudiants: " . $e->getMessage());
            return redirect()->route('administrative-personnel.dashboard')->with('error', 'Erreur lors du chargement des dossiers étudiants.');
        }
    }

    /**
     * Affiche les détails d'un étudiant spécifique.
     *
     * @param string $studentId L'ID de l'étudiant.
     * @return \Illuminate\View\View|\Illuminate\Http\JsonResponse
     */
    public function showStudent(string $studentId)
    {
        $this->authorize('TRAIT_PERS_ADMIN_SCOLARITE_ACCEDER');

        try {
            $data = [
                'profile' => $this->userService->readCompleteUser($studentId),
                'inscriptions' => $this->academicJourneyService->listInscriptions(['numero_carte_etudiant' => $studentId]),
                'notes' => $this->academicJourneyService->listNotes(['numero_carte_etudiant' => $studentId]),
                'stages' => $this->academicJourneyService->listStages(['numero_carte_etudiant' => $studentId]),
                'penalites' => $this->academicJourneyService->listPenalites(['numero_carte_etudiant' => $studentId]),
                'reclamations' => $this->defenseWorkflowService->listComplaints(['numero_carte_etudiant' => $studentId])
            ];
            // Retourne une vue partielle pour un chargement AJAX
            return view('AdministrativePersonnel._student_details_panel', $data);
        } catch (ElementNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            Log::error("Erreur lors du chargement des détails de l'étudiant {$studentId}: " . $e->getMessage());
            return response()->json(['error' => 'Erreur lors du chargement des détails de l\'étudiant : ' . $e->getMessage()], 500);
        }
    }

    /**
     * Active le compte d'un étudiant.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function activateAccount(Request $request)
    {
        $this->authorize('TRAIT_PERS_ADMIN_SCOLARITE_ETUDIANT_GERER');

        $request->validate([
            'numero_etudiant' => 'required|string',
            'login_utilisateur' => 'required|string|max:100',
            'email_principal' => 'required|email|max:255',
            'mot_de_passe' => 'required|string|min:8',
            'id_groupe_utilisateur' => 'required|string', // Devrait être GRP_ETUDIANT
            'id_niveau_acces_donne' => 'required|string', // Devrait être ACCES_PERSONNEL
        ]);

        try {
            $this->userService->activateAccountForEntity($request->numero_etudiant, $request->all());
            return back()->with('success', "Compte de l'étudiant {$request->numero_etudiant} activé avec succès.");
        } catch (DuplicateEntryException|ElementNotFoundException|OperationFailedException $e) {
            return back()->with('error', 'Erreur lors de l\'activation du compte : ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'activation du compte étudiant {$request->numero_etudiant}: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue lors de l\'activation du compte.');
        }
    }

    /**
     * Gère la mise à jour du statut d'inscription (paiement).
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleInscriptionUpdate(Request $request)
    {
        $this->authorize('TRAIT_PERS_ADMIN_SCOLARITE_ETUDIANT_GERER');

        $request->validate([
            'numero_etudiant' => 'required|string',
            'id_niveau' => 'required|string',
            'id_annee' => 'required|string',
            'statut' => 'required|string', // id_statut_paiement
        ]);

        try {
            $this->academicJourneyService->updateInscription(
                $request->numero_etudiant,
                $request->id_niveau,
                $request->id_annee,
                ['id_statut_paiement' => $request->statut]
            );
            return back()->with('success', 'Statut de paiement mis à jour.');
        } catch (ElementNotFoundException|OperationFailedException $e) {
            return back()->with('error', 'Erreur: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de la mise à jour de l'inscription: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue.');
        }
    }

    /**
     * Gère l'enregistrement ou la mise à jour d'une note.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleNoteEntry(Request $request)
    {
        $this->authorize('TRAIT_PERS_ADMIN_SCOLARITE_ETUDIANT_GERER');

        $request->validate([
            'numero_carte_etudiant' => 'required|string',
            'id_ecue' => 'required|string',
            'id_annee_academique' => 'required|string',
            'note' => 'required|numeric|min:0|max:20',
        ]);

        try {
            $this->academicJourneyService->createOrUpdateNote($request->all());
            return back()->with('success', 'Note enregistrée avec succès.');
        } catch (OperationFailedException $e) {
            return back()->with('error', 'Erreur: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'enregistrement de la note: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue.');
        }
    }

    /**
     * Valide un stage.
     *
     * @param Request $request L'objet requête HTTP.
     * @param string $studentNumber Le numéro de carte étudiant.
     * @param string $companyId L'ID de l'entreprise.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function validateInternship(Request $request, string $studentNumber, string $companyId)
    {
        $this->authorize('TRAIT_PERS_ADMIN_SCOLARITE_ETUDIANT_GERER');

        try {
            $this->academicJourneyService->validateStage($studentNumber, $companyId);
            return back()->with('success', 'Stage validé.');
        } catch (\Exception $e) {
            Log::error("Erreur lors de la validation du stage pour {$studentNumber} chez {$companyId}: " . $e->getMessage());
            return back()->with('error', 'Erreur: ' . $e->getMessage());
        }
    }

    /**
     * Régularise une pénalité.
     *
     * @param Request $request L'objet requête HTTP.
     * @param string $penaltyId L'ID de la pénalité.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function regularizePenalty(Request $request, string $penaltyId)
    {
        $this->authorize('TRAIT_PERS_ADMIN_SCOLARITE_PENALITE_GERER');

        try {
            $personnelId = Auth::id();
            $this->academicJourneyService->regularizePenalite($penaltyId, $personnelId);
            return back()->with('success', 'Pénalité régularisée.');
        } catch (ElementNotFoundException|OperationFailedException $e) {
            return back()->with('error', 'Erreur: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de la régularisation de la pénalité {$penaltyId}: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue.');
        }
    }

    /**
     * Gère la réponse à une réclamation.
     *
     * @param Request $request L'objet requête HTTP.
     * @param string $complaintId L'ID de la réclamation.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleComplaintResponse(Request $request, string $complaintId)
    {
        $this->authorize('TRAIT_PERS_ADMIN_RECLAMATIONS_GERER');

        $request->validate([
            'reponse' => 'required|string|max:1000',
        ]);

        try {
            $personnelId = Auth::id();
            $this->defenseWorkflowService->respondToComplaint($complaintId, $request->reponse, $personnelId);
            return back()->with('success', 'Réponse envoyée.');
        } catch (ElementNotFoundException|OperationFailedException $e) {
            return back()->with('error', 'Erreur: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de la réponse à la réclamation {$complaintId}: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue.');
        }
    }

    /**
     * Clôture une réclamation.
     *
     * @param Request $request L'objet requête HTTP.
     * @param string $complaintId L'ID de la réclamation.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function closeComplaint(Request $request, string $complaintId)
    {
        $this->authorize('TRAIT_PERS_ADMIN_RECLAMATIONS_GERER');

        try {
            $personnelId = Auth::id();
            $this->defenseWorkflowService->processComplaint($complaintId, "Réclamation résolue et clôturée.", $personnelId);
            return back()->with('success', 'Réclamation clôturée.');
        } catch (ElementNotFoundException|OperationFailedException $e) {
            return back()->with('error', 'Erreur: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de la clôture de la réclamation {$complaintId}: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue.');
        }
    }

    /**
     * Exporte la liste des étudiants au format spécifié (PDF ou CSV).
     *
     * @param string $format Le format d'exportation ('pdf' ou 'csv').
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function exportStudents(string $format)
    {
        $this->authorize('TRAIT_PERS_ADMIN_SCOLARITE_ACCEDER');

        try {
            $students = $this->userService->listCompleteUsers(['id_type_utilisateur' => 'TYPE_ETUD']);
            $columns = ['numero_utilisateur' => 'Matricule', 'nom' => 'Nom', 'prenom' => 'Prénom', 'email_principal' => 'Email', 'statut_compte' => 'Statut'];

            if ($format === 'pdf') {
                $documentId = $this->documentService->generatePdfList('Liste des Etudiants', $students->toArray(), $columns);
                // Rediriger vers la route de service de fichier pour télécharger le PDF
                return redirect()->route('assets.serve', ['filePath' => DocumentGenere::find($documentId)->chemin_fichier])->with('success', 'Liste des étudiants exportée en PDF.');
            } elseif ($format === 'csv') {
                return $this->generateCsvResponse('etudiants', $students->toArray(), $columns);
            } else {
                return back()->with('error', 'Format d\'export non supporté.');
            }
        } catch (OperationFailedException $e) {
            return back()->with('error', "Erreur lors de l'export : " . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'export des étudiants au format {$format}: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue lors de l\'export.');
        }
    }

    /**
     * Génère une réponse CSV pour le téléchargement.
     *
     * @param string $filename Le nom du fichier CSV.
     * @param array $data Les données à exporter.
     * @param array $columns Les colonnes à inclure (clé_db => Libellé).
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    protected function generateCsvResponse(string $filename, array $data, array $columns)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '_' . now()->format('Ymd_His') . '.csv"',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($data, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, array_values($columns)); // En-têtes

            foreach ($data as $row) {
                $csvRow = [];
                foreach (array_keys($columns) as $key) {
                    $csvRow[] = $row[$key] ?? '';
                }
                fputcsv($file, $csvRow);
            }
            fclose($file);
        }, 200, $headers);
    }
}
