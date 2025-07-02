<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\DefenseWorkflowService;
use App\Services\AcademicJourneyService;
use App\Services\CommunicationService;
use App\Services\DocumentService;
use App\Services\SecurityService;
use App\Services\SupervisionService;
use App\Services\SystemService;
use App\Utils\IdGenerator;
use App\Models\RapportEtudiant;
use App\Models\SectionRapport;
use App\Models\StatutRapportRef;
use App\Models\Approuver;
use App\Models\ConformiteRapportDetail;
use App\Models\CritereConformiteRef;
use App\Models\SessionValidation;
use App\Models\VoteCommission;
use App\Models\CompteRendu;
use App\Models\Affecter;
use App\Models\Etudiant;
use App\Models\AnneeAcademique;
use App\Models\StatutPaiementRef;
use App\Models\Entreprise;
use App\Models\FaireStage;
use App\Models\Utilisateur;
use App\Models\GroupeUtilisateur;
use App\Models\Notification;
use App\Exceptions\ElementNotFoundException;
use App\Exceptions\OperationFailedException;
use App\Exceptions\PermissionDeniedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mockery;

class DefenseWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $defenseWorkflowService;
    protected $idGeneratorMock;
    protected $communicationServiceMock;
    protected $documentServiceMock;
    protected $supervisionServiceMock;
    protected $systemServiceMock;
    protected $academicJourneyServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idGeneratorMock = Mockery::mock(IdGenerator::class);
        $this->communicationServiceMock = Mockery::mock(CommunicationService::class);
        $this->documentServiceMock = Mockery::mock(DocumentService::class);
        $this->supervisionServiceMock = Mockery::mock(SupervisionService::class);
        $this->systemServiceMock = Mockery::mock(SystemService::class);
        $this->academicJourneyServiceMock = Mockery::mock(AcademicJourneyService::class);

        $this->defenseWorkflowService = new DefenseWorkflowService(
            $this->idGeneratorMock,
            $this->communicationServiceMock,
            $this->documentServiceMock,
            $this->supervisionServiceMock,
            $this->systemServiceMock,
            $this->academicJourneyServiceMock
        );

        // Créer un utilisateur authentifié pour les tests qui enregistrent des actions
        $this->actingAs(Utilisateur::factory()->create(['numero_utilisateur' => 'USR-TEST-001']));

        // Créer les statuts de rapport de référence nécessaires pour les tests
        StatutRapportRef::factory()->create(['id_statut_rapport' => 'RAP_BROUILLON', 'etape_workflow' => 1]);
        StatutRapportRef::factory()->create(['id_statut_rapport' => 'RAP_SOUMIS', 'etape_workflow' => 2]);
        StatutRapportRef::factory()->create(['id_statut_rapport' => 'RAP_NON_CONF', 'etape_workflow' => 3]);
        StatutRapportRef::factory()->create(['id_statut_rapport' => 'RAP_CONF', 'etape_workflow' => 4]);
        StatutRapportRef::factory()->create(['id_statut_rapport' => 'RAP_EN_COMMISSION', 'etape_workflow' => 5]);
        StatutRapportRef::factory()->create(['id_statut_rapport' => 'RAP_CORRECT', 'etape_workflow' => 6]);
        StatutRapportRef::factory()->create(['id_statut_rapport' => 'RAP_VALID', 'etape_workflow' => 8]);
        StatutRapportRef::factory()->create(['id_statut_rapport' => 'RAP_REFUSE', 'etape_workflow' => 7]);
        StatutRapportRef::factory()->create(['id_statut_rapport' => 'RAP_ARCHIVE', 'etape_workflow' => 9]);

        // Créer les groupes d'utilisateurs nécessaires
        \App\Models\GroupeUtilisateur::factory()->create(['id_groupe_utilisateur' => 'GRP_AGENT_CONFORMITE']);
        \App\Models\GroupeUtilisateur::factory()->create(['id_groupe_utilisateur' => 'GRP_COMMISSION']);
        \App\Models\GroupeUtilisateur::factory()->create(['id_groupe_utilisateur' => 'GRP_RS']);
        \App\Models\GroupeUtilisateur::factory()->create(['id_groupe_utilisateur' => 'GRP_ETUDIANT']);

        // Créer des notifications de référence
        Notification::factory()->create(['id_notification' => 'NOUVEAU_RAPPORT_A_VERIFIER']);
        Notification::factory()->create(['id_notification' => 'RAPPORT_CONFORME_A_EVALUER']);
        Notification::factory()->create(['id_notification' => 'CORRECTIONS_REQUISES']);
        Notification::factory()->create(['id_notification' => 'RAPPORT_CORRIGE_ET_VALIDE']);
        Notification::factory()->create(['id_notification' => 'RAPPORT_VALIDE']);
        Notification::factory()->create(['id_notification' => 'RAPPORT_REFUSE']);
        Notification::factory()->create(['id_notification' => 'STATUT_RAPPORT_MAJ']);
        Notification::factory()->create(['id_notification' => 'STATUT_RAPPORT_FORCE']);
        Notification::factory()->create(['id_notification' => 'NOUVELLE_RECLAMATION']);
        Notification::factory()->create(['id_notification' => 'RECLAMATION_REPONDU']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // --- Tests pour createOrUpdateDraft ---

    public function test_create_or_update_draft_creates_new_report()
    {
        $studentId = 'ETU-2025-0001';
        $metadata = ['libelle_rapport_etudiant' => 'Mon Super Rapport', 'theme' => 'IA', 'resume' => 'Résumé'];
        $sections = ['Intro' => '<p>Contenu intro</p>', 'Conclu' => '<p>Contenu conclu</p>'];

        Etudiant::factory()->create(['numero_carte_etudiant' => $studentId]);

        $this->idGeneratorMock->shouldReceive('generateUniqueId')->once()->andReturn('RAP-2025-0001');

        $reportId = $this->defenseWorkflowService->createOrUpdateDraft($studentId, $metadata, $sections);

        $this->assertEquals('RAP-2025-0001', $reportId);
        $this->assertDatabaseHas('rapport_etudiant', [
            'id_rapport_etudiant' => 'RAP-2025-0001',
            'numero_carte_etudiant' => $studentId,
            'id_statut_rapport' => 'RAP_BROUILLON',
            'libelle_rapport_etudiant' => 'Mon Super Rapport',
        ]);
        $this->assertDatabaseHas('section_rapport', ['id_rapport_etudiant' => 'RAP-2025-0001', 'titre_section' => 'Intro']);
        $this->assertDatabaseHas('section_rapport', ['id_rapport_etudiant' => 'RAP-2025-0001', 'titre_section' => 'Conclu']);
    }

    public function test_create_or_update_draft_updates_existing_report()
    {
        $studentId = 'ETU-2025-0001';
        $report = RapportEtudiant::factory()->create(['numero_carte_etudiant' => $studentId, 'id_statut_rapport' => 'RAP_BROUILLON', 'id_rapport_etudiant' => 'RAP-2025-0001']);
        SectionRapport::factory()->create(['id_rapport_etudiant' => $report->id_rapport_etudiant, 'titre_section' => 'Old Section']);

        $metadata = ['libelle_rapport_etudiant' => 'Mon Rapport Mis à Jour', 'theme' => 'ML'];
        $sections = ['New Intro' => '<p>New content</p>'];

        $reportId = $this->defenseWorkflowService->createOrUpdateDraft($studentId, $metadata, $sections);

        $this->assertEquals($report->id_rapport_etudiant, $reportId);
        $this->assertDatabaseHas('rapport_etudiant', [
            'id_rapport_etudiant' => $reportId,
            'libelle_rapport_etudiant' => 'Mon Rapport Mis à Jour',
        ]);
        $this->assertDatabaseMissing('section_rapport', ['id_rapport_etudiant' => $reportId, 'titre_section' => 'Old Section']);
        $this->assertDatabaseHas('section_rapport', ['id_rapport_etudiant' => $reportId, 'titre_section' => 'New Intro']);
    }

    // --- Tests pour submitReport ---

    public function test_submit_report_successfully()
    {
        $studentId = 'ETU-2025-0001';
        $report = RapportEtudiant::factory()->create(['numero_carte_etudiant' => $studentId, 'id_statut_rapport' => 'RAP_BROUILLON', 'id_rapport_etudiant' => 'RAP-2025-0001']);
        Etudiant::factory()->create(['numero_carte_etudiant' => $studentId]);

        $this->academicJourneyServiceMock->shouldReceive('isStudentEligibleForSubmission')->once()->andReturn(true);
        $this->supervisionServiceMock->shouldReceive('recordAction')->once();
        $this->communicationServiceMock->shouldReceive('sendInternalNotification')->once();
        $this->communicationServiceMock->shouldReceive('sendGroupNotification')->once();

        $result = $this->defenseWorkflowService->submitReport($report->id_rapport_etudiant, $studentId);

        $this->assertTrue($result);
        $this->assertDatabaseHas('rapport_etudiant', ['id_rapport_etudiant' => $report->id_rapport_etudiant, 'id_statut_rapport' => 'RAP_SOUMIS']);
    }

    public function test_submit_report_throws_permission_denied_if_not_owner()
    {
        $this->expectException(PermissionDeniedException::class);
        $report = RapportEtudiant::factory()->create(['id_statut_rapport' => 'RAP_BROUILLON', 'id_rapport_etudiant' => 'RAP-2025-0001']);
        $this->defenseWorkflowService->submitReport($report->id_rapport_etudiant, 'ETU-NON-OWNER');
    }

    public function test_submit_report_throws_operation_failed_if_not_draft()
    {
        $this->expectException(OperationFailedException::class);
        $studentId = 'ETU-2025-0001';
        $report = RapportEtudiant::factory()->create(['numero_carte_etudiant' => $studentId, 'id_statut_rapport' => 'RAP_SOUMIS', 'id_rapport_etudiant' => 'RAP-2025-0001']);
        $this->defenseWorkflowService->submitReport($report->id_rapport_etudiant, $studentId);
    }

    public function test_submit_report_throws_operation_failed_if_not_eligible()
    {
        $this->expectException(OperationFailedException::class);
        $studentId = 'ETU-2025-0001';
        $report = RapportEtudiant::factory()->create(['numero_carte_etudiant' => $studentId, 'id_statut_rapport' => 'RAP_BROUILLON', 'id_rapport_etudiant' => 'RAP-2025-0001']);
        Etudiant::factory()->create(['numero_carte_etudiant' => $studentId]);

        $this->academicJourneyServiceMock->shouldReceive('isStudentEligibleForSubmission')->once()->andReturn(false);

        $this->defenseWorkflowService->submitReport($report->id_rapport_etudiant, $studentId);
    }

    // --- Tests pour submitCorrections ---

    public function test_submit_corrections_successfully()
    {
        $studentId = 'ETU-2025-0001';
        $report = RapportEtudiant::factory()->create(['numero_carte_etudiant' => $studentId, 'id_statut_rapport' => 'RAP_CORRECT', 'id_rapport_etudiant' => 'RAP-2025-0001']);
        Etudiant::factory()->create(['numero_carte_etudiant' => $studentId]);

        $sections = ['Intro Corrigée' => '<p>Nouveau contenu corrigé</p>'];
        $explanatoryNote = 'Corrections mineures appliquées.';

        $this->supervisionServiceMock->shouldReceive('recordAction')->once();
        $this->communicationServiceMock->shouldReceive('sendInternalNotification')->once();
        $this->communicationServiceMock->shouldReceive('sendGroupNotification')->once(); // Pour le changement de statut vers RAP_VALID

        $reportId = $report->id_rapport_etudiant;
        $result = $this->defenseWorkflowService->submitCorrections($reportId, $studentId, $sections, $explanatoryNote);

        $this->assertTrue($result);
        $this->assertDatabaseHas('rapport_etudiant', ['id_rapport_etudiant' => $reportId, 'id_statut_rapport' => 'RAP_VALID']);
        $this->assertDatabaseHas('section_rapport', ['id_rapport_etudiant' => $reportId, 'titre_section' => 'Intro Corrigée']);
    }

    // --- Tests pour processComplianceVerification ---

    public function test_process_compliance_verification_as_conform()
    {
        $reportId = 'RAP-2025-0001';
        $personnelId = 'ADM-2025-0001';
        $studentId = 'ETU-2025-0001';

        RapportEtudiant::factory()->create(['id_rapport_etudiant' => $reportId, 'numero_carte_etudiant' => $studentId, 'id_statut_rapport' => 'RAP_SOUMIS']);
        \App\Models\PersonnelAdministratif::factory()->create(['numero_personnel_administratif' => $personnelId]);
        CritereConformiteRef::factory()->create(['id_critere' => 'CRITERE-001']);
        \App\Models\StatutConformiteRef::factory()->create(['id_statut_conformite' => 'CONF_OK']);

        $checklistDetails = [['id' => 'CRITERE-001', 'statut' => 'Conforme', 'commentaire' => 'OK']];
        $generalComment = 'Rapport conforme aux exigences.';

        $this->supervisionServiceMock->shouldReceive('recordAction')->twice(); // Pour Approuver et changerStatutRapport
        $this->communicationServiceMock->shouldReceive('sendInternalNotification')->once();
        $this->communicationServiceMock->shouldReceive('sendGroupNotification')->once();

        $result = $this->defenseWorkflowService->processComplianceVerification($reportId, $personnelId, true, $checklistDetails, $generalComment);

        $this->assertTrue($result);
        $this->assertDatabaseHas('approuver', ['id_rapport_etudiant' => $reportId, 'id_statut_conformite' => 'CONF_OK']);
        $this->assertDatabaseHas('conformite_rapport_details', ['id_rapport_etudiant' => $reportId, 'id_critere' => 'CRITERE-001', 'statut_validation' => 'Conforme']);
        $this->assertDatabaseHas('rapport_etudiant', ['id_rapport_etudiant' => $reportId, 'id_statut_rapport' => 'RAP_CONF']);
    }

    // --- Tests pour createSession ---

    public function test_create_session_successfully()
    {
        $presidentId = 'ENS-2025-0001';
        \App\Models\Enseignant::factory()->create(['numero_enseignant' => $presidentId]);

        $sessionData = [
            'nom_session' => 'Session Test',
            'date_debut_session' => '2025-08-01',
            'date_fin_prevue' => '2025-08-05',
            'mode_session' => 'presentiel',
            'nombre_votants_requis' => 3,
        ];

        $this->idGeneratorMock->shouldReceive('generateUniqueId')->once()->andReturn('SESS-2025-0001');

        $sessionId = $this->defenseWorkflowService->createSession($presidentId, $sessionData);

        $this->assertEquals('SESS-2025-0001', $sessionId);
        $this->assertDatabaseHas('session_validation', array_merge($sessionData, ['id_session' => 'SESS-2025-0001', 'id_president_session' => $presidentId, 'statut_session' => 'planifiee']));
    }

    // --- Tests pour recordVote ---

    public function test_record_vote_successfully()
    {
        $report = RapportEtudiant::factory()->create(['id_rapport_etudiant' => 'RAP-2025-0001', 'id_statut_rapport' => 'RAP_EN_COMMISSION']);
        $session = SessionValidation::factory()->create(['id_session' => 'SESS-2025-0001', 'nombre_votants_requis' => 1]);
        $teacher = \App\Models\Enseignant::factory()->create(['numero_enseignant' => 'ENS-2025-0001']);
        \App\Models\DecisionVoteRef::factory()->create(['id_decision_vote' => 'VOTE_APPROUVE']);

        $this->idGeneratorMock->shouldReceive('generateUniqueId')->once()->andReturn('VOTE-2025-0001');
        $this->supervisionServiceMock->shouldReceive('recordAction')->once();

        $result = $this->defenseWorkflowService->recordVote($report->id_rapport_etudiant, $session->id_session, $teacher->numero_enseignant, 'VOTE_APPROUVE', 'Bon travail');

        $this->assertTrue($result);
        $this->assertDatabaseHas('vote_commission', [
            'id_vote' => 'VOTE-2025-0001',
            'id_rapport_etudiant' => $report->id_rapport_etudiant,
            'id_decision_vote' => 'VOTE_APPROUVE',
        ]);
        $this->assertDatabaseHas('rapport_etudiant', ['id_rapport_etudiant' => $report->id_rapport_etudiant, 'id_statut_rapport' => 'RAP_VALID']); // Vérifie la finalisation du vote
    }

    // --- Tests pour initiatePvDraft ---

    public function test_initiate_pv_draft_successfully()
    {
        $sessionId = 'SESS-2025-0001';
        $redactorId = 'ENS-2025-0001';
        SessionValidation::factory()->create(['id_session' => $sessionId]);
        \App\Models\Enseignant::factory()->create(['numero_enseignant' => $redactorId]);
        \App\Models\StatutPvRef::factory()->create(['id_statut_pv' => 'PV_BROUILLON']);

        $this->idGeneratorMock->shouldReceive('generateUniqueId')->once()->andReturn('PV-2025-0001');

        $pvId = $this->defenseWorkflowService->initiatePvDraft($sessionId, $redactorId);

        $this->assertEquals('PV-2025-0001', $pvId);
        $this->assertDatabaseHas('compte_rendu', [
            'id_compte_rendu' => 'PV-2025-0001',
            'id_redacteur' => $redactorId,
            'id_statut_pv' => 'PV_BROUILLON',
        ]);
    }

    // --- Tests pour createComplaint ---

    public function test_create_complaint_successfully()
    {
        $studentId = 'ETU-2025-0001';
        Etudiant::factory()->create(['numero_carte_etudiant' => $studentId]);
        \App\Models\StatutReclamationRef::factory()->create(['id_statut_reclamation' => 'RECLA_OUVERTE']);

        $this->idGeneratorMock->shouldReceive('generateUniqueId')->once()->andReturn('RECLA-2025-0001');
        $this->communicationServiceMock->shouldReceive('sendGroupNotification')->once();

        $complaintId = $this->defenseWorkflowService->createComplaint($studentId, 'Problème Inscription', 'Statut non mis à jour', 'Mon paiement est passé mais le statut est toujours en attente.');

        $this->assertEquals('RECLA-2025-0001', $complaintId);
        $this->assertDatabaseHas('reclamation', [
            'id_reclamation' => 'RECLA-2025-0001',
            'numero_carte_etudiant' => $studentId,
            'id_statut_reclamation' => 'RECLA_OUVERTE',
        ]);
    }
}
