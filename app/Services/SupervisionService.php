<?php

namespace App\Services;

use App\Models\Enregistrer;
use App\Models\Pister;
use App\Models\Action;
use App\Models\QueueJob;
use App\Models\Utilisateur;
use App\Models\RapportEtudiant;
use App\Models\StatutRapportRef; // Pour les libellés de statut
use App\Models\StatutReclamationRef; // Pour les libellés de statut de réclamation
use App\Utils\IdGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Exceptions\{ElementNotFoundException, OperationFailedException};

class SupervisionService
{
    protected $idGenerator;

    public function __construct(IdGenerator $idGenerator)
    {
        $this->idGenerator = $idGenerator;
    }

    /**
     * Enregistre une action utilisateur dans le journal d'audit.
     * L'approche est "auto-réparatrice" : si l'action n'existe pas, elle est créée.
     * La méthode est conçue pour ne jamais bloquer l'exécution principale.
     *
     * @param string $userId L'ID de l'utilisateur effectuant l'action.
     * @param string $actionId L'ID de l'action (ex: 'CONNEXION_REUSSIE').
     * @param string|null $entityId L'ID de l'entité métier concernée.
     * @param string|null $entityType Le type de l'entité métier concernée.
     * @param array $details Les détails supplémentaires de l'action.
     * @return bool
     */
    public function recordAction(
        string $userId,
        string $actionId,
        ?string $entityId = null,
        ?string $entityType = null,
        array $details = []
    ): bool {
        try {
            DB::transaction(function () use ($userId, $actionId, $entityId, $entityType, $details) {
                // Vérifier si l'action existe, sinon la créer
                Action::firstOrCreate(
                    ['id_action' => $actionId],
                    ['libelle_action' => Str::title(str_replace('_', ' ', $actionId)), 'categorie_action' => 'Dynamique']
                );

                Enregistrer::create([
                    'id_enregistrement' => $this->idGenerator->generateUniqueId('LOG'),
                    'numero_utilisateur' => $userId,
                    'id_action' => $actionId,
                    'date_action' => now(),
                    'adresse_ip' => request()->ip(), // Utilisation de l'helper Laravel
                    'user_agent' => request()->header('User-Agent'), // Utilisation de l'helper Laravel
                    'id_entite_concernee' => $entityId,
                    'type_entite_concernee' => $entityType,
                    'details_action' => $details, // Eloquent cast automatiquement en JSON si la colonne est de type JSON
                    'session_id_utilisateur' => session()->getId() // Utilisation de l'helper Laravel
                ]);
            });
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to record action {$actionId} for user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enregistre un accès à une fonctionnalité protégée.
     *
     * @param string $userId L'ID de l'utilisateur.
     * @param string $traitementId L'ID du traitement accédé.
     * @return bool
     */
    public function recordAccess(string $userId, string $traitementId): bool
    {
        try {
            Pister::create([
                'id_piste' => $this->idGenerator->generateUniqueId('PISTE'),
                'numero_utilisateur' => $userId,
                'id_traitement' => $traitementId,
                'date_pister' => now(),
                'acceder' => true
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to record access for user {$userId} to treatment {$traitementId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Consulte les journaux d'audit avec filtres et pagination.
     *
     * @param array $filters Les filtres (search, id_action, numero_utilisateur, date_start, date_end).
     * @param int $limit Le nombre d'enregistrements à retourner.
     * @param int $offset L'offset pour la pagination.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function consultLogs(array $filters = [], int $limit = 50, int $offset = 0): \Illuminate\Database\Eloquent\Collection
    {
        $query = Enregistrer::query()
            ->with(['action', 'utilisateur']) // Charger les relations
            ->leftJoin('etudiant', function($join) {
                $join->on('enregistrer.id_entite_concernee', '=', 'etudiant.numero_carte_etudiant')
                    ->where('enregistrer.type_entite_concernee', '=', 'RapportEtudiant');
            })
            ->leftJoin('enseignant', function($join) {
                $join->on('enregistrer.id_entite_concernee', '=', 'enseignant.numero_enseignant')
                    ->where('enregistrer.type_entite_concernee', '=', 'Enseignant');
            })
            ->leftJoin('personnel_administratif', function($join) {
                $join->on('enregistrer.id_entite_concernee', '=', 'personnel_administratif.numero_personnel_administratif')
                    ->where('enregistrer.type_entite_concernee', '=', 'PersonnelAdministratif');
            })
            ->select(
                'enregistrer.*',
                DB::raw('COALESCE(etudiant.nom, enseignant.nom, personnel_administratif.nom) as entity_nom'),
                DB::raw('COALESCE(etudiant.prenom, enseignant.prenom, personnel_administratif.prenom) as entity_prenom')
            );

        foreach ($filters as $key => $value) {
            if (empty($value)) continue;

            switch ($key) {
                case 'search':
                    $query->where(function ($q) use ($value) {
                        $q->where('enregistrer.id_enregistrement', 'LIKE', "%{$value}%")
                            ->orWhere('enregistrer.numero_utilisateur', 'LIKE', "%{$value}%")
                            ->orWhere('enregistrer.id_action', 'LIKE', "%{$value}%")
                            ->orWhere('enregistrer.id_entite_concernee', 'LIKE', "%{$value}%")
                            ->orWhere('enregistrer.type_entite_concernee', 'LIKE', "%{$value}%")
                            ->orWhere('utilisateur.login_utilisateur', 'LIKE', "%{$value}%");
                    });
                    break;
                case 'id_action':
                case 'numero_utilisateur':
                case 'type_entite_concernee':
                    $query->where("enregistrer.{$key}", $value);
                    break;
                case 'date_start':
                    $query->where('enregistrer.date_action', '>=', $value);
                    break;
                case 'date_end':
                    $query->where('enregistrer.date_action', '<=', $value . ' 23:59:59');
                    break;
                default:
                    // Gérer d'autres filtres si nécessaire
                    break;
            }
        }

        return $query->orderByDesc('enregistrer.date_action')->offset($offset)->limit($limit)->get();
    }

    /**
     * Reconstitue l'historique des actions liées à une entité spécifique.
     *
     * @param string $entityId L'ID de l'entité.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function reconstituteEntityHistory(string $entityId): \Illuminate\Database\Eloquent\Collection
    {
        return Enregistrer::where('id_entite_concernee', $entityId)
            ->orderBy('date_action')
            ->get();
    }

    // ====================================================================
    // SECTION 2 : Maintenance & Supervision Technique
    // ====================================================================

    /**
     * Purge les anciens journaux d'audit.
     *
     * @param string $dateLimit La date limite (les enregistrements avant cette date seront supprimés).
     * @return int Le nombre de lignes supprimées.
     */
    public function purgeOldLogs(string $dateLimit): int
    {
        $deletedCount = Enregistrer::where('date_action', '<', $dateLimit)->delete();
        $this->recordAction(Auth::id() ?? 'SYSTEM', 'PURGE_LOGS', null, null, ['date_limit' => $dateLimit, 'deleted_rows' => $deletedCount]);
        return $deletedCount;
    }

    /**
     * Consulte le contenu du journal d'erreurs PHP.
     *
     * @param string $logFilePath Le chemin complet du fichier de log.
     * @return string Le contenu du fichier de log.
     * @throws OperationFailedException Si le fichier de log est introuvable ou illisible.
     */
    public function consultErrorLogs(string $logFilePath): string
    {
        if (!file_exists($logFilePath) || !is_readable($logFilePath)) {
            throw new OperationFailedException("Le fichier de log est introuvable ou illisible.");
        }
        $content = file($logFilePath);
        return implode("", array_slice($content, -500)); // Retourne les 500 dernières lignes
    }

    /**
     * Liste les tâches asynchrones avec filtres.
     *
     * @param array $filters Les filtres à appliquer.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listAsyncTasks(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = QueueJob::query();

        foreach ($filters as $key => $value) {
            if (empty($value)) continue;
            $query->where($key, $value);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Gère une tâche asynchrone (relancer, supprimer).
     *
     * @param string $taskId L'ID de la tâche.
     * @param string $action L'action à effectuer ('relancer', 'supprimer').
     * @return bool
     * @throws ElementNotFoundException Si la tâche n'est pas trouvée.
     * @throws OperationFailedException Si l'action est inconnue.
     */
    public function manageAsyncTask(string $taskId, string $action): bool
    {
        $task = QueueJob::find($taskId);
        if (!$task) {
            throw new ElementNotFoundException("Tâche non trouvée.");
        }

        switch (strtolower($action)) {
            case 'relancer':
                // Créer une nouvelle tâche avec le même payload
                QueueJob::create([
                    'job_name' => $task->job_name,
                    'payload' => $task->payload,
                    'status' => 'pending',
                    'attempts' => 0,
                    'created_at' => now(),
                ]);
                // Marquer l'ancienne tâche comme "failed_retried" ou similaire si votre enum le permet
                $task->status = 'failed'; // Ou un statut spécifique pour "relancé" si vous l'ajoutez
                $task->error_message = 'Relancée par administrateur.';
                $success = $task->save();
                break;
            case 'supprimer':
                $success = $task->delete();
                break;
            default:
                throw new OperationFailedException("Action '{$action}' non reconnue pour les tâches asynchrones.");
        }

        if ($success) {
            $this->recordAction(Auth::id(), 'HANDLE_ASYNC_TASK', $taskId, 'QueueJob', ['action' => $action]);
        }
        return $success;
    }

    // ====================================================================
    // SECTION 3 : Reporting
    // ====================================================================

    /**
     * Génère un ensemble complet de statistiques pour le tableau de bord de l'administrateur.
     *
     * @return array
     */
    public function generateAdminDashboardStats(): array
    {
        $stats = [];

        // Statistiques utilisateurs
        $stats['users'] = Utilisateur::select('statut_compte', DB::raw('COUNT(*) as count'))
            ->groupBy('statut_compte')
            ->pluck('count', 'statut_compte')
            ->toArray();
        $stats['users']['total'] = array_sum($stats['users']);

        // Statistiques rapports
        $stats['reports'] = StatutRapportRef::leftJoin('rapport_etudiant', 'statut_rapport_ref.id_statut_rapport', '=', 'rapport_etudiant.id_statut_rapport')
            ->select('statut_rapport_ref.libelle_statut_rapport', DB::raw('COUNT(rapport_etudiant.id_rapport_etudiant) as count'))
            ->groupBy('statut_rapport_ref.libelle_statut_rapport')
            ->pluck('count', 'libelle_statut_rapport')
            ->toArray();

        // Statistiques file d'attente
        $stats['queue'] = QueueJob::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Activité récente (7 derniers jours)
        $sevenDaysAgo = now()->subDays(7);
        $stats['recent_activity'] = Enregistrer::select('id_action', DB::raw('COUNT(*) as count'))
            ->where('date_action', '>=', $sevenDaysAgo)
            ->groupBy('id_action')
            ->pluck('count', 'id_action')
            ->toArray();

        // Statistiques réclamations
        $stats['complaints'] = StatutReclamationRef::leftJoin('reclamation', 'statut_reclamation_ref.id_statut_reclamation', '=', 'reclamation.id_statut_reclamation')
            ->select('statut_reclamation_ref.libelle_statut_reclamation', DB::raw('COUNT(reclamation.id_reclamation) as count'))
            ->groupBy('statut_reclamation_ref.libelle_statut_reclamation')
            ->pluck('count', 'libelle_statut_reclamation')
            ->toArray();

        return $stats;
    }
}
