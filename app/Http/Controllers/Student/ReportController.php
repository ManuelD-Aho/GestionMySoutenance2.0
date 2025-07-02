<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\DefenseWorkflowService;
use App\Services\SecurityService;
use App\Services\SupervisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Exceptions\{ElementNotFoundException, OperationFailedException, PermissionDeniedException, ValidationException};

/**
 * Gère la création, la rédaction, la sauvegarde et la soumission du rapport de l'étudiant.
 */
class ReportController extends Controller
{
    protected $defenseWorkflowService;
    protected $securityService;
    protected $supervisionService;

    public function __construct(
        DefenseWorkflowService $defenseWorkflowService,
        SecurityService $securityService,
        SupervisionService $supervisionService
    ) {
        $this->defenseWorkflowService = $defenseWorkflowService;
        $this->securityService = $securityService;
        $this->supervisionService = $supervisionService;

        // Appliquer le middleware de permission pour l'accès aux rapports étudiants
        $this->middleware('can:TRAIT_ETUDIANT_RAPPORT_SUIVRE');
    }

    /**
     * Point d'entrée pour la gestion du rapport.
     * Redirige vers le choix du modèle si aucun rapport n'existe, sinon vers l'éditeur.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function edit()
    {
        $user = Auth::user();

        try {
            $report = $this->defenseWorkflowService->readReportForActiveAcademicYear($user->numero_utilisateur);

            if ($report) {
                return redirect()->route('student.report.show', $report->id_rapport_etudiant);
            } else {
                $models = $this->defenseWorkflowService->listAvailableReportModels();
                return view('Student.choix_modele', [
                    'title' => 'Choisir un Modèle de Rapport',
                    'models' => $models,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Erreur lors du chargement de la page de rédaction de rapport pour {$user->numero_utilisateur}: " . $e->getMessage());
            return redirect()->route('student.dashboard')->with('error', 'Erreur lors du chargement de la page de rédaction.');
        }
    }

    /**
     * Crée un rapport à partir d'un modèle choisi et redirige vers l'éditeur.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function create(Request $request)
    {
        $this->authorize('TRAIT_ETUDIANT_RAPPORT_SOUMETTRE');

        $request->validate([
            'id_modele' => 'required|string',
        ]);

        try {
            $user = Auth::user();
            $reportId = $this->defenseWorkflowService->createReportFromModel($user->numero_utilisateur, $request->id_modele);
            return redirect()->route('student.report.show', $reportId);
        } catch (ElementNotFoundException | OperationFailedException $e) {
            return back()->with('error', "Impossible d'initialiser le rapport : " . $e->getMessage())->withInput();
        } catch (\Exception $e) {
            Log::error("Erreur lors de la création du rapport depuis un modèle pour {$user->numero_utilisateur}: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue lors de l\'initialisation du rapport.')->withInput();
        }
    }

    /**
     * Affiche le formulaire de rédaction/visualisation du rapport.
     *
     * @param string $reportId L'ID du rapport.
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     */
    public function show(string $reportId)
    {
        $user = Auth::user();

        try {
            $report = $this->defenseWorkflowService->readCompleteReport($reportId);

            if (!$report || $report->numero_carte_etudiant !== $user->numero_utilisateur) {
                abort(403, "Vous n'êtes pas autorisé à accéder à ce rapport.");
            }

            $isLocked = !in_array($report->id_statut_rapport, ['RAP_BROUILLON', 'RAP_CORRECT']);

            return view('Student.report_editing', [
                'title' => 'Mon Rapport',
                'report' => $report,
                'isLocked' => $isLocked,
            ]);
        } catch (ElementNotFoundException $e) {
            return redirect()->route('student.dashboard')->with('error', 'Erreur lors du chargement du rapport : ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors du chargement du rapport {$reportId} pour {$user->numero_utilisateur}: " . $e->getMessage());
            return redirect()->route('student.dashboard')->with('error', 'Une erreur inattendue est survenue lors du chargement du rapport.');
        }
    }

    /**
     * Sauvegarde le brouillon du rapport (appel AJAX).
     *
     * @param Request $request L'objet requête HTTP.
     * @param string $reportId L'ID du rapport.
     * @return \Illuminate\Http\JsonResponse
     */
    public function save(Request $request, string $reportId)
    {
        $this->authorize('TRAIT_ETUDIANT_RAPPORT_SUIVRE');

        $request->validate([
            'metadonnees' => 'required|array',
            'metadonnees.libelle_rapport_etudiant' => 'required|string|max:255',
            'metadonnees.theme' => 'nullable|string|max:255',
            'metadonnees.resume' => 'nullable|string',
            'metadonnees.nombre_pages' => 'nullable|integer|min:0',
            'sections' => 'required|array',
            // Ajoutez des règles de validation pour les sections si nécessaire
        ]);

        try {
            $user = Auth::user();
            $this->defenseWorkflowService->createOrUpdateDraft($user->numero_utilisateur, $request->metadonnees, $request->sections);
            return response()->json(['success' => true, 'message' => 'Brouillon sauvegardé.']);
        } catch (OperationFailedException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la sauvegarde du rapport {$reportId} pour {$user->numero_utilisateur}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Une erreur inattendue est survenue lors de la sauvegarde.'], 500);
        }
    }

    /**
     * Soumet le rapport final pour validation (appel AJAX).
     *
     * @param Request $request L'objet requête HTTP.
     * @param string $reportId L'ID du rapport.
     * @return \Illuminate\Http\JsonResponse
     */
    public function submit(Request $request, string $reportId)
    {
        $this->authorize('TRAIT_ETUDIANT_RAPPORT_SOUMETTRE');

        $request->validate([
            'id_rapport' => 'required|string|in:' . $reportId, // S'assurer que l'ID correspond
        ]);

        try {
            $user = Auth::user();
            $this->defenseWorkflowService->submitReport($reportId, $user->numero_utilisateur);
            return response()->json(['success' => true, 'message' => 'Rapport soumis avec succès !', 'redirect' => route('student.dashboard')]);
        } catch (PermissionDeniedException | OperationFailedException | ElementNotFoundException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la soumission du rapport {$reportId} pour {$user->numero_utilisateur}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Une erreur inattendue est survenue lors de la soumission.'], 500);
        }
    }

    /**
     * Soumet les corrections demandées pour un rapport (appel AJAX).
     *
     * @param Request $request L'objet requête HTTP.
     * @param string $reportId L'ID du rapport.
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitCorrections(Request $request, string $reportId)
    {
        $this->authorize('TRAIT_ETUDIANT_RAPPORT_SOUMETTRE');

        $request->validate([
            'note_explicative' => 'required|string|max:1000',
            'sections' => 'required|array',
            // Ajoutez des règles de validation pour les sections si nécessaire
        ]);

        try {
            $user = Auth::user();
            $this->defenseWorkflowService->submitCorrections($reportId, $user->numero_utilisateur, $request->sections, $request->note_explicative);
            return response()->json(['success' => true, 'message' => 'Corrections soumises avec succès !', 'redirect' => route('student.dashboard')]);
        } catch (PermissionDeniedException | OperationFailedException | ElementNotFoundException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la soumission des corrections pour le rapport {$reportId} pour {$user->numero_utilisateur}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Une erreur inattendue est survenue lors de la soumission des corrections.'], 500);
        }
    }
}
