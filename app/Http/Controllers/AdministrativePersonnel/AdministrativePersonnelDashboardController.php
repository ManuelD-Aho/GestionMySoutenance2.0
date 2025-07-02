<?php

namespace App\Http\Controllers\AdministrativePersonnel;

use App\Http\Controllers\Controller;
use App\Services\DefenseWorkflowService;
use App\Services\UserService;
use App\Services\SecurityService;
use App\Services\SupervisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdministrativePersonnelDashboardController extends Controller
{
    protected $defenseWorkflowService;
    protected $userService;
    protected $securityService;
    protected $supervisionService;

    public function __construct(
        DefenseWorkflowService $defenseWorkflowService,
        UserService $userService,
        SecurityService $securityService,
        SupervisionService $supervisionService
    ) {
        $this->defenseWorkflowService = $defenseWorkflowService;
        $this->userService = $userService;
        $this->securityService = $securityService;
        $this->supervisionService = $supervisionService;

        // Appliquer le middleware de permission pour l'accès au tableau de bord
        $this->middleware('can:access-administrative-personnel-dashboard');
    }

    /**
     * Affiche le tableau de bord pour le personnel administratif.
     * Le contenu est adapté en fonction du rôle (Agent de conformité ou RS).
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\View\View|\Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $data = ['title' => 'Tableau de Bord Administratif'];

        try {
            // Logique adaptative en fonction du groupe de l'utilisateur
            if ($user->id_groupe_utilisateur === 'GRP_AGENT_CONFORMITE') {
                // Pour l'agent de conformité : rapports soumis en attente de vérification
                $data['pendingReportsForCompliance'] = $this->defenseWorkflowService->listReports(['id_statut_rapport' => 'RAP_SOUMIS']);
            } elseif ($user->id_groupe_utilisateur === 'GRP_RS') {
                // Pour le RS : étudiants sans compte utilisateur
                $data['studentsWithoutAccount'] = $this->userService->listEntitiesWithoutAccount('etudiant');
                // Pour le RS : réclamations ouvertes
                $data['openComplaints'] = $this->defenseWorkflowService->listComplaints(['id_statut_reclamation' => 'RECLA_OUVERTE']);
                // Ajoutez ici d'autres données pour le RS si nécessaire (ex: stages à valider, pénalités à régulariser)
            }

            return view('AdministrativePersonnel.dashboard_administrative_personnel', $data);

        } catch (\Exception $e) {
            Log::error("Erreur AdministrativePersonnelDashboardController::index : " . $e->getMessage());
            return response()->view('errors.500', ['message' => "Une erreur est survenue lors du chargement du tableau de bord."], 500);
        }
    }
}
