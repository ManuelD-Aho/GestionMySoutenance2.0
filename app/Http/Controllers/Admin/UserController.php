<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use App\Services\SystemService;
use App\Services\SecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Exceptions\{ElementNotFoundException, OperationFailedException, DuplicateEntryException, ValidationException};

/**
 * Gère toutes les opérations CRUD sur les utilisateurs et leurs entités associées.
 * Permet aux administrateurs de lister, créer, modifier et gérer les comptes utilisateurs.
 */
class UserController extends Controller
{
    protected $userService;
    protected $systemService;
    protected $securityService;

    public function __construct(
        UserService $userService,
        SystemService $systemService,
        SecurityService $securityService
    ) {
        $this->userService = $userService;
        $this->systemService = $systemService;
        $this->securityService = $securityService;
    }

    /**
     * Affiche la liste paginée et filtrable de tous les utilisateurs.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function list(Request $request)
    {
        $this->authorize('TRAIT_ADMIN_GERER_UTILISATEURS_LISTER');

        try {
            $filters = $request->all();
            $users = $this->userService->listCompleteUsers($filters);

            $groupes = $this->systemService->manageReferential('list', 'groupe_utilisateur');
            $statuts = ['actif', 'inactif', 'bloque', 'en_attente_validation', 'archive'];
            $types = $this->systemService->manageReferential('list', 'type_utilisateur');

            return view('Administration.gestion_utilisateurs', [
                'title' => 'Gestion des Utilisateurs',
                'users' => $users,
                'groupes' => $groupes,
                'statuts' => $statuts,
                'types' => $types,
                'current_filters' => $filters,
            ]);
        } catch (\Exception $e) {
            Log::error("Erreur lors du chargement des utilisateurs: " . $e->getMessage());
            return redirect()->route('admin.dashboard')->with('error', "Une erreur est survenue lors du chargement des utilisateurs.");
        }
    }

    /**
     * Affiche le formulaire de création d'un nouvel utilisateur et son entité.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showCreateUserForm()
    {
        $this->authorize('TRAIT_ADMIN_GERER_UTILISATEURS_CREER');

        try {
            return view('Administration.Utilisateurs.form_utilisateur', [
                'title' => 'Créer un Nouvel Utilisateur',
                'user' => null,
                'groupes' => $this->systemService->manageReferential('list', 'groupe_utilisateur'),
                'types' => $this->systemService->manageReferential('list', 'type_utilisateur'),
                'niveauxAcces' => $this->systemService->manageReferential('list', 'niveau_acces_donne'),
                'action_url' => route('admin.users.store'),
            ]);
        } catch (\Exception $e) {
            Log::error("Impossible de charger le formulaire de création d'utilisateur: " . $e->getMessage());
            return redirect()->route('admin.users.list')->with('error', 'Impossible de charger le formulaire de création.');
        }
    }

    /**
     * Traite la soumission du formulaire de création d'utilisateur.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function create(Request $request)
    {
        $this->authorize('TRAIT_ADMIN_GERER_UTILISATEURS_CREER');

        $request->validate([
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'login_utilisateur' => 'required|string|max:100|unique:utilisateur,login_utilisateur',
            'email_principal' => 'required|email|max:255|unique:utilisateur,email_principal',
            'mot_de_passe' => 'required|string|min:8',
            'id_groupe_utilisateur' => 'required|string',
            'id_type_utilisateur' => 'required|string',
            'id_niveau_acces_donne' => 'required|string',
            // Ajoutez d'autres règles de validation pour les champs de profil spécifiques
        ]);

        try {
            $entityType = strtolower(explode('_', $request->id_type_utilisateur)[1]); // Ex: TYPE_ETUD -> etud
            $profileData = $request->only(['nom', 'prenom', 'date_naissance', 'telephone', 'email_professionnel', 'adresse_postale', 'ville', 'code_postal', 'contact_urgence_nom', 'contact_urgence_telephone', 'contact_urgence_relation']);
            $accountData = $request->only(['login_utilisateur', 'email_principal', 'mot_de_passe', 'id_groupe_utilisateur', 'id_niveau_acces_donne']);

            $entityId = $this->userService->createEntity($entityType, $profileData);
            $this->userService->activateAccountForEntity($entityId, $accountData);

            return redirect()->route('admin.users.list')->with('success', 'Utilisateur créé avec succès. Un email de validation a été envoyé.');
        } catch (DuplicateEntryException | OperationFailedException | ElementNotFoundException | ValidationException $e) {
            return back()->with('error', 'Erreur de création : ' . $e->getMessage())->withInput();
        } catch (\Exception $e) {
            Log::error("Erreur lors de la création d'utilisateur: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue lors de la création.')->withInput();
        }
    }

    /**
     * Affiche le formulaire de modification pour un utilisateur existant.
     *
     * @param string $id L'ID de l'utilisateur.
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function show(string $id)
    {
        $this->authorize('TRAIT_ADMIN_GERER_UTILISATEURS_MODIFIER');

        try {
            $user = $this->userService->readCompleteUser($id);
            if (!$user) {
                throw new ElementNotFoundException("Utilisateur introuvable.");
            }

            return view('Administration.Utilisateurs.form_utilisateur', [
                'title' => "Modifier l'Utilisateur : " . htmlspecialchars($user->prenom . ' ' . $user->nom),
                'user' => $user,
                'groupes' => $this->systemService->manageReferential('list', 'groupe_utilisateur'),
                'types' => $this->systemService->manageReferential('list', 'type_utilisateur'),
                'niveauxAcces' => $this->systemService->manageReferential('list', 'niveau_acces_donne'),
                'action_url' => route('admin.users.update', $id),
            ]);
        } catch (ElementNotFoundException $e) {
            return redirect()->route('admin.users.list')->with('error', 'Impossible de charger le formulaire de modification : ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Impossible de charger le formulaire de modification d'utilisateur {$id}: " . $e->getMessage());
            return redirect()->route('admin.users.list')->with('error', 'Une erreur inattendue est survenue.');
        }
    }

    /**
     * Traite la soumission du formulaire de modification d'utilisateur.
     *
     * @param Request $request L'objet requête HTTP.
     * @param string $id L'ID de l'utilisateur.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, string $id)
    {
        $this->authorize('TRAIT_ADMIN_GERER_UTILISATEURS_MODIFIER');

        $request->validate([
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'login_utilisateur' => 'required|string|max:100|unique:utilisateur,login_utilisateur,' . $id . ',numero_utilisateur',
            'email_principal' => 'required|email|max:255|unique:utilisateur,email_principal,' . $id . ',numero_utilisateur',
            'mot_de_passe' => 'nullable|string|min:8',
            'id_groupe_utilisateur' => 'required|string',
            'id_type_utilisateur' => 'required|string',
            'id_niveau_acces_donne' => 'required|string',
            'statut_compte' => 'required|in:actif,inactif,bloque,en_attente_validation,archive',
            // Ajoutez d'autres règles de validation pour les champs de profil spécifiques
        ]);

        try {
            $profileData = $request->only(['nom', 'prenom', 'date_naissance', 'telephone', 'email_professionnel', 'adresse_postale', 'ville', 'code_postal', 'contact_urgence_nom', 'contact_urgence_telephone', 'contact_urgence_relation']);
            $accountData = $request->only(['login_utilisateur', 'email_principal', 'mot_de_passe', 'id_groupe_utilisateur', 'id_niveau_acces_donne', 'statut_compte']);

            $this->userService->updateUser($id, $profileData, $accountData);

            return redirect()->route('admin.users.list')->with('success', "Utilisateur {$id} mis à jour avec succès.");
        } catch (ElementNotFoundException | OperationFailedException | DuplicateEntryException | ValidationException $e) {
            return back()->with('error', "Échec de la mise à jour : " . $e->getMessage())->withInput();
        } catch (\Exception $e) {
            Log::error("Erreur lors de la mise à jour d'utilisateur {$id}: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue lors de la mise à jour.')->withInput();
        }
    }

    /**
     * Gère la suppression d'un utilisateur.
     *
     * @param Request $request L'objet requête HTTP.
     * @param string $id L'ID de l'utilisateur.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function delete(Request $request, string $id)
    {
        $this->authorize('TRAIT_ADMIN_GERER_UTILISATEURS_DELETE');

        try {
            $this->userService->deleteUserAndEntity($id);
            return redirect()->route('admin.users.list')->with('success', "L'utilisateur {$id} a été supprimé.");
        } catch (ElementNotFoundException | OperationFailedException $e) {
            return back()->with('error', "Erreur lors de la suppression : " . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de la suppression d'utilisateur {$id}: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue lors de la suppression.');
        }
    }

    /**
     * Gère les actions individuelles sur un utilisateur (changement de statut, impersonation, etc.).
     * Note: Les actions comme 'impersonate' devraient être des routes GET ou POST dédiées,
     * et non une action générique 'handleUserAction' avec un paramètre 'action'.
     * Pour cet exemple, je vais adapter la logique pour correspondre à une route DELETE.
     * Les autres actions (change_status, reset_password, impersonate) devraient avoir leurs propres méthodes/routes.
     *
     * @param Request $request L'objet requête HTTP.
     * @param string $id L'ID de l'utilisateur.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleUserAction(Request $request, string $id)
    {
        // Cette méthode est un exemple générique.
        // Dans une application Laravel, chaque action (changer statut, reset mdp, impersonate)
        // devrait avoir sa propre route (POST/PUT) et sa propre méthode de contrôleur.
        // Par exemple, pour changer le statut: Route::post('/users/{id}/status', [UserController::class, 'changeStatus']);
        // Pour l'impersonation: Route::post('/users/{id}/impersonate', [UserController::class, 'impersonate']);

        $action = $request->input('action'); // Récupérer l'action depuis le formulaire

        try {
            switch ($action) {
                case 'change_status':
                    $this->authorize('TRAIT_ADMIN_GERER_UTILISATEURS_MODIFIER');
                    $request->validate(['status' => 'required|in:actif,inactif,bloque,en_attente_validation,archive']);
                    $this->userService->changeAccountStatus($id, $request->input('status'));
                    return back()->with('success', "Statut de l'utilisateur {$id} modifié.");

                case 'reset_password':
                    $this->authorize('TRAIT_ADMIN_GERER_UTILISATEURS_MODIFIER');
                    $this->userService->resetPasswordByAdmin($id);
                    return back()->with('success', "Mot de passe réinitialisé pour {$id}. Un email a été envoyé.");

                case 'impersonate':
                    $this->authorize('TRAIT_ADMIN_IMPERSONATE_USER');
                    $this->securityService->startImpersonation(Auth::id(), $id);
                    return redirect()->route('dashboard')->with('info', "Vous impersonnalisez maintenant l'utilisateur {$id}.");

                default:
                    return back()->with('error', 'Action non reconnue.');
            }
        } catch (ElementNotFoundException | OperationFailedException | PermissionDeniedException $e) {
            return back()->with('error', "Erreur lors de l'action : " . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'action sur l'utilisateur {$id}: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue.');
        }
    }
}
