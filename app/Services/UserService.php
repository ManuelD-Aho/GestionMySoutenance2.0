<?php

namespace App\Services;

use App\Models\FaireStage;
use App\Models\Penalite;
use App\Models\Utilisateur;
use App\Models\Etudiant;
use App\Models\Enseignant;
use App\Models\PersonnelAdministratif;
use App\Models\Delegation;
use App\Models\RapportEtudiant; // Pour les vérifications de dépendances
use App\Models\Inscrire; // Pour les vérifications de dépendances
use App\Models\Affecter; // Pour les vérifications de dépendances
use App\Models\Approuver; // Pour les vérifications de dépendances
use App\Models\CompteRendu; // Pour les vérifications de dépendances
use App\Models\Reclamation; // Pour les vérifications de dépendances
use App\Utils\IdGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage; // Pour la gestion des fichiers
use App\Exceptions\{ElementNotFoundException, OperationFailedException, DuplicateEntryException, InvalidEmailException, ValidationException};
use PhpOffice\PhpSpreadsheet\IOFactory; // Pour .xlsx import
use Illuminate\Support\Str; // Pour Str::slug

class UserService
{
    protected $idGenerator;
    protected $supervisionService;
    protected $communicationService;
    protected $documentService;
    protected $systemService;

    public function __construct(
        IdGenerator $idGenerator,
        SupervisionService $supervisionService,
        CommunicationService $communicationService,
        DocumentService $documentService,
        SystemService $systemService
    ) {
        $this->idGenerator = $idGenerator;
        $this->supervisionService = $supervisionService;
        $this->communicationService = $communicationService;
        $this->documentService = $documentService;
        $this->systemService = $systemService;
    }

    /**
     * Crée une nouvelle entité métier (étudiant, enseignant, personnel administratif).
     *
     * @param string $entityType Le type d'entité ('etudiant', 'enseignant', 'personnel').
     * @param array $profileData Les données du profil de l'entité.
     * @return string L'ID unique de l'entité créée.
     * @throws \InvalidArgumentException Si le type d'entité n'est pas reconnu.
     * @throws OperationFailedException Si la création de l'entité échoue.
     */
    public function createEntity(string $entityType, array $profileData): string
    {
        $prefixMap = ['etudiant' => 'ETU', 'enseignant' => 'ENS', 'personnel' => 'ADM'];
        $entityTypeLower = strtolower($entityType);

        if (!isset($prefixMap[$entityTypeLower])) {
            throw new \InvalidArgumentException("Type d'entité '{$entityType}' non reconnu.");
        }

        $model = $this->getModelForType($entityTypeLower);
        $primaryKeyColumn = $model->getKeyName();

        $entityId = $this->idGenerator->generateUniqueId($prefixMap[$entityTypeLower]);
        $profileData[$primaryKeyColumn] = $entityId;
        $profileData['numero_utilisateur'] = null; // L'entité n'est pas encore liée à un compte utilisateur

        if (!$model->create($profileData)) {
            throw new OperationFailedException("Échec de la création de l'entité {$entityType}.");
        }

        $this->supervisionService->recordAction(Auth::id() ?? 'SYSTEM', 'CREATE_ENTITE', $entityId, ucfirst($entityType), $profileData);
        return $entityId;
    }

    /**
     * Active un compte utilisateur pour une entité métier existante.
     *
     * @param string $entityId L'ID de l'entité métier.
     * @param array $accountData Les données du compte utilisateur (login, email, mot de passe, groupe, niveau d'accès).
     * @param bool $sendValidationEmail Indique si un email de validation doit être envoyé.
     * @return bool
     * @throws DuplicateEntryException Si un compte utilisateur ou un login/email existe déjà.
     * @throws ElementNotFoundException Si l'entité métier n'existe pas.
     * @throws OperationFailedException Si l'entité est déjà liée à un compte ou si la création échoue.
     * @throws \InvalidArgumentException Si le type d'entité est inconnu.
     */
    public function activateAccountForEntity(string $entityId, array $accountData, bool $sendValidationEmail = true): bool
    {
        return DB::transaction(function () use ($entityId, $accountData, $sendValidationEmail) {
            if (Utilisateur::find($entityId)) {
                throw new DuplicateEntryException("Un compte utilisateur existe déjà pour l'entité '{$entityId}'.");
            }
            if (Utilisateur::where('login_utilisateur', $accountData['login_utilisateur'])->exists()) {
                throw new DuplicateEntryException("Ce login est déjà utilisé.");
            }
            if (Utilisateur::where('email_principal', $accountData['email_principal'])->exists()) {
                throw new DuplicateEntryException("Cet email est déjà utilisé.");
            }

            $entityTypePrefix = explode('-', $entityId)[0];
            $modelEntity = $this->getModelForType(strtolower($entityTypePrefix));
            $entity = $modelEntity->find($entityId);

            if (!$entity) {
                throw new ElementNotFoundException("L'entité métier '{$entityId}' n'existe pas.");
            }
            if ($entity->numero_utilisateur !== null) {
                throw new OperationFailedException("Cette entité est déjà liée à un compte.");
            }

            $userType = match ($entityTypePrefix) {
                'ETU' => 'TYPE_ETUD',
                'ENS' => 'TYPE_ENS',
                'ADM' => 'TYPE_PERS_ADMIN',
                default => throw new \InvalidArgumentException("Préfixe d'entité non géré.")
            };

            $tokenClair = Str::random(64);
            $userData = [
                'numero_utilisateur' => $entityId,
                'login_utilisateur' => $accountData['login_utilisateur'],
                'email_principal' => $accountData['email_principal'],
                'mot_de_passe' => Hash::make($accountData['mot_de_passe']),
                'id_groupe_utilisateur' => $accountData['id_groupe_utilisateur'],
                'id_niveau_acces_donne' => $accountData['id_niveau_acces_donne'],
                'id_type_utilisateur' => $userType,
                'statut_compte' => 'en_attente_validation',
                'token_validation_email' => Hash::make($tokenClair),
                'date_expiration_token_reset' => now()->addDay() // Token valide 24h
            ];
            Utilisateur::create($userData);

            $entity->numero_utilisateur = $entityId;
            $entity->save();

            $this->supervisionService->recordAction(Auth::id() ?? 'SYSTEM', 'ACTIVATION_COMPTE', $entityId, 'Utilisateur');

            if ($sendValidationEmail) {
                $validationLink = url("/validate-email/{$tokenClair}");
                $this->communicationService->sendEmail($accountData['email_principal'], 'VALIDATE_EMAIL', ['validation_link' => $validationLink]);
            }
            return true;
        });
    }

    /**
     * Crée un compte administrateur système.
     *
     * @param string $login Le login de l'administrateur.
     * @param string $email L'email de l'administrateur.
     * @param string $password Le mot de passe de l'administrateur.
     * @return string L'ID de l'administrateur créé.
     * @throws DuplicateEntryException Si le login ou l'email est déjà utilisé.
     * @throws OperationFailedException Si la création du compte échoue.
     */
    public function createAdminUser(string $login, string $email, string $password): string
    {
        if (Utilisateur::where('login_utilisateur', $login)->exists()) {
            throw new DuplicateEntryException("Ce login est déjà utilisé.");
        }
        if (Utilisateur::where('email_principal', $email)->exists()) {
            throw new DuplicateEntryException("Cet email est déjà utilisé.");
        }

        $userId = $this->idGenerator->generateUniqueId('SYS');
        $userData = [
            'numero_utilisateur' => $userId,
            'login_utilisateur' => $login,
            'email_principal' => $email,
            'mot_de_passe' => Hash::make($password),
            'id_niveau_acces_donne' => 'ACCES_TOTAL',
            'id_groupe_utilisateur' => 'GRP_ADMIN_SYS',
            'id_type_utilisateur' => 'TYPE_ADMIN',
            'email_valide' => true,
            'statut_compte' => 'actif',
            'date_creation' => now(),
        ];

        if (!Utilisateur::create($userData)) {
            throw new OperationFailedException("Échec de la création du compte administrateur.");
        }

        $this->supervisionService->recordAction(Auth::id() ?? 'SYSTEM', 'CREATE_ADMIN_USER', $userId, 'Utilisateur');
        return $userId;
    }

    /**
     * Liste les utilisateurs complets avec leurs informations de profil associées.
     *
     * @param array $filters Les filtres à appliquer (login, email, nom, prénom, statut_compte, id_groupe_utilisateur, id_type_utilisateur).
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listCompleteUsers(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Utilisateur::query()
            ->with(['groupeUtilisateur', 'typeUtilisateur']) // Charger les relations
            ->leftJoin('etudiant', 'utilisateur.numero_utilisateur', '=', 'etudiant.numero_carte_etudiant')
            ->leftJoin('enseignant', 'utilisateur.numero_utilisateur', '=', 'enseignant.numero_enseignant')
            ->leftJoin('personnel_administratif', 'utilisateur.numero_utilisateur', '=', 'personnel_administratif.numero_personnel_administratif')
            ->select('utilisateur.*',
                DB::raw('COALESCE(etudiant.nom, enseignant.nom, personnel_administratif.nom) as nom'),
                DB::raw('COALESCE(etudiant.prenom, enseignant.prenom, personnel_administratif.prenom) as prenom')
            );

        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                if (empty($value)) continue;

                switch ($key) {
                    case 'search':
                        $query->where(function ($q) use ($value) {
                            $q->where('utilisateur.login_utilisateur', 'LIKE', "%{$value}%")
                                ->orWhere('utilisateur.email_principal', 'LIKE', "%{$value}%")
                                ->orWhere('etudiant.nom', 'LIKE', "%{$value}%")
                                ->orWhere('etudiant.prenom', 'LIKE', "%{$value}%")
                                ->orWhere('enseignant.nom', 'LIKE', "%{$value}%")
                                ->orWhere('enseignant.prenom', 'LIKE', "%{$value}%")
                                ->orWhere('personnel_administratif.nom', 'LIKE', "%{$value}%")
                                ->orWhere('personnel_administratif.prenom', 'LIKE', "%{$value}%");
                        });
                        break;
                    case 'statut_compte':
                    case 'id_groupe_utilisateur':
                    case 'id_type_utilisateur':
                        $query->where("utilisateur.{$key}", $value);
                        break;
                    case 'numero_utilisateur': // Pour la lecture d'un utilisateur spécifique
                        $query->where("utilisateur.{$key}", $value);
                        break;
                    default:
                        // Gérer d'autres filtres si nécessaire
                        break;
                }
            }
        }

        return $query->orderBy('nom')->orderBy('prenom')->get();
    }

    /**
     * Lit les informations complètes d'un utilisateur et de son profil associé.
     *
     * @param string $userId L'ID de l'utilisateur.
     * @return Utilisateur|null
     */
    public function readCompleteUser(string $userId): ?Utilisateur
    {
        return $this->listCompleteUsers(['numero_utilisateur' => $userId])->first();
    }

    /**
     * Met à jour les informations d'un utilisateur et de son profil associé.
     *
     * @param string $userId L'ID de l'utilisateur.
     * @param array $profileData Les données du profil à mettre à jour.
     * @param array $accountData Les données du compte à mettre à jour.
     * @return bool
     * @throws ElementNotFoundException Si l'utilisateur n'est pas trouvé.
     * @throws OperationFailedException Si la mise à jour échoue.
     */
    public function updateUser(string $userId, array $profileData, array $accountData): bool
    {
        return DB::transaction(function () use ($userId, $profileData, $accountData) {
            $user = Utilisateur::find($userId);
            if (!$user) {
                throw new ElementNotFoundException("Utilisateur non trouvé pour la mise à jour.");
            }

            // Mettre à jour les données du compte utilisateur
            if (!empty($accountData)) {
                if (isset($accountData['mot_de_passe']) && !empty($accountData['mot_de_passe'])) {
                    $accountData['mot_de_passe'] = Hash::make($accountData['mot_de_passe']);
                } else {
                    unset($accountData['mot_de_passe']); // Ne pas modifier le mot de passe s'il est vide
                }
                $user->update($accountData);
            }

            // Mettre à jour les données du profil métier associé
            if (!empty($profileData)) {
                $profileModel = $this->getModelForType(strtolower(explode('-', $user->numero_utilisateur)[0]));
                if ($profileModel) { // Les admins n'ont pas de profil métier associé
                    $profileModel->where($profileModel->getKeyName(), $userId)->update($profileData);
                }
            }

            $this->supervisionService->recordAction(Auth::id() ?? 'SYSTEM', 'UPDATE_UTILISATEUR', $userId, 'Utilisateur');
            return true;
        });
    }

    /**
     * Supprime un utilisateur et son entité métier associée.
     *
     * @param string $userId L'ID de l'utilisateur à supprimer.
     * @return bool
     * @throws ElementNotFoundException Si l'utilisateur n'est pas trouvé.
     * @throws OperationFailedException Si la suppression est impossible à cause de dépendances.
     */
    public function deleteUserAndEntity(string $userId): bool
    {
        return DB::transaction(function () use ($userId) {
            $user = Utilisateur::find($userId);
            if (!$user) {
                throw new ElementNotFoundException("Utilisateur non trouvé.");
            }

            $entityType = strtolower(explode('-', $user->numero_utilisateur)[0]);

            // Vérifications de dépendances avant suppression
            if ($entityType === 'etu') {
                if (RapportEtudiant::where('numero_carte_etudiant', $userId)->exists()) {
                    throw new OperationFailedException("Suppression impossible : l'étudiant est lié à des rapports.");
                }
                if (Inscrire::where('numero_carte_etudiant', $userId)->exists()) {
                    throw new OperationFailedException("Suppression impossible : l'étudiant est lié à des inscriptions.");
                }
                if (FaireStage::where('numero_carte_etudiant', $userId)->exists()) {
                    throw new OperationFailedException("Suppression impossible : l'étudiant est lié à des stages.");
                }
                if (Penalite::where('numero_carte_etudiant', $userId)->exists()) {
                    throw new OperationFailedException("Suppression impossible : l'étudiant est lié à des pénalités.");
                }
                if (Reclamation::where('numero_carte_etudiant', $userId)->exists()) {
                    throw new OperationFailedException("Suppression impossible : l'étudiant est lié à des réclamations.");
                }
            } elseif ($entityType === 'ens') {
                if (Affecter::where('numero_enseignant', $userId)->exists()) {
                    throw new OperationFailedException("Suppression impossible : l'enseignant est lié à des affectations de jury.");
                }
                if (CompteRendu::where('id_redacteur', $userId)->exists()) {
                    throw new OperationFailedException("Suppression impossible : l'enseignant est rédacteur de PV.");
                }
                if (Delegation::where('id_delegant', $userId)->orWhere('id_delegue', $userId)->exists()) {
                    throw new OperationFailedException("Suppression impossible : l'enseignant est impliqué dans des délégations.");
                }
                // Ajoutez d'autres vérifications (fonctions, grades, spécialités)
            } elseif ($entityType === 'adm') {
                if (Approuver::where('numero_personnel_administratif', $userId)->exists()) {
                    throw new OperationFailedException("Suppression impossible : le personnel est lié à des approbations de conformité.");
                }
                if (Reclamation::where('numero_personnel_traitant', $userId)->exists()) {
                    throw new OperationFailedException("Suppression impossible : le personnel a traité des réclamations.");
                }
                if (Delegation::where('id_delegant', $userId)->orWhere('id_delegue', $userId)->exists()) {
                    throw new OperationFailedException("Suppression impossible : le personnel est impliqué dans des délégations.");
                }
            }

            // Supprimer l'entité métier si elle existe
            $profileModel = $this->getModelForType($entityType);
            if ($profileModel && $profileModel->find($userId)) {
                $profileModel->where($profileModel->getKeyName(), $userId)->delete();
            }

            // Supprimer le compte utilisateur
            $user->delete();

            $this->supervisionService->recordAction(Auth::id() ?? 'SYSTEM', 'DELETE_USER_HARD', $userId, 'Utilisateur');
            return true;
        });
    }

    /**
     * Change le statut d'un compte utilisateur.
     *
     * @param string $userId L'ID de l'utilisateur.
     * @param string $newStatus Le nouveau statut ('actif', 'inactif', 'bloque', 'archive').
     * @return bool
     * @throws ElementNotFoundException Si l'utilisateur n'est pas trouvé.
     */
    public function changeAccountStatus(string $userId, string $newStatus): bool
    {
        $user = Utilisateur::find($userId);
        if (!$user) {
            throw new ElementNotFoundException("Utilisateur non trouvé.");
        }

        $user->statut_compte = $newStatus;
        $success = $user->save();

        if ($success) {
            $this->supervisionService->recordAction(Auth::id() ?? 'SYSTEM', 'CHANGEMENT_STATUT_COMPTE', $userId, 'Utilisateur', ['new_status' => $newStatus]);
        }
        return $success;
    }

    /**
     * Réinitialise le mot de passe d'un utilisateur par un administrateur.
     *
     * @param string $userId L'ID de l'utilisateur.
     * @return bool
     * @throws ElementNotFoundException Si l'utilisateur n'est pas trouvé.
     * @throws OperationFailedException Si l'envoi de l'email échoue.
     */
    public function resetPasswordByAdmin(string $userId): bool
    {
        $user = Utilisateur::find($userId);
        if (!$user) {
            throw new ElementNotFoundException("Utilisateur non trouvé.");
        }

        $newPasswordClair = Str::random(16); // Génère un mot de passe aléatoire
        $user->mot_de_passe = Hash::make($newPasswordClair);
        $success = $user->save();

        if ($success) {
            // Utilisation de Mailable pour l'envoi d'email
            $this->communicationService->sendEmail($user->email_principal, 'ADMIN_PASSWORD_RESET', ['login' => $user->login_utilisateur, 'nouveau_mdp' => $newPasswordClair]);
            $this->supervisionService->recordAction(Auth::id(), 'ADMIN_PASSWORD_RESET', $userId, 'Utilisateur');
        }
        return $success;
    }

    /**
     * Renvoye un email de validation pour un utilisateur.
     *
     * @param string $userId L'ID de l'utilisateur.
     * @return bool
     * @throws ElementNotFoundException Si l'utilisateur n'est pas trouvé.
     * @throws OperationFailedException Si l'email est déjà validé ou l'envoi échoue.
     */
    public function resendValidationEmail(string $userId): bool
    {
        $user = Utilisateur::find($userId);
        if (!$user) {
            throw new ElementNotFoundException("Utilisateur non trouvé.");
        }
        if ($user->email_valide) {
            throw new OperationFailedException("L'email de cet utilisateur est déjà validé.");
        }

        $tokenClair = Str::random(64);
        $user->token_validation_email = Hash::make($tokenClair);
        $user->date_expiration_token_reset = now()->addDay();
        $user->save();

        $validationLink = url("/validate-email/{$tokenClair}");
        // Utilisation de Mailable pour l'envoi d'email
        $this->communicationService->sendEmail($user->email_principal, 'VALIDATE_EMAIL', ['validation_link' => $validationLink]);
        $this->supervisionService->recordAction(Auth::id() ?? 'SYSTEM', 'RESEND_VALIDATION_EMAIL', $userId, 'Utilisateur');
        return true;
    }

    /**
     * Télécharge et associe une photo de profil à un utilisateur.
     *
     * @param string $userId L'ID de l'utilisateur.
     * @param array $fileData Les données du fichier uploadé.
     * @return string Le chemin relatif de la photo de profil.
     * @throws ValidationException Si le fichier est invalide.
     * @throws OperationFailedException Si l'upload ou la mise à jour échoue.
     */
    public function uploadProfilePicture(string $userId, array $fileData): string
    {
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        $relativePath = $this->documentService->uploadSecureFile($fileData, 'profile_pictures', $allowedMimeTypes, $maxSize);

        $user = Utilisateur::find($userId);
        if (!$user) {
            // Si l'utilisateur n'existe pas, supprimez le fichier uploadé
            $this->documentService->deleteFile($relativePath);
            throw new ElementNotFoundException("Utilisateur non trouvé.");
        }

        $user->photo_profil = $relativePath;
        $user->save();

        $this->supervisionService->recordAction($userId, 'UPLOAD_PROFILE_PICTURE', $userId, 'Utilisateur', ['path' => $relativePath]);

        return $relativePath;
    }

    /**
     * Crée une délégation de permissions.
     *
     * @param string $delegantId L'ID de l'utilisateur qui délègue.
     * @param string $delegatedId L'ID de l'utilisateur délégué.
     * @param string $traitementId L'ID du traitement délégué.
     * @param string $startDate La date de début de la délégation.
     * @param string $endDate La date de fin de la délégation.
     * @param string|null $contextId L'ID du contexte (optionnel).
     * @param string|null $contextType Le type du contexte (optionnel).
     * @return string L'ID de la délégation créée.
     * @throws OperationFailedException Si la création échoue.
     */
    public function createDelegation(string $delegantId, string $delegatedId, string $traitementId, string $startDate, string $endDate, ?string $contextId = null, ?string $contextType = null): string
    {
        $delegationId = $this->idGenerator->generateUniqueId('DEL');
        $delegationData = [
            'id_delegation' => $delegationId,
            'id_delegant' => $delegantId,
            'id_delegue' => $delegatedId,
            'id_traitement' => $traitementId,
            'date_debut' => $startDate,
            'date_fin' => $endDate,
            'statut' => 'Active',
            'contexte_id' => $contextId,
            'contexte_type' => $contextType
        ];

        if (!Delegation::create($delegationData)) {
            throw new OperationFailedException("Échec de la création de la délégation.");
        }

        $this->supervisionService->recordAction(Auth::id(), 'CREATION_DELEGATION', $delegationId, 'Delegation', ['delegated_to' => $delegatedId, 'traitement' => $traitementId]);
        return $delegationId;
    }

    /**
     * Révoque une délégation existante.
     *
     * @param string $delegationId L'ID de la délégation à révoquer.
     * @return bool
     * @throws ElementNotFoundException Si la délégation n'est pas trouvée.
     * @throws OperationFailedException Si la révocation échoue.
     */
    public function revokeDelegation(string $delegationId): bool
    {
        $delegation = Delegation::find($delegationId);
        if (!$delegation) {
            throw new ElementNotFoundException("Délégation non trouvée.");
        }

        $delegation->statut = 'Révoquée';
        $success = $delegation->save();

        if ($success) {
            $this->supervisionService->recordAction(Auth::id() ?? 'SYSTEM', 'REVOCATION_DELEGATION', $delegationId, 'Delegation');
        }
        return $success;
    }

    /**
     * Liste les délégations en fonction de filtres.
     *
     * @param array $filters Les filtres à appliquer.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listDelegations(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Delegation::query();

        foreach ($filters as $key => $value) {
            if (empty($value)) continue;
            $query->where($key, $value);
        }

        return $query->orderByDesc('date_debut')->get();
    }

    /**
     * Lit une délégation spécifique.
     *
     * @param string $delegationId L'ID de la délégation.
     * @return Delegation|null
     */
    public function readDelegation(string $delegationId): ?Delegation
    {
        return Delegation::find($delegationId);
    }

    /**
     * Gère la transition des rôles d'un utilisateur partant vers un nouvel utilisateur.
     * Réassigne les tâches et révoque les délégations.
     *
     * @param string $departingUserId L'ID de l'utilisateur partant.
     * @param string $newUserId L'ID du nouvel utilisateur.
     * @return array Un rapport des réassignations effectuées.
     * @throws ElementNotFoundException Si l'un des utilisateurs n'est pas trouvé.
     * @throws OperationFailedException Si une opération de réassignation échoue.
     */
    public function manageRoleTransitions(string $departingUserId, string $newUserId): array
    {
        $report = [
            'jury_reassigned' => 0,
            'pv_reassigned' => 0,
            'received_delegations_reassigned' => 0,
            'issued_delegations_revoked' => 0,
            'complaints_reassigned' => 0
        ];

        DB::transaction(function () use ($departingUserId, $newUserId, &$report) {
            $departingUser = Utilisateur::find($departingUserId);
            $newUser = Utilisateur::find($newUserId);

            if (!$departingUser || !$newUser) {
                throw new ElementNotFoundException("Utilisateur(s) non trouvé(s) pour la transition de rôle.");
            }

            // Réassigner les affectations de jury
            $report['jury_reassigned'] = Affecter::where('numero_enseignant', $departingUserId)
                ->whereIn('id_rapport_etudiant', function ($query) {
                    $query->select('id_rapport_etudiant')
                        ->from('rapport_etudiant')
                        ->whereIn('id_statut_rapport', ['RAP_CONF', 'RAP_EN_COMMISSION', 'RAP_CORRECT']);
                })
                ->update(['numero_enseignant' => $newUserId]);

            // Réassigner les PV en brouillon/rejeté
            $report['pv_reassigned'] = CompteRendu::where('id_redacteur', $departingUserId)
                ->whereIn('id_statut_pv', ['PV_BROUILLON', 'PV_REJETE'])
                ->update(['id_redacteur' => $newUserId]);

            // Réassigner les délégations reçues par l'utilisateur partant
            $report['received_delegations_reassigned'] = Delegation::where('id_delegue', $departingUserId)
                ->where('statut', 'Active')
                ->update(['id_delegue' => $newUserId]);

            // Révoquer les délégations émises par l'utilisateur partant
            $report['issued_delegations_revoked'] = Delegation::where('id_delegant', $departingUserId)
                ->where('statut', 'Active')
                ->update(['statut' => 'Révoquée']);

            // Réassigner les réclamations en cours de traitement
            $report['complaints_reassigned'] = Reclamation::where('numero_personnel_traitant', $departingUserId)
                ->whereIn('id_statut_reclamation', ['RECLA_OUVERTE', 'RECLA_EN_COURS'])
                ->update(['numero_personnel_traitant' => $newUserId]);

            // Archiver l'utilisateur partant
            $departingUser->statut_compte = 'archive';
            $departingUser->save();

            $this->supervisionService->recordAction(Auth::id(), 'TRANSITION_ROLE', $departingUserId, 'Utilisateur', ['new_user' => $newUserId, 'transition_report' => $report]);
        });

        return $report;
    }

    /**
     * Importe des étudiants à partir d'un fichier (CSV/Excel).
     *
     * @param string $filePath Le chemin du fichier temporaire.
     * @param array $mapping Le mappage des colonnes du fichier aux champs de la DB.
     * @return array Un rapport d'importation (succès, échecs, erreurs).
     * @throws OperationFailedException Si le fichier ne peut pas être lu.
     * @throws \InvalidArgumentException Si le fichier est d'un format non supporté.
     */
    public function importStudentsFromFile(string $filePath, array $mapping): array
    {
        $report = ['success' => 0, 'failures' => 0, 'errors' => []];
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);

        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestRow();
            $headerRow = $worksheet->rangeToArray('A1:' . $worksheet->getHighestColumn() . '1', null, true, false)[0];
        } catch (\Exception $e) {
            throw new OperationFailedException("Impossible de lire le fichier : " . $e->getMessage());
        }

        for ($row = 2; $row <= $highestRow; $row++) {
            $rowDataRaw = $worksheet->rangeToArray('A' . $row . ':' . $worksheet->getHighestColumn() . $row, null, true, false)[0];
            $rowData = [];
            foreach ($mapping as $fileColumn => $dbField) {
                $colIndex = array_search($fileColumn, $headerRow);
                if ($colIndex !== false && isset($rowDataRaw[$colIndex])) {
                    $rowData[$dbField] = $rowDataRaw[$colIndex];
                }
            }

            if (empty($rowData['nom']) || empty($rowData['prenom'])) {
                $report['failures']++;
                $report['errors'][] = "Ligne {$row}: Le nom et le prénom sont obligatoires.";
                continue;
            }

            try {
                $this->createEntity('etudiant', $rowData);
                $report['success']++;
            } catch (\Exception $e) {
                $report['failures']++;
                $report['errors'][] = "Ligne {$row}: " . $e->getMessage();
            }
        }
        $this->supervisionService->recordAction(Auth::id() ?? 'SYSTEM', 'IMPORT_ETUDIANTS', null, 'File', $report);
        return $report;
    }

    /**
     * Liste les entités (étudiants, enseignants, personnel) qui n'ont pas encore de compte utilisateur.
     *
     * @param string $entityType Le type d'entité à lister ('etudiant', 'enseignant', 'personnel').
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listEntitiesWithoutAccount(string $entityType): \Illuminate\Database\Eloquent\Collection
    {
        $model = $this->getModelForType($entityType);
        // Utilisation de la relation hasOne sur Utilisateur pour vérifier l'absence de compte
        return $model->doesntHave('utilisateur')->get();
    }

    /**
     * Méthode privée pour obtenir le modèle Eloquent approprié en fonction du type d'entité.
     *
     * @param string $type Le type d'entité ('etudiant', 'enseignant', 'personnel').
     * @return \Illuminate\Database\Eloquent\Model L'instance du modèle Eloquent.
     * @throws \InvalidArgumentException Si le type d'entité n'est pas géré.
     */
    protected function getModelForType(string $type): \Illuminate\Database\Eloquent\Model
    {
        return match (strtolower($type)) {
            'etudiant', 'etu' => new Etudiant(),
            'enseignant', 'ens' => new Enseignant(),
            'personnel', 'adm' => new PersonnelAdministratif(),
            default => throw new \InvalidArgumentException("Type de profil '{$type}' non géré."),
        };
    }
}
