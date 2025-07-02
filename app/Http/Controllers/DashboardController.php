<?php

namespace App\Http\Controllers;

use App\Services\SecurityService;
use App\Services\SupervisionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    protected $securityService;
    protected $supervisionService;

    public function __construct(
        SecurityService $securityService,
        SupervisionService $supervisionService
    ) {
        $this->securityService = $securityService;
        $this->supervisionService = $supervisionService;
    }

    /**
     * Point d'entrée après la connexion.
     * Redirige l'utilisateur vers son tableau de bord spécifique en fonction de son groupe.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        $dashboardRouteName = null;

        switch ($user->id_groupe_utilisateur) {
            case 'GRP_ADMIN_SYS':
                $dashboardRouteName = 'admin.dashboard';
                break;
            case 'GRP_ETUDIANT':
                $dashboardRouteName = 'student.dashboard';
                break;
            case 'GRP_ENSEIGNANT':
            case 'GRP_COMMISSION':
                $dashboardRouteName = 'commission.dashboard';
                break;
            case 'GRP_PERS_ADMIN':
            case 'GRP_RS':
            case 'GRP_AGENT_CONFORMITE':
                $dashboardRouteName = 'administrative-personnel.dashboard';
                break;
            default:
                $this->supervisionService->recordAction(
                    $user->numero_utilisateur,
                    'ACCES_DASHBOARD_REFUSE',
                    null,
                    null,
                    ['reason' => 'Groupe utilisateur non géré', 'group' => $user->id_groupe_utilisateur]
                );
                // Utilisation de abort() pour les erreurs HTTP
                abort(403, 'Votre rôle ne vous donne pas accès à un tableau de bord spécifique.');
        }

        if ($dashboardRouteName) {
            $this->supervisionService->recordAction(
                $user->numero_utilisateur,
                'ACCES_DASHBOARD_REUSSI',
                null,
                $dashboardRouteName,
                ['group' => $user->id_groupe_utilisateur]
            );
            return redirect()->route($dashboardRouteName);
        }
    }
}
