<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemService;
use App\Services\DocumentService;
use App\Services\CommunicationService;
use App\Services\SecurityService;
use App\Services\SupervisionService;
use App\Exceptions\OperationFailedException;
use App\Exceptions\ValidationException;
use App\Exceptions\ElementNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache; // Pour vider le cache

/**
 * Gère l'ensemble des configurations de l'application.
 * Ce contrôleur centralise toutes les actions de l'administrateur liées aux paramètres,
 * aux années académiques, aux référentiels, aux modèles de documents, aux notifications et aux menus.
 */
class ConfigurationController extends Controller
{
    protected $systemService;
    protected $documentService;
    protected $communicationService;
    protected $securityService;
    protected $supervisionService;

    public function __construct(
        SystemService $systemService,
        DocumentService $documentService,
        CommunicationService $communicationService,
        SecurityService $securityService,
        SupervisionService $supervisionService
    ) {
        $this->systemService = $systemService;
        $this->documentService = $documentService;
        $this->communicationService = $communicationService;
        $this->securityService = $securityService;
        $this->supervisionService = $supervisionService;

        // Appliquer le middleware de permission pour toutes les actions de ce contrôleur
        $this->middleware('can:TRAIT_ADMIN_CONFIG_ACCEDER');
    }

    /**
     * Affiche la page principale de configuration avec tous ses onglets.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        try {
            $data = [
                'title' => 'Configuration du Système',
                'system_parameters' => $this->systemService->getAllParametres(),
                'academic_years' => $this->systemService->listAcademicYears(),
                'referentials' => $this->getReferentialList(),
                'document_models' => $this->documentService->listDocumentModels(),
                'notification_templates' => $this->communicationService->listNotificationModels(),
                'notification_rules' => $this->communicationService->listNotificationMatrixRules(),
                'all_actions' => $this->systemService->manageReferential('list', 'action'),
                'all_user_groups' => $this->systemService->manageReferential('list', 'groupe_utilisateur'),
            ];
            return view('Administration.gestion_referentiels', $data);
        } catch (\Exception $e) {
            Log::error("Erreur de chargement de la page de configuration : " . $e->getMessage());
            return redirect()->route('admin.dashboard')->with('error', 'Erreur de chargement de la page de configuration.');
        }
    }

    /**
     * Récupère le panneau de détails pour un référentiel (appel AJAX).
     *
     * @param Request $request L'objet requête HTTP.
     * @param string $entityName Le nom du référentiel.
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function getReferentialDetails(Request $request, string $entityName)
    {
        $this->authorize('TRAIT_ADMIN_CONFIG_REFERENTIELS_GERER'); // Vérification de permission spécifique

        try {
            $entries = $this->systemService->manageReferential('list', $entityName);
            return response()->json(['success' => true, 'entries' => $entries]);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des détails du référentiel {$entityName}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Traite la mise à jour des paramètres système.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleSystemParameters(Request $request)
    {
        $this->authorize('TRAIT_ADMIN_CONFIG_PARAMETRES_GERER'); // Vérification de permission spécifique

        $request->validate([
            'MAX_LOGIN_ATTEMPTS' => 'required|integer|min:1',
            'LOCKOUT_TIME_MINUTES' => 'required|integer|min:1',
            'PASSWORD_MIN_LENGTH' => 'required|integer|min:1',
            // Ajoutez des règles de validation pour tous vos paramètres
        ]);

        try {
            $this->systemService->setParametres($request->except('_token')); // Exclure le jeton CSRF
            return redirect()->route('admin.configuration.index')->with('success', 'Paramètres système mis à jour avec succès.');
        } catch (ElementNotFoundException | OperationFailedException $e) {
            return back()->with('error', 'Erreur lors de la mise à jour des paramètres : ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de la mise à jour des paramètres système: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue lors de la mise à jour des paramètres.');
        }
    }

    /**
     * Traite les actions CRUD sur les années académiques.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleAcademicYearAction(Request $request)
    {
        $this->authorize('TRAIT_ADMIN_CONFIG_ANNEES_GERER'); // Vérification de permission spécifique

        $action = $request->input('action');
        $id = $request->input('id');

        try {
            switch ($action) {
                case 'create':
                    $request->validate([
                        'libelle_annee_academique' => 'required|string|max:50',
                        'date_debut' => 'required|date',
                        'date_fin' => 'required|date|after:date_debut',
                    ]);
                    $this->systemService->createAcademicYear(
                        $request->libelle_annee_academique,
                        $request->date_debut,
                        $request->date_fin,
                        $request->boolean('est_active')
                    );
                    return back()->with('success', "L'année académique '{$request->libelle_annee_academique}' a été créée.");
                case 'update':
                    $request->validate([
                        'id' => 'required|string',
                        'libelle_annee_academique' => 'required|string|max:50',
                        'date_debut' => 'required|date',
                        'date_fin' => 'required|date|after:date_debut',
                    ]);
                    $this->systemService->updateAcademicYear($id, $request->only(['libelle_annee_academique', 'date_debut', 'date_fin', 'est_active']));
                    return back()->with('success', "L'année académique '{$id}' a été mise à jour.");
                case 'delete':
                    $request->validate(['id' => 'required|string']);
                    $this->systemService->deleteAcademicYear($id);
                    return back()->with('success', "L'année académique '{$id}' a été supprimée.");
                case 'set_active':
                    $request->validate(['id' => 'required|string']);
                    $this->systemService->setActiveAcademicYear($id);
                    return back()->with('success', "L'année académique '{$id}' est maintenant active.");
                default:
                    return back()->with('error', 'Action non reconnue pour les années académiques.');
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->getErrors())->withInput();
        } catch (ElementNotFoundException | OperationFailedException $e) {
            return back()->with('error', 'Erreur sur les années académiques : ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de la gestion des années académiques: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue lors de la gestion des années académiques.');
        }
    }

    /**
     * Traite les actions CRUD sur un référentiel.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleReferentialAction(Request $request)
    {
        $this->authorize('TRAIT_ADMIN_CONFIG_REFERENTIELS_GERER'); // Vérification de permission spécifique

        $action = $request->input('action');
        $entityName = $request->input('entity_name');
        $id = $request->input('id');
        $label = $request->input('libelle'); // Supposons que 'libelle' est le champ principal pour la création/mise à jour

        try {
            switch ($action) {
                case 'create':
                    $request->validate([
                        'entity_name' => 'required|string',
                        'libelle' => 'required|string|max:255',
                        // Ajoutez des règles de validation spécifiques au référentiel si nécessaire
                    ]);
                    // La logique de génération d'ID doit être dans le service manageReferential
                    $this->systemService->manageReferential('create', $entityName, null, $request->except(['_token', 'action', 'entity_name']));
                    return back()->with('success', "L'entrée '{$label}' a été ajoutée au référentiel '{$entityName}'.");
                case 'update':
                    $request->validate([
                        'entity_name' => 'required|string',
                        'id' => 'required|string',
                        'libelle' => 'required|string|max:255',
                    ]);
                    $this->systemService->manageReferential('update', $entityName, $id, $request->except(['_token', 'action', 'entity_name', 'id']));
                    return back()->with('success', "L'entrée '{$id}' a été mise à jour dans le référentiel '{$entityName}'.");
                case 'delete':
                    $request->validate([
                        'entity_name' => 'required|string',
                        'id' => 'required|string',
                    ]);
                    $this->systemService->manageReferential('delete', $entityName, $id);
                    return back()->with('success', "L'entrée '{$id}' a été supprimée du référentiel '{$entityName}'.");
                default:
                    return back()->with('error', 'Action non reconnue pour les référentiels.');
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->getErrors())->withInput();
        } catch (ElementNotFoundException | OperationFailedException | DuplicateEntryException $e) {
            return back()->with('error', "Erreur sur le référentiel '{$entityName}' : " . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de la gestion du référentiel {$entityName}: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue lors de la gestion du référentiel.');
        }
    }

    /**
     * Gère les actions CRUD sur les modèles de documents.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleDocumentModelAction(Request $request)
    {
        $this->authorize('TRAIT_ADMIN_CONFIG_MODELES_DOC_GERER'); // Vérification de permission spécifique

        $action = $request->input('action');
        $id = $request->input('id_modele');

        try {
            switch ($action) {
                case 'import':
                    $request->validate([
                        'word_file' => 'required|file|mimes:docx|max:10240', // Max 10MB
                    ]);
                    $this->documentService->importDocumentModelWord($request->file('word_file')->getPathname());
                    return back()->with('success', 'Modèle importé avec succès.');
                case 'update':
                    $request->validate([
                        'id_modele' => 'required|string',
                        'nom_modele' => 'required|string|max:255',
                        'contenu_html' => 'required|string',
                    ]);
                    $this->documentService->updateDocumentModel($id, $request->nom_modele, $request->contenu_html);
                    return back()->with('success', "Le modèle '{$request->nom_modele}' a été mis à jour.");
                case 'delete':
                    $request->validate(['id_modele' => 'required|string']);
                    $this->documentService->deleteDocumentModel($id);
                    return back()->with('success', "Le modèle '{$id}' a été supprimé.");
                default:
                    return back()->with('error', 'Action non reconnue pour les modèles de documents.');
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->getErrors())->withInput();
        } catch (ElementNotFoundException | OperationFailedException $e) {
            return back()->with('error', "Erreur lors de l'opération sur le modèle de document : " . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de la gestion des modèles de documents: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue lors de la gestion des modèles de documents.');
        }
    }

    /**
     * Gère les actions sur les règles et modèles de notification.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleNotificationAction(Request $request)
    {
        $this->authorize('TRAIT_ADMIN_CONFIG_NOTIFS_GERER'); // Vérification de permission spécifique

        $action = $request->input('action');

        try {
            switch ($action) {
                case 'update_rule':
                    $request->validate([
                        'id_regle' => 'required|string',
                        'canal' => 'required|in:Interne,Email,Tous',
                        'est_active' => 'boolean',
                    ]);
                    $this->communicationService->updateNotificationMatrixRule(
                        $request->id_regle,
                        $request->canal,
                        $request->boolean('est_active')
                    );
                    return back()->with('success', "La règle de notification '{$request->id_regle}' a été mise à jour.");
                case 'update_template':
                    $request->validate([
                        'id' => 'required|string',
                        'libelle' => 'required|string|max:100',
                        'contenu' => 'required|string',
                    ]);
                    $this->communicationService->updateNotificationModel(
                        $request->id,
                        $request->libelle,
                        $request->contenu
                    );
                    return back()->with('success', "Le modèle de notification '{$request->id}' a été mis à jour.");
                default:
                    return back()->with('error', 'Action non reconnue pour les notifications.');
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->getErrors())->withInput();
        } catch (ElementNotFoundException | OperationFailedException $e) {
            return back()->with('error', "Erreur sur les notifications : " . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de la gestion des notifications: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue lors de la gestion des notifications.');
        }
    }

    /**
     * Traite la mise à jour de la structure des menus.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleMenuOrder(Request $request)
    {
        $this->authorize('TRAIT_ADMIN_CONFIG_MENUS_GERER'); // Vérification de permission spécifique

        $request->validate([
            'menu_structure' => 'required|json',
        ]);

        try {
            $menuStructure = json_decode($request->menu_structure, true);
            if (!is_array($menuStructure)) {
                throw new ValidationException("Structure de menu invalide.");
            }
            $this->systemService->updateMenuStructure($menuStructure);
            return back()->with('success', "La structure du menu a été sauvegardée.");
        } catch (ValidationException $e) {
            return back()->withErrors($e->getErrors())->withInput();
        } catch (OperationFailedException $e) {
            return back()->with('error', 'Erreur lors de la sauvegarde du menu : ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors de la sauvegarde de la structure du menu: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue lors de la sauvegarde du menu.');
        }
    }

    /**
     * Vide les caches de l'application.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function clearCache(Request $request)
    {
        $this->authorize('TRAIT_ADMIN_CONFIG_ACCEDER'); // Vérification de permission spécifique

        try {
            Cache::flush(); // Vide tout le cache de Laravel
            // Vous pouvez aussi vider des caches spécifiques si nécessaire
            // Cache::forget('admin_dashboard_stats');

            return back()->with('success', 'Les caches de l\'application ont été vidés.');
        } catch (\Exception $e) {
            Log::error("Erreur lors du vidage du cache: " . $e->getMessage());
            return back()->with('error', 'Une erreur est survenue lors du vidage du cache.');
        }
    }

    /**
     * Méthode utilitaire pour obtenir la liste des référentiels disponibles.
     *
     * @return array
     */
    protected function getReferentialList(): array
    {
        $referentialKeys = [
            'grade', 'fonction', 'specialite', 'niveau_etude', 'statut_rapport_ref',
            'statut_pv_ref', 'statut_paiement_ref', 'decision_vote_ref', 'statut_conformite_ref',
            'statut_reclamation_ref', 'type_document_ref', 'statut_jury', 'action', 'groupe_utilisateur',
            'critere_conformite_ref', 'decision_passage_ref', 'decision_validation_pv_ref',
            'niveau_acces_donne', 'type_utilisateur', 'ue', 'ecue', 'entreprise'
        ];
        $list = [];
        foreach ($referentialKeys as $key) {
            $list[$key] = Str::title(str_replace(['_', ' ref'], [' ', ''], $key));
        }
        asort($list);
        return $list;
    }
}
