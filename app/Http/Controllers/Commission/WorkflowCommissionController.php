<?php

namespace App\Http\Controllers\Commission;

use App\Http\Controllers\Controller;
use App\Services\DefenseWorkflowService;
use App\Services\SecurityService;
use App\Services\SupervisionService;
use App\Services\SystemService; // Ajouté pour la cohérence
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Exceptions\{ElementNotFoundException, OperationFailedException, PermissionDeniedException, ValidationException};

/**
 * Orchestre tout le workflow de la commission : gestion des sessions, votes, et PV.
 */
class WorkflowCommissionController extends Controller
{
    protected $defenseWorkflowService;
    protected $securityService;
    protected $supervisionService;
    protected $systemService;

    public function __construct(
        DefenseWorkflowService $defenseWorkflowService,
        SecurityService $securityService,
        SupervisionService $supervisionService,
        SystemService $systemService
    ) {
        $this->defenseWorkflowService = $defenseWorkflowService;
        $this->securityService = $securityService;
        $this->supervisionService = $supervisionService;
        $this->systemService = $systemService;

        // Appliquer le middleware de permission pour l'accès au workflow de commission
        $this->middleware('can:access-commission-dashboard'); // Ou une permission plus spécifique si nécessaire
    }

    /**
     * Affiche la liste de toutes les sessions de validation.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function index(Request $request)
    {
        try {
            $sessions = $this->defenseWorkflowService->listCommissionSessions();
            return view('Commission.workflow_commission', [
                'title' => 'Gestion des Sessions de Validation',
                'sessions' => $sessions,
            ]);
        } catch (\Exception $e) {
            Log::error("Erreur lors du chargement des sessions de commission: " . $e->getMessage());
            return redirect()->route('commission.dashboard')->with('error', 'Erreur lors du chargement des sessions.');
        }
    }

    /**
     * Traite la création d'une nouvelle session de validation.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function create(Request $request)
    {
        $this->authorize('TRAIT_COMMISSION_SESSION_CREER');

        $request->validate([
            'nom_session' => 'required|string|max:255',
            'date_debut_session' => 'required|date',
            'date_fin_prevue' => 'required|date|after:date_debut_session',
            'mode_session' => 'required|in:presentiel,en_ligne,hybride',
            'nombre_votants_requis' => 'required|integer|min:1',
        ]);

        try {
            $presidentId = Auth::id();
            $sessionId = $this->defenseWorkflowService->createSession($presidentId, $request->all());
            return redirect()->route('commission.workflow.index')->with('success', "Session '{$request->nom_session}' créée avec l'ID {$sessionId}.");
        } catch (OperationFailedException $e) {
            return back()->with('error', "Erreur lors de la création de la session : " . $e->getMessage())->withInput();
        } catch (\Exception $e) {
            Log::error("Erreur lors de la création de la session de commission: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue lors de la création de la session.')->withInput();
        }
    }

    /**
     * Traite le vote d'un membre de la commission pour un rapport.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\JsonResponse
     */
    public function vote(Request $request)
    {
        $this->authorize('TRAIT_COMMISSION_VALIDATION_RAPPORT_VOTER');

        $request->validate([
            'id_rapport' => 'required|string',
            'id_session' => 'required|string',
            'decision' => 'required|string|in:VOTE_APPROUVE,VOTE_REFUSE,VOTE_APPROUVE_RESERVE,VOTE_ABSTENTION',
            'commentaire' => 'nullable|string|max:1000',
        ]);

        if ($request->decision !== 'VOTE_APPROUVE' && empty($request->commentaire)) {
            return response()->json(['success' => false, 'message' => 'Un commentaire est requis pour cette décision.'], 422);
        }

        try {
            $teacherId = Auth::id();
            $this->defenseWorkflowService->recordVote($request->id_rapport, $request->id_session, $teacherId, $request->decision, $request->commentaire);
            return response()->json(['success' => true, 'message' => 'Vote enregistré avec succès.']);
        } catch (OperationFailedException | ElementNotFoundException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'enregistrement du vote: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Une erreur inattendue est survenue.'], 500);
        }
    }

    /**
     * Traite l'initiation de la rédaction d'un PV.
     *
     * @param string $sessionId L'ID de la session.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function initiatePv(string $sessionId)
    {
        $this->authorize('TRAIT_COMMISSION_DASHBOARD_ACCEDER'); // Permission d'accès au dashboard de commission

        try {
            $redactorId = Auth::id();
            $pvId = $this->defenseWorkflowService->initiatePvDraft($sessionId, $redactorId);
            return redirect()->route('commission.pv.edit', $pvId)->with('success', "Vous êtes maintenant le rédacteur du PV {$pvId}.");
        } catch (OperationFailedException | ElementNotFoundException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'initiation du PV pour la session {$sessionId}: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue.');
        }
    }

    /**
     * Traite l'approbation d'un PV par un membre.
     *
     * @param string $pvId L'ID du PV.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approvePv(string $pvId)
    {
        $this->authorize('TRAIT_COMMISSION_DASHBOARD_ACCEDER'); // Permission d'accès au dashboard de commission

        try {
            $presidentId = Auth::id();
            $this->defenseWorkflowService->approvePv($pvId, $presidentId);
            return back()->with('success', 'PV approuvé avec succès.');
        } catch (ElementNotFoundException | OperationFailedException $e) {
            return back()->with('error', 'Erreur lors de l\'approbation : ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'approbation du PV {$pvId}: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue.');
        }
    }

    /**
     * Traite la validation forcée d'un PV par le président.
     *
     * @param Request $request L'objet requête HTTP.
     * @param string $pvId L'ID du PV.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function forcePvValidation(Request $request, string $pvId)
    {
        $this->authorize('TRAIT_COMMISSION_SESSION_GERER'); // Permission spécifique pour forcer la validation

        $request->validate([
            'justification' => 'required|string|max:1000',
        ]);

        try {
            $presidentId = Auth::id();
            $this->defenseWorkflowService->forcePvValidation($pvId, $presidentId, $request->justification);
            return back()->with('success', 'Le PV a été validé par substitution.');
        } catch (ElementNotFoundException | OperationFailedException $e) {
            return back()->with('error', 'Erreur : ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de la validation forcée du PV {$pvId}: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue.');
        }
    }
}
