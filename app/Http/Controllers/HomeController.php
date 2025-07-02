<?php

namespace App\Http\Controllers;

use App\Services\SystemService;
use App\Services\SecurityService;
use App\Services\SupervisionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    protected $systemService;
    protected $securityService;
    protected $supervisionService;

    public function __construct(
        SystemService $systemService,
        SecurityService $securityService,
        SupervisionService $supervisionService
    ) {
        $this->systemService = $systemService;
        $this->securityService = $securityService;
        $this->supervisionService = $supervisionService;
    }

    /**
     * Affiche la page d'accueil.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     */
    public function index()
    {
        try {
            // Vérifie si le mode maintenance est activé
            if ($this->systemService->isMaintenanceModeActive()) {
                $message = $this->systemService->getParametre('MAINTENANCE_MODE_MESSAGE', "Le site est actuellement en maintenance. Veuillez réessayer plus tard.");
                // Retourne une réponse HTTP 503 (Service Unavailable)
                return response()->view('errors.500', ['message' => $message], 503);
            }

            // Redirige vers le tableau de bord si l'utilisateur est déjà connecté
            if (Auth::check()) {
                return redirect()->route('dashboard');
            }

            // Affiche la page d'accueil pour les utilisateurs non connectés
            return view('home.index', ['title' => 'Bienvenue sur GestionMySoutenance']);

        } catch (\Exception $e) {
            Log::error("Erreur HomeController::index: " . $e->getMessage());
            // Retourne une page d'erreur générique 500
            return response()->view('errors.500', ['message' => "Une erreur inattendue est survenue lors du chargement de la page d'accueil."], 500);
        }
    }
}
