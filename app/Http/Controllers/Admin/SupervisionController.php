<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SupervisionService;
use App\Services\SecurityService;
use App\Exceptions\OperationFailedException;
use App\Exceptions\ElementNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SupervisionController extends Controller
{
    protected $supervisionService;
    protected $securityService;

    public function __construct(
        SupervisionService $supervisionService,
        SecurityService $securityService
    ) {
        $this->supervisionService = $supervisionService;
        $this->securityService = $securityService;

        // Appliquer le middleware de permission pour toutes les actions de ce contrôleur
        $this->middleware('can:TRAIT_ADMIN_SUPERVISION_AUDIT_VIEW');
    }

    /**
     * Affiche la page de supervision avec les journaux d'audit, d'erreurs et les tâches asynchrones.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\View\View|\Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->all();
            $data = [
                'title' => 'Supervision du Système',
                'audit_logs' => $this->supervisionService->consultLogs($filters),
                'async_tasks' => $this->supervisionService->listAsyncTasks($filters),
                'current_filters' => $filters
            ];

            $logFilePath = storage_path('logs/laravel.log'); // Chemin par défaut du log Laravel
            $data['error_log_content'] = file_exists($logFilePath) ? $this->supervisionService->consultErrorLogs($logFilePath) : "Fichier log non trouvé ou illisible.";

            return view('Administration.supervision', $data);
        } catch (\Exception $e) {
            Log::error("Erreur lors du chargement de la page de supervision: " . $e->getMessage());
            return response()->view('errors.500', ['message' => 'Impossible de charger la page de supervision : ' . $e->getMessage()], 500);
        }
    }

    /**
     * Récupère les détails d'une entrée de journal d'audit (pour affichage modal/détail).
     *
     * @param string $id L'ID de l'enregistrement.
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAuditLogDetails(string $id)
    {
        $this->authorize('TRAIT_ADMIN_SUPERVISION_AUDIT_VIEW'); // Vérification de permission spécifique

        try {
            $logEntry = $this->supervisionService->reconstituteEntityHistory($id)->first(); // Assuming $id is the record ID
            if (!$logEntry) {
                return response()->json(['success' => false, 'message' => 'Entrée de log non trouvée.'], 404);
            }
            return response()->json(['success' => true, 'data' => $logEntry->toArray()]);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des détails du log {$id}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Purge les anciens journaux d'audit.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function purgeAuditLogs(Request $request)
    {
        $this->authorize('TRAIT_ADMIN_SUPERVISION_AUDIT_PURGE'); // Vérification de permission spécifique

        $request->validate([
            'date_limite' => 'required|date',
        ]);

        try {
            $deletedCount = $this->supervisionService->purgeOldLogs($request->date_limite);
            return back()->with('success', "Purge effectuée. {$deletedCount} enregistrements supprimés.");
        } catch (OperationFailedException $e) {
            return back()->with('error', 'Erreur de purge: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de la purge des journaux d'audit: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue lors de la purge.');
        }
    }

    /**
     * Gère une tâche asynchrone (relancer, supprimer).
     *
     * @param Request $request L'objet requête HTTP.
     * @param string $taskId L'ID de la tâche.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleTaskAction(Request $request, string $taskId)
    {
        $this->authorize('TRAIT_ADMIN_SUPERVISION_TACHES_GERER'); // Vérification de permission spécifique

        $request->validate([
            'action' => 'required|in:relancer,supprimer', // 'requeue' si vous l'implémentez
        ]);

        try {
            $action = $request->input('action');
            $this->supervisionService->manageAsyncTask($taskId, $action);
            return back()->with('success', "La tâche {$taskId} a été {$action}ée avec succès.");
        } catch (ElementNotFoundException | OperationFailedException $e) {
            return back()->with('error', 'Erreur: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de la gestion de la tâche asynchrone {$taskId}: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue.');
        }
    }
}
