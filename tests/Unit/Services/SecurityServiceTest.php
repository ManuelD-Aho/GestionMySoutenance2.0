<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\SecurityService;
use App\Services\SupervisionService;
use App\Services\CommunicationService;
use App\Services\SystemService;
use App\Utils\IdGenerator;
use App\Models\Utilisateur;
use App\Models\HistoriqueMotDePasse;
use App\Models\Session;
use App\Models\Rattacher;
use App\Models\Traitement;
use App\Models\Delegation;
use App\Exceptions\AccountBlockedException;
use App\Exceptions\AuthenticationException;
use App\Exceptions\ElementNotFoundException;
use App\Exceptions\InvalidAccountStateException;
use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\InvalidPasswordException;
use App\Exceptions\InvalidTokenException;
use App\Exceptions\OperationFailedException;
use App\Exceptions\PermissionDeniedException;
use App\Exceptions\TokenExpiredException;
use App\Mail\PasswordResetMail;
use App\Mail\EmailValidationMail;
use App\Mail\AdminPasswordResetMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session as LaravelSession; // Alias pour éviter le conflit avec le modèle Session
use Mockery;
use RobThree\Auth\TwoFactorAuth;

class SecurityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $securityService;
    protected $idGeneratorMock;
    protected $supervisionServiceMock;
    protected $communicationServiceMock;
    protected $systemServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idGeneratorMock = Mockery::mock(IdGenerator::class);
        $this->supervisionServiceMock = Mockery::mock(SupervisionService::class);
        $this->communicationServiceMock = Mockery::mock(CommunicationService::class);
        $this->systemServiceMock = Mockery::mock(SystemService::class);

        $this->securityService = new SecurityService(
            $this->idGeneratorMock,
            $this->supervisionServiceMock,
            $this->communicationServiceMock,
            $this->systemServiceMock
        );

        // Créer un utilisateur authentifié pour les tests qui enregistrent des actions
        $this->actingAs(Utilisateur::factory()->create(['numero_utilisateur' => 'USR-TEST-001']));

        // Créer les données de référence pour les groupes et traitements
        \App\Models\GroupeUtilisateur::factory()->create(['id_groupe_utilisateur' => 'GRP_ADMIN_SYS']);
        \App\Models\GroupeUtilisateur::factory()->create(['id_groupe_utilisateur' => 'GRP_ETUDIANT']);
        \App\Models\NiveauAccesDonne::factory()->create(['id_niveau_acces_donne' => 'ACCES_TOTAL']);
        \App\Models\NiveauAccesDonne::factory()->create(['id_niveau_acces_donne' => 'ACCES_PERSONNEL']);
        \App\Models\TypeUtilisateur::factory()->create(['id_type_utilisateur' => 'TYPE_ADMIN']);
        \App\Models\TypeUtilisateur::factory()->create(['id_type_utilisateur' => 'TYPE_ETUD']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'TRAIT_ADMIN_DASHBOARD_ACCEDER', 'libelle_traitement' => 'Accéder Dashboard Admin']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'TRAIT_ETUDIANT_PROFIL_GERER', 'libelle_traitement' => 'Gérer Profil Étudiant']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'TRAIT_ADMIN_IMPERSONATE_USER', 'libelle_traitement' => 'Impersonner Utilisateur']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'GENERATION_2FA_SECRET', 'libelle_traitement' => 'Génération Secret 2FA']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'ACTIVATION_2FA', 'libelle_traitement' => 'Activation 2FA']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'DESACTIVATION_2FA', 'libelle_traitement' => 'Désactivation 2FA']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'CHANGEMENT_MDP', 'libelle_traitement' => 'Changement Mot de Passe']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'IMPERSONATION_START', 'libelle_traitement' => 'Début Impersonation']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'IMPERSONATION_STOP', 'libelle_traitement' => 'Fin Impersonation']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'SYNCHRONISATION_RBAC', 'libelle_traitement' => 'Synchronisation RBAC']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'VALIDATION_EMAIL_SUCCES', 'libelle_traitement' => 'Validation Email Succès']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'ECHEC_ACTIVATION_2FA', 'libelle_traitement' => 'Échec Activation 2FA']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'ECHEC_LOGIN', 'libelle_traitement' => 'Échec Connexion']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'SUCCES_LOGIN', 'libelle_traitement' => 'Connexion Réussie']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'LOGOUT', 'libelle_traitement' => 'Déconnexion']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'MENU_ADMINISTRATION', 'libelle_traitement' => 'Administration', 'url_associee' => '/admin/dashboard']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'MENU_DASHBOARDS', 'libelle_traitement' => 'Tableaux de Bord', 'url_associee' => '/dashboard']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'MENU_ETUDIANT', 'libelle_traitement' => 'Espace Étudiant', 'url_associee' => '/student/dashboard']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'MENU_GESTION_COMPTES', 'libelle_traitement' => 'Gestion des Comptes', 'id_parent_traitement' => 'MENU_ADMINISTRATION']);
        \App\Models\Traitement::factory()->create(['id_traitement' => 'TRAIT_ADMIN_GERER_UTILISATEURS_LISTER', 'libelle_traitement' => 'Lister Utilisateurs', 'id_parent_traitement' => 'MENU_GESTION_COMPTES']);

        // Mock SystemService pour les paramètres
        $this->systemServiceMock->shouldReceive('getParametre')
            ->with('MAX_LOGIN_ATTEMPTS', Mockery::any())
            ->andReturn(5);
        $this->systemServiceMock->shouldReceive('getParametre')
            ->with('LOCKOUT_TIME_MINUTES', Mockery::any())
            ->andReturn(30);
        $this->systemServiceMock->shouldReceive('getParametre')
            ->with('PASSWORD_MIN_LENGTH', Mockery::any())
            ->andReturn(8);
        $this->systemServiceMock->shouldReceive('getParametre')
            ->with('PASSWORD_HISTORY_LIMIT', Mockery::any())
            ->andReturn(3);
        $this->systemServiceMock->shouldReceive('getParametre')
            ->with('MAIL_FROM_ADDRESS', Mockery::any())
            ->andReturn('from@example.com');
        $this->systemServiceMock->shouldReceive('getParametre')
            ->with('MAIL_FROM_NAME', Mockery::any())
            ->andReturn('App Name');
    }

    // --- Tests pour Authentification ---

    public function test_attempt_login_successful()
    {
        $user = Utilisateur::factory()->create([
            'login_utilisateur' => 'testuser',
            'email_principal' => 'test@example.com',
            'mot_de_passe' => Hash::make('password'),
            'statut_compte' => 'actif',
            'email_valide' => true,
            'preferences_2fa_active' => false,
        ]);

        $this->supervisionServiceMock->shouldReceive('recordAction')->once()->with($user->numero_utilisateur, 'SUCCES_LOGIN');
        $this->supervisionServiceMock->shouldReceive('recordAction')->once()->with('SYSTEM', 'SYNCHRONISATION_RBAC', $user->numero_utilisateur, 'Utilisateur');

        $result = $this->securityService->attemptLogin('testuser', 'password');

        $this->assertEquals('success', $result['status']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_attempt_login_with_2fa_required()
    {
        $user = Utilisateur::factory()->create([
            'login_utilisateur' => 'testuser2fa',
            'mot_de_passe' => Hash::make('password'),
            'statut_compte' => 'actif',
            'email_valide' => true,
            'preferences_2fa_active' => true,
            'secret_2fa' => 'ABCDEF1234567890',
        ]);

        $this->supervisionServiceMock->shouldReceive('recordAction')->once()->with($user->numero_utilisateur, 'SUCCES_LOGIN');

        $result = $this->securityService->attemptLogin('testuser2fa', 'password');

        $this->assertEquals('2fa_required', $result['status']);
        $this->assertFalse(Auth::check()); // Pas encore authentifié
        $this->assertTrue(LaravelSession::get('2fa_pending'));
        $this->assertEquals($user->numero_utilisateur, LaravelSession::get('2fa_user_id'));
    }

    public function test_attempt_login_throws_invalid_credentials_exception()
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->supervisionServiceMock->shouldReceive('recordAction')->once()->with('nonexistent', 'ECHEC_LOGIN', Mockery::any(), Mockery::any(), Mockery::any());
        $this->securityService->attemptLogin('nonexistent', 'wrongpassword');
    }

    public function test_attempt_login_throws_account_blocked_exception()
    {
        $user = Utilisateur::factory()->create([
            'login_utilisateur' => 'blockeduser',
            'mot_de_passe' => Hash::make('password'),
            'statut_compte' => 'bloque',
            'compte_bloque_jusqua' => now()->addMinutes(10),
        ]);

        $this->supervisionServiceMock->shouldReceive('recordAction')->once()->with($user->numero_utilisateur, 'ECHEC_LOGIN', Mockery::any(), Mockery::any(), Mockery::any());

        $this->expectException(AccountBlockedException::class);
        $this->securityService->attemptLogin('blockeduser', 'password');
    }

    public function test_logout_successfully()
    {
        $user = Utilisateur::factory()->create();
        Auth::login($user);

        $this->supervisionServiceMock->shouldReceive('recordAction')->once()->with($user->numero_utilisateur, 'LOGOUT');

        $this->securityService->logout();

        $this->assertGuest();
        $this->assertFalse(LaravelSession::has('user_data'));
    }

    // --- Tests pour Gestion des Mots de Passe ---

    public function test_request_password_reset_sends_email()
    {
        Mail::fake();
        $user = Utilisateur::factory()->create(['email_principal' => 'reset@example.com']);

        $this->securityService->requestPasswordReset('reset@example.com');

        $user->refresh();
        $this->assertNotNull($user->token_reset_mdp);
        $this->assertNotNull($user->date_expiration_token_reset);

        Mail::assertSent(PasswordResetMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email_principal);
        });
    }

    public function test_reset_password_via_token_successfully()
    {
        $user = Utilisateur::factory()->create([
            'mot_de_passe' => Hash::make('oldpassword'),
            'token_reset_mdp' => Hash::make('validtoken'),
            'date_expiration_token_reset' => now()->addHour(),
        ]);
        HistoriqueMotDePasse::create([
            'id_historique_mdp' => 'HMP-001',
            'numero_utilisateur' => $user->numero_utilisateur,
            'mot_de_passe_hache' => Hash::make('anotheroldpassword'),
            'date_changement' => now()->subDays(10),
        ]);

        $this->idGeneratorMock->shouldReceive('generateUniqueId')->once()->andReturn('HMP-002');
        $this->supervisionServiceMock->shouldReceive('recordAction')->once()->with($user->numero_utilisateur, 'CHANGEMENT_MDP');

        $result = $this->securityService->resetPasswordViaToken('validtoken', 'NewPassword123!');

        $this->assertTrue($result);
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->mot_de_passe));
        $this->assertNull($user->token_reset_mdp);
        $this->assertDatabaseHas('historique_mot_de_passe', [
            'numero_utilisateur' => $user->numero_utilisateur,
            'mot_de_passe_hache' => Hash::make('oldpassword'),
        ]);
    }

    public function test_change_password_successfully()
    {
        $user = Utilisateur::factory()->create([
            'mot_de_passe' => Hash::make('oldpassword'),
        ]);
        HistoriqueMotDePasse::create([
            'id_historique_mdp' => 'HMP-001',
            'numero_utilisateur' => $user->numero_utilisateur,
            'mot_de_passe_hache' => Hash::make('anotheroldpassword'),
            'date_changement' => now()->subDays(10),
        ]);

        $this->idGeneratorMock->shouldReceive('generateUniqueId')->once()->andReturn('HMP-002');
        $this->supervisionServiceMock->shouldReceive('recordAction')->once()->with($user->numero_utilisateur, 'CHANGEMENT_MDP');

        $result = $this->securityService->changePassword($user->numero_utilisateur, 'NewPassword123!', 'oldpassword');

        $this->assertTrue($result);
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->mot_de_passe));
    }

    public function test_change_password_throws_invalid_password_exception_for_wrong_old_password()
    {
        $this->expectException(InvalidPasswordException::class);
        $user = Utilisateur::factory()->create(['mot_de_passe' => Hash::make('oldpassword')]);
        $this->securityService->changePassword($user->numero_utilisateur, 'NewPassword123!', 'wrongoldpassword');
    }

    // --- Tests pour 2FA ---

    public function test_generate_and_store_2fa_secret_successfully()
    {
        $user = Utilisateur::factory()->create(['email_principal' => '2fa@example.com']);
        $this->supervisionServiceMock->shouldReceive('recordAction')->once()->with($user->numero_utilisateur, 'GENERATION_2FA_SECRET');

        $result = $this->securityService->generateAndStore2FASecret($user->numero_utilisateur);

        $this->assertArrayHasKey('secret', $result);
        $this->assertArrayHasKey('qr_code_url', $result);
        $user->refresh();
        $this->assertNotNull($user->secret_2fa);
    }

    public function test_activate_two_factor_authentication_successfully()
    {
        $user = Utilisateur::factory()->create(['secret_2fa' => 'TESTSECRET', 'preferences_2fa_active' => false]);
        $tfaMock = Mockery::mock('overload:RobThree\Auth\TwoFactorAuth');
        $tfaMock->shouldReceive('verifyCode')->once()->andReturn(true);

        $this->supervisionServiceMock->shouldReceive('recordAction')->once()->with($user->numero_utilisateur, 'ACTIVATION_2FA');

        $result = $this->securityService->activateTwoFactorAuthentication($user->numero_utilisateur, '123456');

        $this->assertTrue($result);
        $user->refresh();
        $this->assertTrue($user->preferences_2fa_active);
    }

    public function test_disable_two_factor_authentication_successfully()
    {
        $user = Utilisateur::factory()->create(['mot_de_passe' => Hash::make('password'), 'preferences_2fa_active' => true, 'secret_2fa' => 'TESTSECRET']);

        $this->supervisionServiceMock->shouldReceive('recordAction')->once()->with($user->numero_utilisateur, 'DESACTIVATION_2FA');

        $result = $this->securityService->disableTwoFactorAuthentication($user->numero_utilisateur, 'password');

        $this->assertTrue($result);
        $user->refresh();
        $this->assertFalse($user->preferences_2fa_active);
        $this->assertNull($user->secret_2fa);
    }

    // --- Tests pour RBAC ---

    public function test_user_has_permission_for_group_permission()
    {
        $adminUser = Utilisateur::factory()->create(['id_groupe_utilisateur' => 'GRP_ADMIN_SYS']);
        Rattacher::create(['id_groupe_utilisateur' => 'GRP_ADMIN_SYS', 'id_traitement' => 'TRAIT_ADMIN_DASHBOARD_ACCEDER']);
        Auth::login($adminUser);
        $this->securityService->updateUserSessionData($adminUser); // Simule la mise à jour de session

        $this->assertTrue($this->securityService->userHasPermission('TRAIT_ADMIN_DASHBOARD_ACCEDER'));
    }

    public function test_user_has_permission_for_delegated_permission()
    {
        $user = Utilisateur::factory()->create(['numero_utilisateur' => 'USR-002', 'id_groupe_utilisateur' => 'GRP_ETUDIANT']);
        $admin = Utilisateur::factory()->create(['numero_utilisateur' => 'ADM-001', 'id_groupe_utilisateur' => 'GRP_ADMIN_SYS']);
        Delegation::create([
            'id_delegation' => 'DEL-001',
            'id_delegant' => $admin->numero_utilisateur,
            'id_delegue' => $user->numero_utilisateur,
            'id_traitement' => 'TRAIT_ADMIN_DASHBOARD_ACCEDER',
            'date_debut' => now()->subDay(),
            'date_fin' => now()->addDay(),
            'statut' => 'Active',
        ]);
        Auth::login($user);
        $this->securityService->updateUserSessionData($user);

        $this->assertTrue($this->securityService->userHasPermission('TRAIT_ADMIN_DASHBOARD_ACCEDER'));
    }

    public function test_synchronize_user_sessions_permissions_updates_session_payload()
    {
        $user = Utilisateur::factory()->create(['id_groupe_utilisateur' => 'GRP_ETUDIANT']);
        Rattacher::create(['id_groupe_utilisateur' => 'GRP_ETUDIANT', 'id_traitement' => 'TRAIT_ETUDIANT_PROFIL_GERER']);

        // Simule une session active pour l'utilisateur
        $sessionData = serialize(['user_group_permissions' => [], 'user_delegations' => [], 'user_data' => $user->toArray()]);
        DB::table('sessions')->insert([
            'id' => 'session_id_123',
            'user_id' => $user->numero_utilisateur,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => base64_encode($sessionData),
            'last_activity' => time(),
            'session_lifetime' => 3600,
        ]);

        $this->supervisionServiceMock->shouldReceive('recordAction')->once()->with('SYSTEM', 'SYNCHRONISATION_RBAC', $user->numero_utilisateur, 'Utilisateur');

        $this->securityService->synchronizeUserSessionsPermissions($user->numero_utilisateur);

        $updatedSession = DB::table('sessions')->where('user_id', $user->numero_utilisateur)->first();
        $decodedPayload = unserialize(base64_decode($updatedSession->payload));

        $this->assertContains('TRAIT_ETUDIANT_PROFIL_GERER', $decodedPayload['user_group_permissions']);
    }

    // --- Tests pour Impersonation ---

    public function test_start_impersonation_successfully()
    {
        $admin = Utilisateur::factory()->create(['numero_utilisateur' => 'ADM-001', 'id_groupe_utilisateur' => 'GRP_ADMIN_SYS']);
        $targetUser = Utilisateur::factory()->create(['numero_utilisateur' => 'ETU-001', 'id_groupe_utilisateur' => 'GRP_ETUDIANT']);

        Auth::login($admin); // Admin est connecté
        $this->securityService->updateUserSessionData($admin); // Simule la session admin

        $this->supervisionServiceMock->shouldReceive('recordAction')->once()->with($admin->numero_utilisateur, 'IMPERSONATION_START', $targetUser->numero_utilisateur, 'Utilisateur');

        $result = $this->securityService->startImpersonation($admin->numero_utilisateur, $targetUser->numero_utilisateur);

        $this->assertTrue($result);
        $this->assertAuthenticatedAs($targetUser); // L'utilisateur cible est maintenant authentifié
        $this->assertTrue(LaravelSession::has('impersonator_data'));
        $this->assertEquals($admin->numero_utilisateur, LaravelSession::get('impersonator_data')['numero_utilisateur']);
    }

    public function test_stop_impersonation_successfully()
    {
        $admin = Utilisateur::factory()->create(['numero_utilisateur' => 'ADM-001', 'id_groupe_utilisateur' => 'GRP_ADMIN_SYS']);
        $targetUser = Utilisateur::factory()->create(['numero_utilisateur' => 'ETU-001', 'id_groupe_utilisateur' => 'GRP_ETUDIANT']);

        // Simule une session d'impersonation
        LaravelSession::put('impersonator_data', $admin->toArray());
        Auth::login($targetUser);
        $this->securityService->updateUserSessionData($targetUser);

        $this->supervisionServiceMock->shouldReceive('recordAction')->once()->with($admin->numero_utilisateur, 'IMPERSONATION_STOP', $targetUser->numero_utilisateur, 'Utilisateur');
        $this->supervisionServiceMock->shouldReceive('recordAction')->once()->with('SYSTEM', 'SYNCHRONISATION_RBAC', $admin->numero_utilisateur, 'Utilisateur'); // Appel de updateUserSessionData

        $result = $this->securityService->stopImpersonation();

        $this->assertTrue($result);
        $this->assertAuthenticatedAs($admin); // L'admin est de nouveau authentifié
        $this->assertFalse(LaravelSession::has('impersonator_data'));
    }

    // --- Tests pour validateEmailToken ---

    public function test_validate_email_token_successfully()
    {
        $tokenClair = 'validemailtoken';
        $user = Utilisateur::factory()->create([
            'email_valide' => false,
            'token_validation_email' => Hash::make($tokenClair),
            'date_expiration_token_reset' => now()->addHour(),
        ]);

        $this->supervisionServiceMock->shouldReceive('recordAction')->once()->with($user->numero_utilisateur, 'VALIDATION_EMAIL_SUCCES', $user->numero_utilisateur, 'Utilisateur');

        $validatedUser = $this->securityService->validateEmailToken($tokenClair);

        $this->assertTrue($validatedUser->email_valide);
        $this->assertNull($validatedUser->token_validation_email);
        $this->assertNull($validatedUser->date_expiration_token_reset);
        $this->assertDatabaseHas('utilisateur', ['numero_utilisateur' => $user->numero_utilisateur, 'email_valide' => true]);
    }

    public function test_validate_email_token_throws_token_expired_exception()
    {
        $this->expectException(TokenExpiredException::class);
        $tokenClair = 'expiredtoken';
        Utilisateur::factory()->create([
            'email_valide' => false,
            'token_validation_email' => Hash::make($tokenClair),
            'date_expiration_token_reset' => now()->subHour(),
        ]);

        $this->securityService->validateEmailToken($tokenClair);
    }
}
