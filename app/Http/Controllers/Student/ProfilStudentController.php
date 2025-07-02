<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use App\Services\SecurityService;
use App\Services\SupervisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Exceptions\{ElementNotFoundException, OperationFailedException, ValidationException};

/**
 * Gère le profil de l'étudiant : consultation, mise à jour, et photo.
 */
class ProfilStudentController extends Controller
{
    protected $userService;
    protected $securityService;
    protected $supervisionService;

    public function __construct(
        UserService $userService,
        SecurityService $securityService,
        SupervisionService $supervisionService
    ) {
        $this->userService = $userService;
        $this->securityService = $securityService;
        $this->supervisionService = $supervisionService;

        // Appliquer le middleware de permission pour l'accès au profil étudiant
        $this->middleware('can:TRAIT_ETUDIANT_PROFIL_GERER');
    }

    /**
     * Affiche la page de profil de l'étudiant connecté.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function show()
    {
        $user = Auth::user();

        try {
            $fullUserData = $this->userService->readCompleteUser($user->numero_utilisateur);
            if (!$fullUserData) {
                throw new ElementNotFoundException("Données de profil introuvables.");
            }

            return view('Student.profil_student', [
                'title' => 'Mon Profil',
                'user' => $fullUserData,
            ]);
        } catch (ElementNotFoundException $e) {
            return redirect()->route('student.dashboard')->with('error', 'Erreur lors du chargement du profil : ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors du chargement du profil étudiant {$user->numero_utilisateur}: " . $e->getMessage());
            return redirect()->route('student.dashboard')->with('error', 'Une erreur inattendue est survenue lors du chargement du profil.');
        }
    }

    /**
     * Traite la mise à jour des informations personnelles de l'étudiant.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'telephone' => 'nullable|string|max:20',
            'email_contact_secondaire' => 'nullable|email|max:255',
            'adresse_postale' => 'nullable|string|max:500',
            'ville' => 'nullable|string|max:100',
            'code_postal' => 'nullable|string|max:20',
            'contact_urgence_nom' => 'nullable|string|max:100',
            'contact_urgence_telephone' => 'nullable|string|max:20',
            'contact_urgence_relation' => 'nullable|string|max:50',
            'current_password' => 'nullable|required_with:new_password|string',
            'new_password' => 'nullable|string|min:8|confirmed',
        ]);

        try {
            $profileData = $request->only([
                'telephone', 'email_contact_secondaire', 'adresse_postale', 'ville', 'code_postal',
                'contact_urgence_nom', 'contact_urgence_telephone', 'contact_urgence_relation'
            ]);

            $accountData = [];
            if ($request->filled('new_password')) {
                $this->securityService->changePassword($user->numero_utilisateur, $request->new_password, $request->current_password);
            }

            $this->userService->updateUser($user->numero_utilisateur, $profileData, $accountData);

            return redirect()->route('student.profile.show')->with('success', 'Profil mis à jour avec succès.');
        } catch (ElementNotFoundException | OperationFailedException | ValidationException | InvalidPasswordException $e) {
            return back()->with('error', 'Erreur lors de la mise à jour : ' . $e->getMessage())->withInput();
        } catch (\Exception $e) {
            Log::error("Erreur lors de la mise à jour du profil étudiant {$user->numero_utilisateur}: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue lors de la mise à jour du profil.')->withInput();
        }
    }

    /**
     * Traite le téléversement de la photo de profil.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handlePhotoUpload(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'photo_profil_file' => 'required|image|mimes:jpeg,png,gif|max:2048', // Max 2MB
        ]);

        try {
            $fileData = $request->file('photo_profil_file');
            $this->userService->uploadProfilePicture($user->numero_utilisateur, $fileData->toArray()); // Convertir UploadedFile en array si le service l'attend
            return redirect()->route('student.profile.show')->with('success', 'Photo de profil mise à jour.');
        } catch (ValidationException | OperationFailedException | ElementNotFoundException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors du téléversement de la photo de profil pour {$user->numero_utilisateur}: " . $e->getMessage());
            return back()->with('error', 'Une erreur inattendue est survenue lors du téléversement de la photo.');
        }
    }
}
