<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SupervisionService;
use App\Services\SystemService;
use App\Services\SecurityService; // Ajouté pour la cohérence
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Gère l'affichage du tableau de bord principal de l'administrateur.
 * Fournit une vue d'ensemble de l'état du système avec des statistiques et des alertes.
 */
class AdminDashboardController extends Controller
{
    protected $supervisionService;
    protected $systemService;
    protected $securityService;

    public function __construct(
        SupervisionService $supervisionService,
        SystemService $systemService,
        SecurityService $securityService
    ) {
        $this->supervisionService = $supervisionService;
        $this->systemService = $systemService;
        $this->securityService = $securityService;

        // Application du middleware de permission via les Gates de Laravel
        $this->middleware('can:access-admin-dashboard');
    }

    /**
     * Affiche le tableau de bord de l'administrateur avec les statistiques et les alertes.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\View\View|\Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $stats = null;
            $cacheKey = 'admin_dashboard_stats';
            $cacheDuration = 300; // 5 minutes

            // Utilisation du cache de Laravel
            $stats = cache()->remember($cacheKey, $cacheDuration, function () {
                return $this->supervisionService->generateAdminDashboardStats();
            });

            $failedJobsThreshold = (int) $this->systemService->getParametre('ALERT_THRESHOLD_FAILED_JOBS', 5);
            $alerts = [];
            if (isset($stats['queue']['failed']) && $stats['queue']['failed'] > $failedJobsThreshold) {
                $alerts[] = ['type' => 'error', 'message' => "Attention : {$stats['queue']['failed']} tâches asynchrones ont échoué, ce qui dépasse le seuil de {$failedJobsThreshold}."];
            }

            $data = [
                'title' => 'Tableau de Bord Administrateur',
                'stats' => $stats,
                'alerts' => $alerts,
            ];

            return view('Administration.dashboard_admin', $data);

        } catch (\Exception $e) {
            Log::error("Erreur inattendue dans AdminDashboardController::index: " . $e->getMessage());
            // Retourne une page d'erreur 500
            return response()->view('errors.500', ['message' => 'Impossible de charger le tableau de bord administrateur.'], 500);
        }
    }
}
