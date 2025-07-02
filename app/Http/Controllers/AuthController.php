<?php

namespace App\Http\Controllers;

use App\Models\Utilisateur;
use App\Services\SecurityService;
use App\Services\SupervisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use App\Exceptions\{AuthenticationException, AccountBlockedException, InvalidAccountStateException, InvalidCredentialsException, InvalidPasswordException, TokenExpiredException, InvalidTokenException, OperationFailedException};

class AuthController extends Controller
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
     * Affiche le formulaire de connexion.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->intended('/dashboard');
        }
        return view('Auth.auth', ['form' => 'login', 'title' => 'Connexion']);
    }

    /**
     * Gère la tentative de connexion.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'identifiant' => 'required|string',
            'mot_de_passe' => 'required|string',
        ]);

        try {
            $result = $this->securityService->attemptLogin($request->identifiant, $request->mot_de_passe);

            if ($result['status'] === '2fa_required') {
                return redirect()->route('2fa.show');
            } elseif ($result['status'] === 'success') {
                $request->session()->regenerate();
                return redirect()->intended('/dashboard')->with('success', 'Connexion réussie !');
            }
        } catch (InvalidCredentialsException | AccountBlockedException | InvalidAccountStateException $e) {
            $type = ($e instanceof InvalidAccountStateException) ? 'warning' : 'error';
            return back()->withInput($request->only('identifiant'))->with($type, $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur de connexion: " . $e->getMessage());
            return back()->withInput($request->only('identifiant'))->with('error', 'Une erreur inattendue est survenue.');
        }
    }

    /**
     * Affiche le formulaire de vérification 2FA.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function show2faForm()
    {
        if (!Session::get('2fa_pending') || !Session::has('2fa_user_id')) {
            return redirect()->route('login');
        }
        return view('Auth.auth', ['form' => '2fa', 'title' => 'Vérification 2FA']);
    }

    /**
     * Gère la vérification du code 2FA.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handle2faVerification(Request $request)
    {
        $request->validate([
            'code_totp' => 'required|numeric|digits:6',
        ]);

        if (!Session::has('2fa_user_id')) {
            return redirect()->route('login');
        }

        try {
            $userId = Session::get('2fa_user_id');
            if ($this->securityService->verifyTwoFactorCode($userId, $request->code_totp)) {
                $user = Utilisateur::find($userId);
                Auth::login($user); // Authentifie l'utilisateur après 2FA
                $request->session()->regenerate();
                Session::forget(['2fa_pending', '2fa_user_id']); // Nettoie les flags 2FA
                return redirect()->intended('/dashboard')->with('success', 'Vérification 2FA réussie !');
            } else {
                return back()->with('error', 'Code 2FA incorrect. Veuillez réessayer.');
            }
        } catch (\Exception $e) {
            Log::error("Erreur lors de la vérification 2FA: " . $e->getMessage());
            return back()->with('error', 'Erreur lors de la vérification 2FA.');
        }
    }

    /**
     * Déconnecte l'utilisateur.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        $this->securityService->logout();
        return redirect()->route('login')->with('info', 'Vous avez été déconnecté.');
    }

    /**
     * Affiche le formulaire de mot de passe oublié.
     *
     * @return \Illuminate\View\View
     */
    public function showForgotPasswordForm()
    {
        return view('Auth.auth', ['form' => 'forgot_password', 'title' => 'Mot de passe oublié']);
    }

    /**
     * Gère la demande de réinitialisation de mot de passe.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleForgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            $this->securityService->requestPasswordReset($request->email);
            return redirect()->route('login')->with('success', 'Si votre email est enregistré, un lien de réinitialisation a été envoyé.');
        } catch (\Exception $e) {
            Log::error("Erreur demande MDP oublié: " . $e->getMessage());
            return back()->with('error', 'Une erreur est survenue lors de la demande.');
        }
    }

    /**
     * Affiche le formulaire de réinitialisation de mot de passe.
     *
     * @param string $token Le token de réinitialisation.
     * @return \Illuminate\View\View
     */
    public function showResetPasswordForm(string $token)
    {
        return view('Auth.auth', ['form' => 'reset_password', 'title' => 'Réinitialiser le mot de passe', 'token' => $token]);
    }

    /**
     * Gère la soumission du formulaire de réinitialisation de mot de passe.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleResetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email', // Ajouté pour la validation Laravel
            'password' => 'required|string|min:8|confirmed', // 'confirmed' vérifie password_confirmation
        ]);

        try {
            $this->securityService->resetPasswordViaToken($request->token, $request->password);
            return redirect()->route('login')->with('success', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');
        } catch (TokenExpiredException | InvalidTokenException | InvalidPasswordException $e) {
            $type = ($e instanceof InvalidPasswordException) ? 'error' : 'warning';
            return back()->with($type, $e->getMessage())->withInput($request->only('email'));
        } catch (\Exception $e) {
            Log::error("Erreur réinitialisation MDP: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue.')->withInput($request->only('email'));
        }
    }

    /**
     * Valide l'adresse email d'un utilisateur via un token.
     *
     * @param string $token Le token de validation.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function validateEmail(string $token)
    {
        try {
            $this->securityService->validateEmailToken($token);
            return redirect()->route('login')->with('success', 'Votre adresse email a été validée ! Vous pouvez vous connecter.');
        } catch (TokenExpiredException | InvalidTokenException | OperationFailedException $e) {
            $type = ($e instanceof OperationFailedException) ? 'warning' : 'error';
            return redirect()->route('login')->with($type, $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur validation email: " . $e->getMessage());
            return redirect()->route('login')->with('error', 'Une erreur est survenue lors de la validation.');
        }
    }
}
