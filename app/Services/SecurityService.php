<?php

namespace App\Services;

use App\Models\Utilisateur;
use App\Models\HistoriqueMotDePasse;
use App\Models\Session; // Le modèle Eloquent pour la table 'sessions'
use App\Models\Rattacher;
use App\Models\Traitement;
use App\Models\Delegation;
use App\Utils\IdGenerator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RobThree\Auth\TwoFactorAuth;
use App\Exceptions\{AuthenticationException,
    AccountBlockedException,
    InvalidAccountStateException,
    InvalidCredentialsException,
    InvalidPasswordException,
    ElementNotFoundException,
    PermissionDeniedException,
    TokenExpiredException,
    InvalidTokenException,
    OperationFailedException};
use App\Mail\PasswordResetMail; // Vos Mailables
use App\Mail\EmailValidationMail;
use App\Mail\AdminPasswordResetMail;
use Illuminate\Support\Facades\Mail; // Façade Mail de Laravel

class SecurityService
{
    protected $idGenerator;
    protected $supervisionService;
    protected $communicationService;
    protected $systemService;

    public function __construct(
        IdGenerator $idGenerator,
        SupervisionService $supervisionService,
        CommunicationService $communicationService,
        SystemService $systemService
    ) {
        $this->idGenerator = $idGenerator;
        $this->supervisionService = $supervisionService;
        $this->communicationService = $communicationService;
        $this->systemService = $systemService;
    }

    //================================================================
    // SECTION 1 : AUTHENTIFICATION & GESTION DE SESSION
    //================================================================

    /**
     * Tente de connecter un utilisateur. Gère les échecs, le blocage de compte et la 2FA.
     *
     * @param string $identifiant Le login ou l'email de l'utilisateur.
     * @param string $motDePasseClair Le mot de passe en clair.
     * @return array Le statut de la tentative de connexion (success, 2fa_required).
     * @throws AuthenticationException En cas d'identifiants invalides, compte bloqué ou non valide.
     */
    public function attemptLogin(string $identifiant, string $motDePasseClair): array
    {
        $utilisateur = Utilisateur::where('login_utilisateur', $identifiant)
            ->orWhere('email_principal', $identifiant)
            ->first();

        if (!$utilisateur || !Hash::check($motDePasseClair, $utilisateur->mot_de_passe)) {
            if ($utilisateur) {
                $this->handleFailedLoginAttempt($utilisateur);
            }
            $this->supervisionService->recordAction($identifiant, 'ECHEC_LOGIN', null, null, ['reason' => 'Identifiants invalides']);
            throw new InvalidCredentialsException("Le login ou le mot de passe est incorrect.");
        }

        if ($this->isAccountLocked($utilisateur)) {
            $this->supervisionService->recordAction($utilisateur->numero_utilisateur, 'ECHEC_LOGIN', null, null, ['reason' => 'Compte bloqué']);
            throw new AccountBlockedException("Votre compte est temporairement bloqué. Veuillez réessayer plus tard.");
        }

        if ($utilisateur->statut_compte !== 'actif' || !$utilisateur->email_valide) {
            $this->supervisionService->recordAction($utilisateur->numero_utilisateur, 'ECHEC_LOGIN', null, null, ['reason' => 'Compte non actif ou email non validé']);
            throw new InvalidAccountStateException("Votre compte n'est pas actif ou votre email n'a pas été validé.");
        }

        $this->resetLoginAttempts($utilisateur);
        $this->supervisionService->recordAction($utilisateur->numero_utilisateur, 'SUCCES_LOGIN');

        if ($utilisateur->preferences_2fa_active) {
            session(['2fa_user_id' => $utilisateur->numero_utilisateur, '2fa_pending' => true]);
            return ['status' => '2fa_required'];
        }

        Auth::login($utilisateur); // Authentifie l'utilisateur via le guard par défaut
        $this->updateUserSessionData($utilisateur); // Met à jour les permissions en session
        return ['status' => 'success'];
    }

    /**
     * Démarre une session utilisateur après une authentification réussie.
     *
     * @param string $numeroUtilisateur L'ID de l'utilisateur.
     * @return void
     * @throws ElementNotFoundException Si l'utilisateur n'est pas trouvé.
     */
    public function startUserSession(string $numeroUtilisateur): void
    {
        $utilisateur = Utilisateur::find($numeroUtilisateur);
        if (!$utilisateur) {
            throw new ElementNotFoundException("Impossible de démarrer la session pour un utilisateur inexistant.");
        }

        Auth::login($utilisateur); // Authentifie l'utilisateur via le guard par défaut
        $this->updateUserSessionData($utilisateur); // Met à jour les permissions en session

        $utilisateur->derniere_connexion = now();
        $utilisateur->save();
    }

    /**
     * Déconnecte l'utilisateur courant.
     *
     * @return void
     */
    public function logout(): void
    {
        $numeroUtilisateur = Auth::id() ?? 'ANONYMOUS';
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        $this->supervisionService->recordAction($numeroUtilisateur, 'LOGOUT');
    }

    /**
     * Vérifie si un utilisateur est actuellement connecté.
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return Auth::check();
    }

    /**
     * Récupère l'utilisateur connecté.
     *
     * @return Utilisateur|null
     */
    public function getAuthenticatedUser(): ?Utilisateur
    {
        return Auth::user();
    }

    //================================================================
    // SECTION 2 : GESTION DES MOTS DE PASSE
    //================================================================

    /**
     * Demande une réinitialisation de mot de passe pour un email donné.
     *
     * @param string $emailPrincipal L'email de l'utilisateur.
     * @return void
     */
    public function requestPasswordReset(string $emailPrincipal): void
    {
        $utilisateur = Utilisateur::where('email_principal', $emailPrincipal)->first();
        if (!$utilisateur) {
            return; // Ne pas révéler si l'email existe ou non
        }

        $tokenClair = Str::random(64); // Génère un token aléatoire
        $utilisateur->token_reset_mdp = Hash::make($tokenClair); // Hache le token avant de le stocker
        $utilisateur->date_expiration_token_reset = now()->addHour(); // Token valide 1 heure
        $utilisateur->save();

        $resetLink = url("/reset-password/{$tokenClair}"); // Génère l'URL de réinitialisation

        // Utilisation de Mailable pour l'envoi d'email
        Mail::to($emailPrincipal)->send(new PasswordResetMail($resetLink, 'Réinitialisation de votre mot de passe'));
    }

    /**
     * Réinitialise le mot de passe d'un utilisateur via un token.
     *
     * @param string $tokenClair Le token de réinitialisation en clair.
     * @param string $nouveauMotDePasseClair Le nouveau mot de passe en clair.
     * @return bool
     * @throws InvalidTokenException Si le token est invalide.
     * @throws TokenExpiredException Si le token a expiré.
     * @throws InvalidPasswordException Si le nouveau mot de passe est invalide ou déjà utilisé.
     */
    public function resetPasswordViaToken(string $tokenClair, string $nouveauMotDePasseClair): bool
    {
        // Le token stocké est haché, donc nous devons le comparer avec le token en clair haché
        $utilisateur = Utilisateur::where('token_reset_mdp', Hash::make($tokenClair))->first();

        if (!$utilisateur) {
            throw new InvalidTokenException("Token invalide ou déjà utilisé.");
        }
        if ($utilisateur->date_expiration_token_reset && now()->greaterThan($utilisateur->date_expiration_token_reset)) {
            throw new TokenExpiredException("Le token a expiré.");
        }

        return $this->setNewPassword($utilisateur, $nouveauMotDePasseClair);
    }

    /**
     * Modifie le mot de passe d'un utilisateur après vérification de l'ancien.
     *
     * @param string $numeroUtilisateur L'ID de l'utilisateur.
     * @param string $nouveauMotDePasseClair Le nouveau mot de passe en clair.
     * @param string $ancienMotDePasseClair L'ancien mot de passe en clair.
     * @return bool
     * @throws InvalidPasswordException Si l'ancien mot de passe est incorrect ou le nouveau est invalide.
     * @throws ElementNotFoundException Si l'utilisateur n'est pas trouvé.
     */
    public function changePassword(string $numeroUtilisateur, string $nouveauMotDePasseClair, string $ancienMotDePasseClair): bool
    {
        $utilisateur = Utilisateur::find($numeroUtilisateur);
        if (!$utilisateur) {
            throw new ElementNotFoundException("Utilisateur non trouvé.");
        }
        if (!Hash::check($ancienMotDePasseClair, $utilisateur->mot_de_passe)) {
            throw new InvalidPasswordException("L'ancien mot de passe est incorrect.");
        }
        return $this->setNewPassword($utilisateur, $nouveauMotDePasseClair);
    }

    //================================================================
    // SECTION 3 : AUTHENTIFICATION À DEUX FACTEURS (2FA)
    //================================================================

    /**
     * Génère et stocke un secret 2FA pour un utilisateur, et retourne l'URL du QR code.
     *
     * @param string $numeroUtilisateur L'ID de l'utilisateur.
     * @return array Contenant le secret et l'URL du QR code.
     * @throws ElementNotFoundException Si l'utilisateur n'est pas trouvé.
     */
    public function generateAndStore2FASecret(string $numeroUtilisateur): array
    {
        $utilisateur = Utilisateur::find($numeroUtilisateur);
        if (!$utilisateur) {
            throw new ElementNotFoundException("Utilisateur non trouvé.");
        }

        $tfa = new TwoFactorAuth(config('app.name'));
        $secret = $tfa->createSecret();
        $qrCodeUrl = $tfa->getQRCodeImageAsDataUri($utilisateur->email_principal, $secret);

        $utilisateur->secret_2fa = $secret;
        $utilisateur->save();

        $this->supervisionService->recordAction($numeroUtilisateur, 'GENERATION_2FA_SECRET');

        return ['secret' => $secret, 'qr_code_url' => $qrCodeUrl];
    }

    /**
     * Active l'authentification à deux facteurs pour un utilisateur.
     *
     * @param string $numeroUtilisateur L'ID de l'utilisateur.
     * @param string $codeTOTP Le code TOTP fourni par l'utilisateur.
     * @return bool
     * @throws OperationFailedException Si aucun secret 2FA n'est généré.
     * @throws InvalidCredentialsException Si le code TOTP est incorrect.
     */
    public function activateTwoFactorAuthentication(string $numeroUtilisateur, string $codeTOTP): bool
    {
        $utilisateur = Utilisateur::find($numeroUtilisateur);
        if (!$utilisateur || empty($utilisateur->secret_2fa)) {
            throw new OperationFailedException("Impossible d'activer la 2FA : aucun secret n'est généré.");
        }

        if (!$this->verifyTwoFactorCode($utilisateur->numero_utilisateur, $codeTOTP, $utilisateur->secret_2fa)) {
            $this->supervisionService->recordAction($numeroUtilisateur, 'ECHEC_ACTIVATION_2FA', null, null, ['reason' => 'Code invalide']);
            throw new InvalidCredentialsException("Le code de vérification est incorrect.");
        }

        $utilisateur->preferences_2fa_active = true;
        $success = $utilisateur->save();

        if ($success) {
            $this->supervisionService->recordAction($numeroUtilisateur, 'ACTIVATION_2FA');
        }
        return $success;
    }

    /**
     * Désactive l'authentification à deux facteurs pour un utilisateur.
     *
     * @param string $numeroUtilisateur L'ID de l'utilisateur.
     * @param string $motDePasseClair Le mot de passe de l'utilisateur pour confirmation.
     * @return bool
     * @throws InvalidPasswordException Si le mot de passe est incorrect.
     * @throws ElementNotFoundException Si l'utilisateur n'est pas trouvé.
     */
    public function disableTwoFactorAuthentication(string $numeroUtilisateur, string $motDePasseClair): bool
    {
        $utilisateur = Utilisateur::find($numeroUtilisateur);
        if (!$utilisateur) {
            throw new ElementNotFoundException("Utilisateur non trouvé.");
        }
        if (!Hash::check($motDePasseClair, $utilisateur->mot_de_passe)) {
            throw new InvalidPasswordException("Le mot de passe est incorrect.");
        }

        $utilisateur->preferences_2fa_active = false;
        $utilisateur->secret_2fa = null;
        $success = $utilisateur->save();

        if ($success) {
            $this->supervisionService->recordAction($numeroUtilisateur, 'DESACTIVATION_2FA');
        }
        return $success;
    }

    /**
     * Vérifie un code TOTP pour un utilisateur.
     *
     * @param string $numeroUtilisateur L'ID de l'utilisateur.
     * @param string $codeTOTP Le code TOTP à vérifier.
     * @param string|null $secret Le secret 2FA (si déjà connu, sinon il sera récupéré).
     * @return bool
     */
    public function verifyTwoFactorCode(string $numeroUtilisateur, string $codeTOTP, ?string $secret = null): bool
    {
        if ($secret === null) {
            $user = Utilisateur::find($numeroUtilisateur);
            if (!$user || empty($user->secret_2fa)) {
                return false;
            }
            $secret = $user->secret_2fa;
        }
        $tfa = new TwoFactorAuth(config('app.name'));
        return $tfa->verifyCode($secret, $codeTOTP);
    }

    //================================================================
    // SECTION 4 : AUTORISATION & PERMISSIONS
    //================================================================

    /**
     * Vérifie si l'utilisateur connecté possède une permission spécifique.
     *
     * @param string $permissionCode Le code de la permission (TRAIT_...).
     * @param string|null $contexteId L'ID de l'entité concernée (optionnel).
     * @param string|null $contexteType Le type de l'entité concernée (optionnel).
     * @return bool
     */
    public function userHasPermission(string $permissionCode, ?string $contexteId = null, ?string $contexteType = null): bool
    {
        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();

        // Les permissions du groupe sont stockées en session lors de la connexion
        $groupPermissions = session('user_group_permissions', []);

        // Les délégations actives sont également stockées en session
        $delegations = session('user_delegations', []);

        // Vérifier les permissions du groupe
        if (in_array($permissionCode, $groupPermissions)) {
            return true;
        }

        // Vérifier les permissions déléguées
        foreach ($delegations as $delegation) {
            if ($delegation['id_traitement'] === $permissionCode) {
                // Si la délégation n'a pas de contexte spécifique OU si le contexte correspond
                if ($delegation['contexte_id'] === null || ($delegation['contexte_id'] === $contexteId && $delegation['contexte_type'] === $contexteType)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Synchronise les permissions d'un utilisateur dans toutes ses sessions actives.
     *
     * @param string $numeroUtilisateur L'ID de l'utilisateur.
     * @return void
     */
    public function synchronizeUserSessionsPermissions(string $numeroUtilisateur): void
    {
        $utilisateur = Utilisateur::find($numeroUtilisateur);
        if (!$utilisateur) {
            return;
        }

        // Récupérer les nouvelles permissions du groupe
        $newGroupPermissions = Rattacher::where('id_groupe_utilisateur', $utilisateur->id_groupe_utilisateur)
            ->pluck('id_traitement')
            ->toArray();

        // Récupérer les nouvelles délégations actives
        $newDelegations = Delegation::where('id_delegue', $utilisateur->numero_utilisateur)
            ->where('statut', 'Active')
            ->where('date_debut', '<=', now())
            ->where('date_fin', '>=', now())
            ->get(['id_traitement', 'contexte_id', 'contexte_type'])
            ->toArray();

        // Mettre à jour toutes les sessions de l'utilisateur
        // La table 'sessions' de Laravel stocke le payload sérialisé et encodé en base64
        DB::table('sessions')->where('user_id', $numeroUtilisateur)->each(function ($session) use ($newGroupPermissions, $newDelegations) {
            $sessionData = unserialize(base64_decode($session->payload));
            $sessionData['user_group_permissions'] = $newGroupPermissions;
            $sessionData['user_delegations'] = $newDelegations;
            $session->payload = base64_encode(serialize($sessionData));
            DB::table('sessions')->where('id', $session->id)->update(['payload' => $session->payload]);
        });

        $this->supervisionService->recordAction('SYSTEM', 'SYNCHRONISATION_RBAC', $numeroUtilisateur, 'Utilisateur');
    }

    //================================================================
    // SECTION 5 : IMPERSONATION
    //================================================================

    /**
     * Démarre une session d'impersonation pour un administrateur.
     *
     * @param string $adminId L'ID de l'administrateur.
     * @param string $targetUserId L'ID de l'utilisateur à impersonner.
     * @return bool
     * @throws PermissionDeniedException Si l'administrateur n'a pas les droits.
     * @throws OperationFailedException Si l'administrateur tente de s'impersonner lui-même.
     * @throws ElementNotFoundException Si l'un des utilisateurs n'est pas trouvé.
     */
    public function startImpersonation(string $adminId, string $targetUserId): bool
    {
        $admin = Utilisateur::find($adminId);
        $targetUser = Utilisateur::find($targetUserId);

        if (!$admin || !$targetUser || $admin->id_groupe_utilisateur !== 'GRP_ADMIN_SYS') {
            throw new PermissionDeniedException("Action d'impersonation non autorisée.");
        }
        if ($adminId === $targetUserId) {
            throw new OperationFailedException("Vous ne pouvez pas vous impersonnaliser vous-même.");
        }

        // Stocker les informations de l'admin dans la session
        session()->put('impersonator_data', $admin->toArray());

        // Déconnecter l'admin et connecter l'utilisateur cible
        Auth::logout();
        Auth::login($targetUser);
        $this->updateUserSessionData($targetUser); // Met à jour les permissions de l'utilisateur cible en session

        $this->supervisionService->recordAction($adminId, 'IMPERSONATION_START', $targetUserId, 'Utilisateur');

        return true;
    }

    /**
     * Arrête la session d'impersonation et restaure la session de l'administrateur.
     *
     * @return bool
     * @throws OperationFailedException Si aucune impersonation n'est en cours.
     * @throws ElementNotFoundException Si l'administrateur d'origine n'est pas trouvé.
     */
    public function stopImpersonation(): bool
    {
        if (!session()->has('impersonator_data')) {
            throw new OperationFailedException("Aucune impersonation n'est en cours.");
        }

        $adminData = session('impersonator_data');
        $targetUserId = Auth::id(); // L'utilisateur actuellement connecté (impersonné)

        $admin = Utilisateur::find($adminData['numero_utilisateur']);
        if (!$admin) {
            throw new ElementNotFoundException("Administrateur d'origine non trouvé.");
        }

        // Déconnecter l'utilisateur impersonné et reconnecter l'administrateur
        Auth::logout();
        Auth::login($admin);
        $this->updateUserSessionData($admin); // Met à jour les permissions de l'admin en session

        session()->forget('impersonator_data'); // Nettoyer la session d'impersonation

        $this->supervisionService->recordAction($admin->numero_utilisateur, 'IMPERSONATION_STOP', $targetUserId, 'Utilisateur');

        return true;
    }

    /**
     * Vérifie si une session d'impersonation est active.
     *
     * @return bool
     */
    public function isImpersonating(): bool
    {
        return session()->has('impersonator_data');
    }

    /**
     * Récupère les données de l'administrateur qui impersonne.
     *
     * @return array|null
     */
    public function getImpersonatorData(): ?array
    {
        return session('impersonator_data');
    }

    //================================================================
    // SECTION 6 : GESTION DYNAMIQUE DE L'INTERFACE
    //================================================================

    /**
     * Construit la structure hiérarchique du menu de navigation pour l'utilisateur connecté.
     *
     * @return array La structure du menu prête à être parcourue dans une vue.
     */
    public function buildMenuForAuthenticatedUser(): array
    {
        if (!Auth::check()) {
            return [];
        }

        $user = Auth::user();
        $permissionsUtilisateur = session('user_group_permissions', []);
        $delegationsUtilisateur = session('user_delegations', []);

        // Ajouter les permissions déléguées
        foreach ($delegationsUtilisateur as $delegation) {
            $permissionsUtilisateur[] = $delegation['id_traitement'];
        }
        $permissionsUtilisateur = array_unique($permissionsUtilisateur);

        if (empty($permissionsUtilisateur)) {
            return [];
        }

        // Récupérer tous les éléments de menu auxquels l'utilisateur a droit
        $itemsMenu = Traitement::whereIn('id_traitement', $permissionsUtilisateur)
            ->where('id_traitement', 'LIKE', 'MENU_%') // Filtrer pour les éléments de menu
            ->orderBy('ordre_affichage')
            ->orderBy('libelle_traitement')
            ->get()
            ->toArray();

        // Construire l'arborescence
        $menuHierarchique = [];
        $itemsParId = [];

        foreach ($itemsMenu as $item) {
            $itemsParId[$item['id_traitement']] = $item;
            $itemsParId[$item['id_traitement']]['enfants'] = [];
        }

        foreach ($itemsParId as $id => &$item) {
            if (!empty($item['id_parent_traitement']) && isset($itemsParId[$item['id_parent_traitement']])) {
                $itemsParId[$item['id_parent_traitement']]['enfants'][] = &$item;
            }
        }
        unset($item);

        foreach ($itemsParId as $id => $item) {
            if (empty($item['id_parent_traitement']) || !isset($itemsParId[$item['id_parent_traitement']])) {
                $menuHierarchique[] = $item;
            }
        }

        // Trier les éléments de premier niveau et leurs enfants
        usort($menuHierarchique, function($a, $b) {
            return $a['ordre_affichage'] <=> $b['ordre_affichage'];
        });
        foreach ($menuHierarchique as &$item) {
            if (!empty($item['enfants'])) {
                usort($item['enfants'], function($a, $b) {
                    return $a['ordre_affichage'] <=> $b['ordre_affichage'];
                });
            }
        }
        unset($item);

        return $menuHierarchique;
    }

    //================================================================
    // SECTION 7 : VALIDATION D'EMAIL
    //================================================================

    /**
     * Valide l'adresse email d'un utilisateur à partir d'un token fourni.
     *
     * @param string $tokenClair Le token reçu dans l'URL de validation.
     * @return Utilisateur L'utilisateur dont l'email vient d'être validé.
     * @throws InvalidTokenException Si le token ne correspond à aucun utilisateur.
     * @throws OperationFailedException Si l'email de l'utilisateur est déjà validé.
     * @throws TokenExpiredException Si le token a dépassé sa date de validité.
     */
    public function validateEmailToken(string $tokenClair): Utilisateur
    {
        // Le token stocké est haché, donc nous devons le comparer avec le token en clair haché
        $utilisateur = Utilisateur::where('token_validation_email', Hash::make($tokenClair))->first();

        if (!$utilisateur) {
            throw new InvalidTokenException("Token de validation d'email invalide ou déjà utilisé.");
        }

        if ($utilisateur->email_valide) {
            throw new OperationFailedException("L'email est déjà validé pour cet utilisateur.");
        }

        if ($utilisateur->date_expiration_token_reset && now()->greaterThan($utilisateur->date_expiration_token_reset)) {
            throw new TokenExpiredException("Le token de validation d'email a expiré. Veuillez demander un nouveau lien.");
        }

        $utilisateur->email_valide = true;
        $utilisateur->token_validation_email = null;
        $utilisateur->date_expiration_token_reset = null;
        $success = $utilisateur->save();

        if (!$success) {
            throw new OperationFailedException("Échec de la mise à jour du statut de l'email.");
        }

        $this->supervisionService->recordAction(
            $utilisateur->numero_utilisateur,
            'VALIDATION_EMAIL_SUCCES',
            $utilisateur->numero_utilisateur,
            'Utilisateur'
        );

        return $utilisateur;
    }

    //================================================================
    // SECTION 8 : LOGIQUE INTERNE & MÉTHODES PRIVÉES
    //================================================================

    /**
     * Gère les tentatives de connexion échouées pour un utilisateur.
     *
     * @param Utilisateur $user L'instance de l'utilisateur.
     * @return void
     */
    protected function handleFailedLoginAttempt(Utilisateur $user): void
    {
        $user->increment('tentatives_connexion_echouees');
        $maxAttempts = (int) $this->systemService->getParametre('MAX_LOGIN_ATTEMPTS', 5);
        $lockoutTime = (int) $this->systemService->getParametre('LOCKOUT_TIME_MINUTES', 30);

        if ($user->tentatives_connexion_echouees >= $maxAttempts) {
            $user->update([
                'statut_compte' => 'bloque',
                'compte_bloque_jusqua' => now()->addMinutes($lockoutTime)
            ]);
        }
    }

    /**
     * Réinitialise le compteur de tentatives de connexion pour un utilisateur.
     *
     * @param Utilisateur $user L'instance de l'utilisateur.
     * @return void
     */
    protected function resetLoginAttempts(Utilisateur $user): void
    {
        $user->update([
            'tentatives_connexion_echouees' => 0,
            'compte_bloque_jusqua' => null,
            'derniere_connexion' => now()
        ]);
    }

    /**
     * Vérifie si un compte utilisateur est bloqué.
     *
     * @param Utilisateur $user L'instance de l'utilisateur.
     * @return bool
     */
    protected function isAccountLocked(Utilisateur $user): bool
    {
        if ($user->statut_compte === 'bloque' && $user->compte_bloque_jusqua && now()->lt($user->compte_bloque_jusqua)) {
            return true;
        }
        // Si le temps de blocage est écoulé, réactiver le compte
        if ($user->statut_compte === 'bloque' && $user->compte_bloque_jusqua && now()->gte($user->compte_bloque_jusqua)) {
            $user->update(['statut_compte' => 'actif', 'compte_bloque_jusqua' => null]);
        }
        return false;
    }

    /**
     * Définit un nouveau mot de passe pour un utilisateur, en vérifiant la robustesse et l'historique.
     *
     * @param Utilisateur $user L'instance de l'utilisateur.
     * @param string $nouveauMotDePasseClair Le nouveau mot de passe en clair.
     * @return bool
     * @throws InvalidPasswordException Si le mot de passe ne respecte pas les critères ou est dans l'historique.
     */
    protected function setNewPassword(Utilisateur $user, string $nouveauMotDePasseClair): bool
    {
        $this->verifyPasswordStrength($nouveauMotDePasseClair);
        if ($this->isNewPasswordInHistory($user, $nouveauMotDePasseClair)) {
            throw new InvalidPasswordException("Ce mot de passe a été utilisé récemment. Veuillez en choisir un autre.");
        }

        DB::transaction(function () use ($user, $nouveauMotDePasseClair) {
            // Sauvegarder l'ancien mot de passe dans l'historique
            HistoriqueMotDePasse::create([
                'id_historique_mdp' => $this->idGenerator->generateUniqueId('HMP'),
                'numero_utilisateur' => $user->numero_utilisateur,
                'mot_de_passe_hache' => $user->mot_de_passe, // L'ancien mot de passe haché
                'date_changement' => now()
            ]);

            // Mettre à jour le nouveau mot de passe
            $user->mot_de_passe = Hash::make($nouveauMotDePasseClair);
            $user->token_reset_mdp = null;
            $user->date_expiration_token_reset = null;
            $user->save();
        });

        $this->supervisionService->recordAction($user->numero_utilisateur, 'CHANGEMENT_MDP');
        return true;
    }

    /**
     * Vérifie la robustesse d'un mot de passe.
     *
     * @param string $password Le mot de passe à vérifier.
     * @return void
     * @throws InvalidPasswordException Si le mot de passe ne respecte pas les critères.
     */
    protected function verifyPasswordStrength(string $password): void
    {
        $minLength = (int) $this->systemService->getParametre('PASSWORD_MIN_LENGTH', 8); // Lecture dynamique

        if (strlen($password) < $minLength) {
            throw new InvalidPasswordException("Le mot de passe doit contenir au moins {$minLength} caractères.");
        }
        if (!preg_match('/[A-Z]/', $password)) {
            throw new InvalidPasswordException("Le mot de passe doit contenir au moins une majuscule.");
        }
        if (!preg_match('/[a-z]/', $password)) {
            throw new InvalidPasswordException("Le mot de passe doit contenir au moins une minuscule.");
        }
        if (!preg_match('/[0-9]/', $password)) {
            throw new InvalidPasswordException("Le mot de passe doit contenir au moins un chiffre.");
        }
    }

    /**
     * Vérifie si le nouveau mot de passe est présent dans l'historique récent de l'utilisateur.
     *
     * @param Utilisateur $user L'instance de l'utilisateur.
     * @param string $newPasswordClair Le nouveau mot de passe en clair.
     * @return bool
     */
    protected function isNewPasswordInHistory(Utilisateur $user, string $newPasswordClair): bool
    {
        $historyLimit = (int) $this->systemService->getParametre('PASSWORD_HISTORY_LIMIT', 3); // Lecture dynamique
        $history = HistoriqueMotDePasse::where('numero_utilisateur', $user->numero_utilisateur)
            ->orderByDesc('date_changement')
            ->limit($historyLimit)
            ->get();

        foreach ($history as $entry) {
            if (Hash::check($newPasswordClair, $entry->mot_de_passe_hache)) {
                return true;
            }
        }
        return false;
    }
}
