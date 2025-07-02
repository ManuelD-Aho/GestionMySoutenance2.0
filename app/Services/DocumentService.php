<?php

namespace App\Services;

use App\Models\DocumentGenere;
use App\Models\RapportModele;
use App\Models\RapportModeleSection;
use App\Models\TypeDocumentRef;
use App\Models\Etudiant;
use App\Models\Inscrire;
use App\Models\AnneeAcademique;
use App\Models\Evaluer;
use App\Models\CompteRendu;
use App\Models\RapportEtudiant;
use App\Models\SectionRapport;
use App\Utils\IdGenerator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Exceptions\{ElementNotFoundException, OperationFailedException, ValidationException};
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use TCPDF; // Assurez-vous que TCPDF est correctement installé et autoloadé
use Illuminate\Support\Str; // Pour Str::slug et Str::random

class DocumentService
{
    protected $idGenerator;
    protected $supervisionService;
    protected $systemService;

    public function __construct(
        IdGenerator $idGenerator,
        SupervisionService $supervisionService,
        SystemService $systemService
    ) {
        $this->idGenerator = $idGenerator;
        $this->supervisionService = $supervisionService;
        $this->systemService = $systemService;
    }

    // ====================================================================
    // SECTION 1: GÉNÉRATION DE DOCUMENTS PDF
    // ====================================================================

    /**
     * Génère une attestation de scolarité au format PDF.
     *
     * @param string $studentId L'ID de l'étudiant.
     * @param string $academicYearId L'ID de l'année académique.
     * @return string L'ID du document généré.
     * @throws ElementNotFoundException Si les données nécessaires sont introuvables.
     * @throws OperationFailedException Si le modèle de l'attestation est introuvable ou la génération échoue.
     */
    public function generateSchoolingCertificate(string $studentId, string $academicYearId): string
    {
        $student = Etudiant::find($studentId);
        $academicYear = AnneeAcademique::find($academicYearId);
        $inscription = Inscrire::where('numero_carte_etudiant', $studentId)
            ->where('id_annee_academique', $academicYearId)
            ->first();

        if (!$student || !$inscription || !$academicYear) {
            throw new ElementNotFoundException("Données d'inscription introuvables pour l'étudiant {$studentId} pour l'année {$academicYearId}.");
        }

        $templatePath = resource_path('views/templates/pdf/schooling_certificate.blade.php');
        if (!file_exists($templatePath)) {
            throw new OperationFailedException("Le modèle de l'attestation est introuvable.");
        }

        $htmlContent = view('templates.pdf.schooling_certificate', [
            'student' => $student,
            'academicYear' => $academicYear,
            'inscription' => $inscription,
            'generationDate' => now()->format('d/m/Y')
        ])->render();

        $entityId = $studentId . '_' . $inscription->id_niveau_etude . '_' . $academicYearId;
        return $this->generatePdfFromHtml($htmlContent, 'AttestationScolarite', $entityId, 'DOC_ATTESTATION', $studentId);
    }

    /**
     * Génère un bulletin de notes au format PDF.
     *
     * @param string $studentId L'ID de l'étudiant.
     * @param string $academicYearId L'ID de l'année académique.
     * @return string L'ID du document généré.
     * @throws ElementNotFoundException Si l'étudiant ou l'année académique est introuvable.
     * @throws OperationFailedException Si le modèle du bulletin est introuvable ou la génération échoue.
     */
    public function generateGradeReport(string $studentId, string $academicYearId): string
    {
        $student = Etudiant::find($studentId);
        $academicYear = AnneeAcademique::find($academicYearId);
        if (!$student || !$academicYear) {
            throw new ElementNotFoundException("Étudiant ou année académique introuvable.");
        }

        $notes = Evaluer::where('numero_carte_etudiant', $studentId)
            ->where('id_annee_academique', $academicYearId)
            ->get();

        $templatePath = resource_path('views/templates/pdf/grade_report.blade.php');
        if (!file_exists($templatePath)) {
            throw new OperationFailedException("Le modèle du bulletin est introuvable.");
        }

        $htmlContent = view('templates.pdf.grade_report', [
            'student' => $student,
            'academicYear' => $academicYear,
            'notes' => $notes,
            'generationDate' => now()->format('d/m/Y')
        ])->render();

        $entityId = $studentId . '_' . $academicYearId;
        return $this->generatePdfWithVersioning($htmlContent, 'BulletinNotes', $entityId, 'DOC_BULLETIN', $studentId);
    }

    /**
     * Génère un procès-verbal de validation au format PDF.
     *
     * @param string $pvId L'ID du PV.
     * @return string L'ID du document généré.
     * @throws ElementNotFoundException Si le PV n'est pas trouvé.
     * @throws OperationFailedException Si le modèle du PV est introuvable ou la génération échoue.
     */
    public function generateDefenseMinutes(string $pvId): string
    {
        $pv = CompteRendu::find($pvId);
        if (!$pv) {
            throw new ElementNotFoundException("PV '{$pvId}' non trouvé.");
        }

        $templatePath = resource_path('views/templates/pdf/defense_minutes.blade.php');
        if (!file_exists($templatePath)) {
            throw new OperationFailedException("Le modèle du PV est introuvable.");
        }

        $htmlContent = view('templates.pdf.defense_minutes', [
            'pv' => $pv,
            'generationDate' => now()->format('d/m/Y')
        ])->render();

        return $this->generatePdfFromHtml($htmlContent, 'PV', $pvId, 'DOC_PV', $pv->id_redacteur);
    }

    /**
     * Génère un reçu de paiement au format PDF.
     *
     * @param string $inscriptionId L'ID de l'inscription.
     * @return string L'ID du document généré.
     * @throws ElementNotFoundException Si l'inscription n'est pas trouvée.
     * @throws OperationFailedException Si la génération échoue.
     */
    public function generatePaymentReceipt(string $inscriptionId): string
    {
        // Supposons que $inscriptionId est une clé primaire simple pour Inscrire, sinon ajuster
        $inscription = Inscrire::find($inscriptionId);
        if (!$inscription) {
            throw new ElementNotFoundException("Inscription non trouvée.");
        }

        $htmlContent = view('templates.pdf.payment_receipt', [ // Créez cette vue si elle n'existe pas
            'inscription' => $inscription,
            'generationDate' => now()->format('d/m/Y')
        ])->render();

        return $this->generatePdfFromHtml($htmlContent, 'RecuPaiement', $inscriptionId, 'DOC_RECU', $inscription->numero_carte_etudiant);
    }

    /**
     * Génère le rapport étudiant au format PDF.
     *
     * @param string $reportId L'ID du rapport.
     * @return string L'ID du document généré.
     * @throws ElementNotFoundException Si le rapport n'est pas trouvé.
     * @throws OperationFailedException Si la génération échoue.
     */
    public function generateStudentReportPdf(string $reportId): string
    {
        $report = RapportEtudiant::with('sectionRapports')->find($reportId);
        if (!$report) {
            throw new ElementNotFoundException("Rapport non trouvé.");
        }

        $htmlContent = view('templates.pdf.student_report', [ // Créez cette vue si elle n'existe pas
            'report' => $report,
            'generationDate' => now()->format('d/m/Y')
        ])->render();

        return $this->generatePdfFromHtml($htmlContent, 'Rapport', $reportId, 'DOC_RAPPORT', $report->numero_carte_etudiant);
    }

    /**
     * Génère une liste de données au format PDF.
     *
     * @param string $listName Le nom de la liste.
     * @param array $data Les données à inclure.
     * @param array $columns Les colonnes à afficher (clé_db => Libellé).
     * @return string L'ID du document généré.
     * @throws OperationFailedException Si aucune donnée n'est fournie ou la génération échoue.
     */
    public function generatePdfList(string $listName, array $data, array $columns): string
    {
        if (empty($data)) {
            throw new OperationFailedException("Aucune donnée à exporter.");
        }

        $htmlContent = view('templates.pdf.generic_list', [ // Créez cette vue si elle n'existe pas
            'listName' => $listName,
            'data' => $data,
            'columns' => $columns,
            'generationDate' => now()->format('d/m/Y')
        ])->render();

        $entityId = 'export_' . Str::slug($listName) . '_' . now()->timestamp;
        return $this->generatePdfFromHtml($htmlContent, 'ExportList', $entityId, 'DOC_EXPORT', Auth::id() ?? 'SYSTEM');
    }

    // ====================================================================
    // SECTION 2: GESTION DES MODÈLES DE DOCUMENTS (CRUD)
    // ====================================================================

    /**
     * Crée un nouveau modèle de document.
     *
     * @param string $name Le nom du modèle.
     * @param string $htmlContent Le contenu HTML du modèle.
     * @param string $type Le type de modèle (ex: 'pdf', 'word').
     * @return string L'ID du modèle créé.
     * @throws OperationFailedException Si la création échoue.
     */
    public function createDocumentModel(string $name, string $htmlContent, string $type = 'pdf'): string
    {
        $modelId = $this->idGenerator->generateUniqueId('TPL');
        return DB::transaction(function () use ($modelId, $name, $htmlContent, $type) {
            if (!RapportModele::create([
                'id_modele' => $modelId,
                'nom_modele' => $name,
                'description' => 'Modèle de document généré.',
                'version' => '1.0',
                'statut' => 'Brouillon',
                'type_modele' => $type, // Assurez-vous que cette colonne existe dans votre table rapport_modele
            ])) {
                throw new OperationFailedException("Échec de la création du modèle de document.");
            }
            // Si le contenu est dans RapportModeleSection, il faut le créer ici aussi
            if (!RapportModeleSection::create([
                'id_section_modele' => $this->idGenerator->generateUniqueId('RMS'),
                'id_modele' => $modelId,
                'titre_section' => 'Contenu Principal',
                'contenu_par_defaut' => $htmlContent,
                'ordre' => 1
            ])) {
                throw new OperationFailedException("Échec de la création de la section du modèle de document.");
            }

            $this->supervisionService->recordAction(Auth::id(), 'CREATE_DOC_TEMPLATE', $modelId, 'DocumentModel');
            return $modelId;
        });
    }

    /**
     * Lit un modèle de document.
     *
     * @param string $modelId L'ID du modèle.
     * @return RapportModele|null
     */
    public function readDocumentModel(string $modelId): ?RapportModele
    {
        return RapportModele::find($modelId);
    }

    /**
     * Met à jour un modèle de document.
     *
     * @param string $modelId L'ID du modèle.
     * @param string $name Le nouveau nom.
     * @param string $htmlContent Le nouveau contenu HTML.
     * @return bool
     * @throws ElementNotFoundException Si le modèle n'est pas trouvé.
     * @throws OperationFailedException Si la mise à jour échoue.
     */
    public function updateDocumentModel(string $modelId, string $name, string $htmlContent): bool
    {
        return DB::transaction(function () use ($modelId, $name, $htmlContent) {
            $model = RapportModele::find($modelId);
            if (!$model) {
                throw new ElementNotFoundException("Modèle non trouvé.");
            }

            // Incrémenter la version
            $newVersion = (float)$model->version + 0.1;

            $success = $model->update([
                'nom_modele' => $name,
                'version' => (string)round($newVersion, 1),
            ]);

            // Si le contenu est dans RapportModeleSection, mettez à jour la section principale
            $mainSection = RapportModeleSection::where('id_modele', $modelId)->where('ordre', 1)->first();
            if ($mainSection) {
                $mainSection->update(['contenu_par_defaut' => $htmlContent]);
            } else {
                // Créer si elle n'existe pas (cas rare)
                RapportModeleSection::create([
                    'id_section_modele' => $this->idGenerator->generateUniqueId('RMS'),
                    'id_modele' => $modelId,
                    'titre_section' => 'Contenu Principal',
                    'contenu_par_defaut' => $htmlContent,
                    'ordre' => 1
                ]);
            }

            if ($success) {
                $this->supervisionService->recordAction(Auth::id(), 'UPDATE_DOC_TEMPLATE', $modelId, 'DocumentModel');
            }
            return $success;
        });
    }

    /**
     * Supprime un modèle de document.
     *
     * @param string $modelId L'ID du modèle.
     * @return bool
     * @throws ElementNotFoundException Si le modèle n'est pas trouvé.
     * @throws OperationFailedException Si la suppression échoue (dépendances).
     */
    public function deleteDocumentModel(string $modelId): bool
    {
        // Vérifier les dépendances avant suppression (ex: rapports basés sur ce modèle)
        // Assurez-vous d'avoir une colonne id_modele dans rapport_etudiant si c'est le cas
        if (RapportEtudiant::where('id_modele', $modelId)->exists()) {
            throw new OperationFailedException("Suppression impossible : des rapports sont basés sur ce modèle.");
        }

        $success = RapportModele::destroy($modelId);
        if (!$success) {
            throw new ElementNotFoundException("Modèle non trouvé ou impossible à supprimer.");
        }
        $this->supervisionService->recordAction(Auth::id(), 'DELETE_DOC_TEMPLATE', $modelId, 'DocumentModel');
        return (bool) $success;
    }

    /**
     * Liste les modèles de document disponibles.
     *
     * @param string $type Le type de modèle à lister (ex: 'pdf').
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listDocumentModels(string $type = 'pdf'): \Illuminate\Database\Eloquent\Collection
    {
        // Si vous avez une colonne 'type_modele' dans RapportModele
        return RapportModele::where('type_modele', $type)->get();
    }

    /**
     * Importe un modèle de document à partir d'un fichier Word (.docx).
     *
     * @param array $fileData Les données du fichier uploadé.
     * @return string L'ID du modèle créé.
     * @throws ValidationException Si le fichier est invalide.
     * @throws OperationFailedException Si l'importation échoue.
     */
    public function importDocumentModelWord(array $fileData): string
    {
        if (!isset($fileData['error']) || $fileData['error'] !== UPLOAD_ERR_OK) {
            throw new ValidationException("Erreur lors de l'upload du fichier Word.");
        }

        $filePath = $fileData['tmp_name'];
        $fileExtension = pathinfo($fileData['name'], PATHINFO_EXTENSION);

        if (!in_array($fileExtension, ['docx'])) {
            throw new ValidationException("Seuls les fichiers .docx sont autorisés pour l'import de modèle.");
        }

        try {
            // Assurez-vous que PhpOffice\PhpWord est correctement installé via Composer
            $phpWord = WordIOFactory::load($filePath);
            $htmlWriter = new \PhpOffice\PhpWord\Writer\HTML($phpWord);
            $htmlContent = $htmlWriter->getContent();

            // Nettoyage basique du HTML généré (peut nécessiter un nettoyage plus avancé)
            $htmlContent = preg_replace('/<image[^>]+>/', '', $htmlContent); // Supprime les images
            $htmlContent = preg_replace('/style="[^"]*"/i', '', $htmlContent); // Supprime les styles inline

            $modelName = pathinfo($fileData['name'], PATHINFO_FILENAME);
            return $this->createDocumentModel($modelName, $htmlContent, 'pdf'); // Supposons que les modèles Word sont pour les PDF
        } catch (\Exception $e) {
            Log::error("Failed to import Word document model: " . $e->getMessage());
            throw new OperationFailedException("Échec de l'importation du modèle Word : " . $e->getMessage());
        }
    }

    // ====================================================================
    // SECTION 3: GESTION DES FICHIERS UPLOADÉS
    // ====================================================================

    /**
     * Télécharge un fichier de manière sécurisée vers un emplacement public.
     *
     * @param array $fileData Les données du fichier uploadé (depuis $_FILES).
     * @param string $destinationType Le sous-dossier de destination (ex: 'profile_pictures', 'rapport_images').
     * @param array $allowedMimeTypes Les types MIME autorisés.
     * @param int $maxSizeInBytes La taille maximale autorisée en octets.
     * @return string Le chemin relatif du fichier stocké (ex: 'profile_pictures/filename.jpg').
     * @throws ValidationException Si le fichier est invalide ou ne respecte pas les critères.
     * @throws OperationFailedException Si le déplacement du fichier échoue.
     */
    public function uploadSecureFile(array $fileData, string $destinationType, array $allowedMimeTypes, int $maxSizeInBytes): string
    {
        if (!isset($fileData['error']) || $fileData['error'] !== UPLOAD_ERR_OK) {
            throw new ValidationException("Erreur lors de l'upload du fichier.");
        }
        if ($fileData['size'] > $maxSizeInBytes) {
            throw new ValidationException("Le fichier est trop volumineux.");
        }

        $mimeType = mime_content_type($fileData['tmp_name']);
        if (!in_array($mimeType, $allowedMimeTypes)) {
            throw new ValidationException("Le type de fichier '{$mimeType}' n'est pas autorisé.");
        }

        // Utilisation du système de fichiers de Laravel (Storage Facade)
        $disk = Storage::disk('public'); // Le disque 'public' pointe vers storage/app/public
        $path = $disk->path($destinationType); // Chemin absolu vers le dossier de destination

        if (!$disk->exists($destinationType)) {
            $disk->makeDirectory($destinationType);
        }

        $fileName = Str::random(40) . '.' . pathinfo($fileData['name'], PATHINFO_EXTENSION); // Génère un nom de fichier unique
        $filePath = $disk->putFileAs($destinationType, $fileData['tmp_name'], $fileName); // Déplace le fichier

        if (!$filePath) {
            throw new OperationFailedException("Échec du déplacement du fichier uploadé.");
        }

        $this->supervisionService->recordAction(Auth::id() ?? 'SYSTEM', 'UPLOAD_FICHIER', null, 'File', ['path' => $filePath, 'type' => $destinationType]);
        return $filePath; // Retourne le chemin relatif au disque public
    }

    /**
     * Supprime un fichier stocké.
     *
     * @param string $relativePath Le chemin relatif du fichier (ex: 'profile_pictures/filename.jpg').
     * @return bool
     */
    public function deleteFile(string $relativePath): bool
    {
        $disk = Storage::disk('public');
        if ($disk->exists($relativePath)) {
            $success = $disk->delete($relativePath);
            if ($success) {
                $this->supervisionService->recordAction(Auth::id() ?? 'SYSTEM', 'DELETE_FICHIER', null, 'File', ['path' => $relativePath]);
            }
            return $success;
        }
        return false;
    }

    // ====================================================================
    // SECTION 4: VÉRIFICATION DES DROITS
    // ====================================================================

    /**
     * Vérifie si un utilisateur est le propriétaire d'un document généré.
     *
     * @param string $filename Le nom du fichier (pas le chemin complet).
     * @param string $userId L'ID de l'utilisateur à vérifier.
     * @return bool True si l'utilisateur est le propriétaire.
     */
    public function verifyDocumentOwnership(string $filename, string $userId): bool
    {
        // Le chemin stocké en base est relatif, ex: 'documents_generes/mon_fichier.pdf'
        // Nous cherchons donc un document dont le chemin_fichier se termine par le nom du fichier.
        $document = DocumentGenere::where('chemin_fichier', 'LIKE', '%' . $filename)
            ->where('numero_utilisateur_concerne', $userId)
            ->first();

        return (bool) $document;
    }
}
