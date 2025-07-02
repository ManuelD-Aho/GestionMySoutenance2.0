<?php

namespace App\Http\Controllers\Commission;

use App\Http\Controllers\Controller;
use App\Services\DefenseWorkflowService;
use App\Services\SecurityService;
use App\Services\SupervisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Gère l'affichage du tableau de bord pour les membres de la commission.
 */
class CommissionDashboardController extends Controller
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

        // Appliquer le middleware de permission pour l'accès au tableau de bord
        $this->middleware('can:access-commission-dashboard');
    }

    /**
     * Affiche le tableau de bord de la commission.
     * Liste les rapports en attente de vote et les PV en attente d'approbation pour l'utilisateur connecté.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\View\View|\Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        try {
            $pendingReportsForVote = $this->defenseWorkflowService->listReports(['votant' => $user->numero_utilisateur, 'id_statut_rapport' => 'RAP_EN_COMMISSION']);
            $pendingPvsForApproval = $this->defenseWorkflowService->listPendingPvForApproval($user->numero_utilisateur);

            return view('Commission.dashboard_commission', [
                'title' => 'Tableau de Bord Commission',
                'pendingReportsForVote' => $pendingReportsForVote,
                'pendingPvsForApproval' => $pendingPvsForApproval,
                'user' => $user
            ]);

        } catch (\Exception $e) {
            Log::error("Erreur CommissionDashboardController::index : " . $e->getMessage());
            return response()->view('errors.500', ['message' => "Impossible de charger les données du tableau de bord."], 500);
        }
    }
}
