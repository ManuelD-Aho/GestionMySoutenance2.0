<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\DocumentService;
use App\Services\SupervisionService;
use App\Services\SystemService;
use App\Utils\IdGenerator;
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
use App\Exceptions\ElementNotFoundException;
use App\Exceptions\OperationFailedException;
use App\Exceptions\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;

// Mock de la classe TCPDF pour éviter les erreurs de fichier/chemin lors des tests unitaires
// car TCPDF tente d'écrire des fichiers et peut ne pas trouver les chemins dans un environnement de test unitaire.
// Nous nous assurons que la méthode Output est appelée.
class MockTCPDF extends \TCPDF {
    public function Output($file, $dest = '') {
        // Simule l'écriture du fichier sans réellement le faire
        // Pour les tests, nous vérifions juste que cette méthode est appelée.
        return true;
    }
}

class DocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $documentService;
    protected $idGeneratorMock;
    protected $supervisionServiceMock;
    protected $systemServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idGeneratorMock = Mockery::mock(IdGenerator::class);
        $this->supervisionServiceMock = Mockery::mock(SupervisionService::class);
        $this->systemServiceMock = Mockery::mock(SystemService::class);

        $this->documentService = new DocumentService(
            $this->idGeneratorMock,
            $this->supervisionServiceMock,
            $this->systemServiceMock
        );

        // Créer un utilisateur authentifié pour les tests qui enregistrent des actions
        $this->actingAs(\App\Models\Utilisateur::factory()->create(['numero_utilisateur' => 'USR-TEST-001']));

        // Mock de la façade Storage
        Storage::fake('public');

        // Mock de la classe TCPDF
        // Nous devons mocker la classe elle-même si elle est instanciée directement dans le service
        // Pour cela, nous pouvons utiliser un alias de classe ou un mock global si possible.
        // Pour les tests unitaires, il est souvent préférable de mocker l'appel de la méthode qui utilise TCPDF
        // ou d'injecter une "factory" de PDF dans le service.
        // Pour cet exemple, nous allons simuler le comportement de Output() pour qu'il ne plante pas.
        // Note: La meilleure pratique serait d'injecter une abstraction pour la génération PDF.
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // --- Tests pour generateSchoolingCertificate ---

    public function test_generate_schooling_certificate_successfully()
    {
        $student = Etudiant::factory()->create(['numero_carte_etudiant' => 'ETU-001']);
        $academicYear = AnneeAcademique::factory()->create(['id_annee_academique' => 'ANNEE-2025-2026']);
        $inscription = Inscrire::factory()->create([
            'numero_carte_etudiant' => $student->numero_carte_etudiant,
            'id_annee_academique' => $academicYear->id_annee_academique,
            'id_niveau_etude' => 'M2', // Assurez-vous que NiveauEtude existe
            'id_statut_paiement' => 'PAIE_OK', // Assurez-vous que StatutPaiementRef existe
        ]);
        TypeDocumentRef::factory()->create(['id_type_document' => 'DOC_ATTESTATION']);

        $this->idGeneratorMock->shouldReceive('generateUniqueId')->once()->andReturn('DOC-ATTEST-001');
        $this->supervisionServiceMock->shouldReceive('recordAction')->once();

        // Mock de la vue Blade pour éviter les erreurs de rendu
        \View::shouldReceive('make')->once()->andReturnSelf();
        \View::shouldReceive('render')->once()->andReturn('<html>Attestation</html>');

        // Mock de la classe TCPDF
        $tcpdfMock = Mockery::mock('overload:TCPDF');
        $tcpdfMock->shouldReceive('__construct')->once();
        $tcpdfMock->shouldReceive('SetCreator')->once();
        $tcpdfMock->shouldReceive('SetAuthor')->once();
        $tcpdfMock->shouldReceive('SetTitle')->once();
        $tcpdfMock->shouldReceive('AddPage')->once();
        $tcpdfMock->shouldReceive('writeHTML')->once();
        $tcpdfMock->shouldReceive('Output')->once()->andReturn(true); // Simule l'écriture du fichier

        $this->systemServiceMock->shouldReceive('getParametre')->andReturn(storage_path('app/public/')); // Mock du chemin d'upload

        $documentId = $this->documentService->generateSchoolingCertificate($student->numero_carte_etudiant, $academicYear->id_annee_academique);

        $this->assertEquals('DOC-ATTEST-001', $documentId);
        $this->assertDatabaseHas('document_genere', [
            'id_document_genere' => 'DOC-ATTEST-001',
            'id_type_document' => 'DOC_ATTESTATION',
            'numero_utilisateur_concerne' => $student->numero_carte_etudiant,
        ]);
    }

    // --- Tests pour createDocumentModel ---

    public function test_create_document_model_successfully()
    {
        $this->idGeneratorMock->shouldReceive('generateUniqueId')->twice()->andReturn('TPL-001', 'RMS-001');
        $this->supervisionServiceMock->shouldReceive('recordAction')->once();

        $modelId = $this->documentService->createDocumentModel('New Template', '<p>Default content</p>', 'pdf');

        $this->assertEquals('TPL-001', $modelId);
        $this->assertDatabaseHas('rapport_modele', ['id_modele' => 'TPL-001', 'nom_modele' => 'New Template']);
        $this->assertDatabaseHas('rapport_modele_section', ['id_modele' => 'TPL-001', 'titre_section' => 'Contenu Principal', 'contenu_par_defaut' => '<p>Default content</p>']);
    }

    // --- Tests pour uploadSecureFile ---

    public function test_upload_secure_file_successfully()
    {
        $file = \Illuminate\Http\UploadedFile::fake()->image('avatar.jpg', 100, 100)->size(500);
        $fileData = [
            'tmp_name' => $file->getPathname(),
            'name' => $file->getClientOriginalName(),
            'type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'error' => UPLOAD_ERR_OK,
            'extension' => $file->getClientOriginalExtension(),
        ];

        $this->supervisionServiceMock->shouldReceive('recordAction')->once();
        $this->systemServiceMock->shouldReceive('getParametre')->andReturn(storage_path('app/public/')); // Mock du chemin de base

        $relativePath = $this->documentService->uploadSecureFile($fileData, 'profile_pictures', ['image/jpeg'], 2 * 1024 * 1024);

        Storage::disk('public')->assertExists($relativePath);
        $this->assertStringStartsWith('profile_pictures/', $relativePath);
    }

    public function test_upload_secure_file_throws_validation_exception_for_invalid_mime_type()
    {
        $this->expectException(ValidationException::class);
        $file = \Illuminate\Http\UploadedFile::fake()->create('document.txt', 100, 'text/plain');
        $fileData = [
            'tmp_name' => $file->getPathname(),
            'name' => $file->getClientOriginalName(),
            'type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'error' => UPLOAD_ERR_OK,
            'extension' => $file->getClientOriginalExtension(),
        ];

        $this->documentService->uploadSecureFile($fileData, 'documents', ['application/pdf'], 2 * 1024 * 1024);
    }

    // --- Tests pour verifyDocumentOwnership ---

    public function test_verify_document_ownership_returns_true_for_owner()
    {
        $user = \App\Models\Utilisateur::factory()->create(['numero_utilisateur' => 'USR-001']);
        DocumentGenere::factory()->create([
            'id_document_genere' => 'DOC-001',
            'chemin_fichier' => 'documents_genere/report_abc.pdf',
            'numero_utilisateur_concerne' => $user->numero_utilisateur,
        ]);

        $isOwner = $this->documentService->verifyDocumentOwnership('report_abc.pdf', $user->numero_utilisateur);
        $this->assertTrue($isOwner);
    }

    public function test_verify_document_ownership_returns_false_for_non_owner()
    {
        $user = \App\Models\Utilisateur::factory()->create(['numero_utilisateur' => 'USR-001']);
        DocumentGenere::factory()->create([
            'id_document_genere' => 'DOC-001',
            'chemin_fichier' => 'documents_genere/report_abc.pdf',
            'numero_utilisateur_concerne' => 'ANOTHER-USER',
        ]);

        $isOwner = $this->documentService->verifyDocumentOwnership('report_abc.pdf', $user->numero_utilisateur);
        $this->assertFalse($isOwner);
    }
}
