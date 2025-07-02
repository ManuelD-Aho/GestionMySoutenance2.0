<?php

namespace App\Http\Controllers;

use App\Services\DocumentService;
use App\Services\SecurityService;
use App\Services\SupervisionService;
use App\Exceptions\ElementNotFoundException;
use App\Exceptions\PermissionDeniedException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetController extends Controller
{
    protected $documentService;
    protected $securityService;
    protected $supervisionService;

    public function __construct(
        DocumentService $documentService,
        SecurityService $securityService,
        SupervisionService $supervisionService
    ) {
        $this->documentService = $documentService;
        $this->securityService = $securityService;
        $this->supervisionService = $supervisionService;
    }

    /**
     * Sert les fichiers protégés (documents générés, photos de profil, etc.).
     *
     * @param Request $request L'objet requête HTTP.
     * @param string $filePath Le chemin relatif du fichier (ex: 'documents_generes/mon_fichier.pdf').
     * @return BinaryFileResponse
     */
    public function serve(Request $request, string $filePath)
    {
        $user = Auth::user();
        $userId = $user ? $user->numero_utilisateur : 'ANONYMOUS';

        try {
            // Vérifier la permission d'accès générale pour les administrateurs
            if ($this->securityService->userHasPermission('TRAIT_ADMIN_ACCES_FICHIERS_PROTEGES')) {
                // L'administrateur a un accès universel
            }
            // Vérifier la propriété du document généré
            elseif (Str::startsWith($filePath, 'documents_generes/')) {
                $filename = basename($filePath);
                if (!$this->documentService->verifyDocumentOwnership($filename, $userId)) {
                    throw new PermissionDeniedException("Vous n'êtes pas le propriétaire de ce document.");
                }
            }
            // Vérifier l'accès aux photos de profil
            elseif (Str::startsWith($filePath, 'profile_pictures/')) {
                // Assurez-vous que l'utilisateur accède à sa propre photo ou a une permission spécifique
                if ($user->photo_profil !== $filePath && !$this->securityService->userHasPermission('TRAIT_VIEW_ALL_PROFILE_PICTURES')) {
                    throw new PermissionDeniedException("Vous n'êtes pas autorisé à accéder à cette photo de profil.");
                }
            }
            // Vérifier l'accès du personnel administratif aux documents étudiants
            elseif ($this->securityService->userHasPermission('TRAIT_PERS_ADMIN_ACCES_DOCUMENTS_ETUDIANTS')) {
                // Accès autorisé pour le personnel administratif
            }
            else {
                throw new PermissionDeniedException("Vous n'êtes pas autorisé à accéder à ce fichier.");
            }

            $fullPath = Storage::disk('public')->path($filePath);

            if (!file_exists($fullPath) || !is_file($fullPath)) {
                throw new ElementNotFoundException("Le fichier demandé n'existe pas.");
            }

            $this->supervisionService->recordAction($userId, 'ACCES_ASSET_SUCCES', null, null, ['file' => $filePath]);

            // Retourner le fichier comme réponse binaire
            return response()->file($fullPath);

        } catch (PermissionDeniedException $e) {
            $this->supervisionService->recordAction($userId, 'ACCES_ASSET_ECHEC', null, null, ['reason' => $e->getMessage(), 'file' => $filePath]);
            abort(403, $e->getMessage());
        } catch (ElementNotFoundException $e) {
            $this->supervisionService->recordAction($userId, 'ACCES_ASSET_ECHEC', null, null, ['reason' => $e->getMessage(), 'file' => $filePath]);
            abort(404, $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Erreur lors du service de l'asset {$filePath}: " . $e->getMessage());
            $this->supervisionService->recordAction($userId, 'ACCES_ASSET_ECHEC', null, null, ['reason' => 'Erreur interne', 'file' => $filePath, 'error' => $e->getMessage()]);
            abort(500, "Erreur lors de la lecture du fichier.");
        }
    }
}
