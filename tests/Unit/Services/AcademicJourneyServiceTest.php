<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AcademicJourneyService;
use App\Services\SupervisionService;
use App\Services\SystemService;
use App\Utils\IdGenerator;
use App\Models\Inscrire;
use App\Models\Evaluer;
use App\Models\FaireStage;
use App\Models\Penalite;
use App\Models\AnneeAcademique;
use App\Exceptions\ElementNotFoundException;
use App\Exceptions\OperationFailedException;
use App\Exceptions\DuplicateEntryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class AcademicJourneyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $academicJourneyService;
    protected $idGeneratorMock;
    protected $supervisionServiceMock;
    protected $systemServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idGeneratorMock = Mockery::mock(IdGenerator::class);
        $this->supervisionServiceMock = Mockery::mock(SupervisionService::class);
        $this->systemServiceMock = Mockery::mock(SystemService::class);

        $this->academicJourneyService = new AcademicJourneyService(
            $this->idGeneratorMock,
            $this->supervisionServiceMock,
            $this->systemServiceMock
        );

        // Mock de l'utilisateur authentifié pour les tests qui enregistrent des actions
        $this->actingAs(\App\Models\Utilisateur::factory()->create(['numero_utilisateur' => 'USR-TEST-001']));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // --- Tests pour Inscriptions ---

    public function test_create_inscription_successfully()
    {
        $data = [
            'numero_carte_etudiant' => 'ETU-2025-0001',
            'id_niveau_etude' => 'M2',
            'id_annee_academique' => 'ANNEE-2025-2026',
            'montant_inscription' => 1500.00,
            'id_statut_paiement' => 'PAIE_ATTENTE',
        ];

        // Créer les dépendances nécessaires dans la DB
        \App\Models\Etudiant::factory()->create(['numero_carte_etudiant' => 'ETU-2025-0001']);
        \App\Models\NiveauEtude::factory()->create(['id_niveau_etude' => 'M2']);
        \App\Models\AnneeAcademique::factory()->create(['id_annee_academique' => 'ANNEE-2025-2026']);
        \App\Models\StatutPaiementRef::factory()->create(['id_statut_paiement' => 'PAIE_ATTENTE']);

        $result = $this->academicJourneyService->createInscription($data);

        $this->assertTrue($result);
        $this->assertDatabaseHas('inscrire', [
            'numero_carte_etudiant' => 'ETU-2025-0001',
            'id_niveau_etude' => 'M2',
            'id_annee_academique' => 'ANNEE-2025-2026',
        ]);
    }

    public function test_create_inscription_throws_duplicate_entry_exception()
    {
        $this->expectException(DuplicateEntryException::class);

        $data = [
            'numero_carte_etudiant' => 'ETU-2025-0001',
            'id_niveau_etude' => 'M2',
            'id_annee_academique' => 'ANNEE-2025-2026',
            'montant_inscription' => 1500.00,
            'id_statut_paiement' => 'PAIE_ATTENTE',
        ];

        // Créer les dépendances nécessaires dans la DB
        \App\Models\Etudiant::factory()->create(['numero_carte_etudiant' => 'ETU-2025-0001']);
        \App\Models\NiveauEtude::factory()->create(['id_niveau_etude' => 'M2']);
        \App\Models\AnneeAcademique::factory()->create(['id_annee_academique' => 'ANNEE-2025-2026']);
        \App\Models\StatutPaiementRef::factory()->create(['id_statut_paiement' => 'PAIE_ATTENTE']);

        $this->academicJourneyService->createInscription($data); // Première création
        $this->academicJourneyService->createInscription($data); // Deuxième création (doit échouer)
    }

    public function test_read_inscription_successfully()
    {
        \App\Models\Etudiant::factory()->create(['numero_carte_etudiant' => 'ETU-2025-0001']);
        \App\Models\NiveauEtude::factory()->create(['id_niveau_etude' => 'M2']);
        \App\Models\AnneeAcademique::factory()->create(['id_annee_academique' => 'ANNEE-2025-2026']);
        \App\Models\StatutPaiementRef::factory()->create(['id_statut_paiement' => 'PAIE_OK']);

        Inscrire::create([
            'numero_carte_etudiant' => 'ETU-2025-0001',
            'id_niveau_etude' => 'M2',
            'id_annee_academique' => 'ANNEE-2025-2026',
            'montant_inscription' => 1500.00,
            'date_inscription' => now(),
            'id_statut_paiement' => 'PAIE_OK',
        ]);

        $inscription = $this->academicJourneyService->readInscription('ETU-2025-0001', 'M2', 'ANNEE-2025-2026');
        $this->assertNotNull($inscription);
        $this->assertEquals('ETU-2025-0001', $inscription->numero_carte_etudiant);
    }

    public function test_update_inscription_successfully()
    {
        \App\Models\Etudiant::factory()->create(['numero_carte_etudiant' => 'ETU-2025-0001']);
        \App\Models\NiveauEtude::factory()->create(['id_niveau_etude' => 'M2']);
        \App\Models\AnneeAcademique::factory()->create(['id_annee_academique' => 'ANNEE-2025-2026']);
        \App\Models\StatutPaiementRef::factory()->create(['id_statut_paiement' => 'PAIE_ATTENTE']);
        \App\Models\StatutPaiementRef::factory()->create(['id_statut_paiement' => 'PAIE_OK']);

        Inscrire::create([
            'numero_carte_etudiant' => 'ETU-2025-0001',
            'id_niveau_etude' => 'M2',
            'id_annee_academique' => 'ANNEE-2025-2026',
            'montant_inscription' => 1500.00,
            'date_inscription' => now(),
            'id_statut_paiement' => 'PAIE_ATTENTE',
        ]);

        $updated = $this->academicJourneyService->updateInscription('ETU-2025-0001', 'M2', 'ANNEE-2025-2026', ['id_statut_paiement' => 'PAIE_OK']);
        $this->assertTrue($updated);
        $this->assertDatabaseHas('inscrire', [
            'numero_carte_etudiant' => 'ETU-2025-0001',
            'id_statut_paiement' => 'PAIE_OK',
        ]);
    }

    public function test_update_inscription_throws_element_not_found_exception()
    {
        $this->expectException(ElementNotFoundException::class);
        $this->academicJourneyService->updateInscription('NON_EXISTENT', 'M2', 'ANNEE-2025-2026', ['montant_inscription' => 2000.00]);
    }

    public function test_delete_inscription_successfully()
    {
        \App\Models\Etudiant::factory()->create(['numero_carte_etudiant' => 'ETU-2025-0001']);
        \App\Models\NiveauEtude::factory()->create(['id_niveau_etude' => 'M2']);
        \App\Models\AnneeAcademique::factory()->create(['id_annee_academique' => 'ANNEE-2025-2026']);
        \App\Models\StatutPaiementRef::factory()->create(['id_statut_paiement' => 'PAIE_OK']);

        Inscrire::create([
            'numero_carte_etudiant' => 'ETU-2025-0001',
            'id_niveau_etude' => 'M2',
            'id_annee_academique' => 'ANNEE-2025-2026',
            'montant_inscription' => 1500.00,
            'date_inscription' => now(),
            'id_statut_paiement' => 'PAIE_OK',
        ]);

        $deleted = $this->academicJourneyService->deleteInscription('ETU-2025-0001', 'M2', 'ANNEE-2025-2026');
        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('inscrire', [
            'numero_carte_etudiant' => 'ETU-2025-0001',
            'id_niveau_etude' => 'M2',
            'id_annee_academique' => 'ANNEE-2025-2026',
        ]);
    }

    // --- Tests pour Notes ---

    public function test_create_or_update_note_creates_new_note()
    {
        $data = [
            'numero_carte_etudiant' => 'ETU-2025-0001',
            'id_ecue' => 'ECUE_ALGO_AVANCE',
            'id_annee_academique' => 'ANNEE-2025-2026',
            'note' => 15.50,
        ];

        // Créer les dépendances nécessaires
        \App\Models\Etudiant::factory()->create(['numero_carte_etudiant' => 'ETU-2025-0001']);
        \App\Models\Ecue::factory()->create(['id_ecue' => 'ECUE_ALGO_AVANCE', 'id_ue' => 'UE_ALGO']);
        \App\Models\Ue::factory()->create(['id_ue' => 'UE_ALGO']);
        \App\Models\AnneeAcademique::factory()->create(['id_annee_academique' => 'ANNEE-2025-2026']);

        $result = $this->academicJourneyService->createOrUpdateNote($data);
        $this->assertTrue($result);
        $this->assertDatabaseHas('evaluer', $data);
    }

    public function test_create_or_update_note_updates_existing_note()
    {
        $initialData = [
            'numero_carte_etudiant' => 'ETU-2025-0001',
            'id_ecue' => 'ECUE_ALGO_AVANCE',
            'id_annee_academique' => 'ANNEE-2025-2026',
            'note' => 10.00,
            'date_evaluation' => now(),
        ];
        \App\Models\Etudiant::factory()->create(['numero_carte_etudiant' => 'ETU-2025-0001']);
        \App\Models\Ecue::factory()->create(['id_ecue' => 'ECUE_ALGO_AVANCE', 'id_ue' => 'UE_ALGO']);
        \App\Models\Ue::factory()->create(['id_ue' => 'UE_ALGO']);
        \App\Models\AnneeAcademique::factory()->create(['id_annee_academique' => 'ANNEE-2025-2026']);
        Evaluer::create($initialData);

        $updatedData = [
            'numero_carte_etudiant' => 'ETU-2025-0001',
            'id_ecue' => 'ECUE_ALGO_AVANCE',
            'id_annee_academique' => 'ANNEE-2025-2026',
            'note' => 18.00,
        ];
        $result = $this->academicJourneyService->createOrUpdateNote($updatedData);
        $this->assertTrue($result);
        $this->assertDatabaseHas('evaluer', $updatedData);
    }

    // --- Tests pour Stages ---

    public function test_create_stage_successfully()
    {
        $data = [
            'id_entreprise' => 'ENT-001',
            'numero_carte_etudiant' => 'ETU-2025-0001',
            'date_debut_stage' => '2025-01-01',
            'sujet_stage' => 'Développement d\'une application web',
        ];

        \App\Models\Entreprise::factory()->create(['id_entreprise' => 'ENT-001']);
        \App\Models\Etudiant::factory()->create(['numero_carte_etudiant' => 'ETU-2025-0001']);

        $result = $this->academicJourneyService->createStage($data);
        $this->assertTrue($result);
        $this->assertDatabaseHas('faire_stage', $data);
    }

    public function test_validate_stage_records_action()
    {
        $this->supervisionServiceMock->shouldReceive('recordAction')
            ->once()
            ->with(Mockery::any(), 'VALIDATION_STAGE', 'ETU-2025-0001', 'Etudiant', ['company_id' => 'ENT-001']);

        $result = $this->academicJourneyService->validateStage('ETU-2025-0001', 'ENT-001');
        $this->assertTrue($result);
    }

    // --- Tests pour Pénalités ---

    public function test_create_penalite_successfully()
    {
        $data = [
            'numero_carte_etudiant' => 'ETU-2025-0001',
            'id_annee_academique' => 'ANNEE-2025-2026',
            'type_penalite' => 'Financière',
            'montant_du' => 50.00,
            'motif' => 'Retard de soumission',
        ];

        \App\Models\Etudiant::factory()->create(['numero_carte_etudiant' => 'ETU-2025-0001']);
        \App\Models\AnneeAcademique::factory()->create(['id_annee_academique' => 'ANNEE-2025-2026']);
        \App\Models\StatutPenaliteRef::factory()->create(['id_statut_penalite' => 'PEN_DUE']);

        $this->idGeneratorMock->shouldReceive('generateUniqueId')->once()->andReturn('PEN-2025-0001');

        $penaltyId = $this->academicJourneyService->createPenalite($data);
        $this->assertEquals('PEN-2025-0001', $penaltyId);
        $this->assertDatabaseHas('penalite', array_merge($data, ['id_penalite' => 'PEN-2025-0001', 'id_statut_penalite' => 'PEN_DUE']));
    }

    public function test_regularize_penalite_successfully()
    {
        $penalty = Penalite::create([
            'id_penalite' => 'PEN-2025-0001',
            'numero_carte_etudiant' => 'ETU-2025-0001',
            'id_annee_academique' => 'ANNEE-2025-2026',
            'type_penalite' => 'Financière',
            'montant_du' => 50.00,
            'motif' => 'Retard de soumission',
            'id_statut_penalite' => 'PEN_DUE',
            'date_creation' => now(),
        ]);
        \App\Models\Etudiant::factory()->create(['numero_carte_etudiant' => 'ETU-2025-0001']);
        \App\Models\AnneeAcademique::factory()->create(['id_annee_academique' => 'ANNEE-2025-2026']);
        \App\Models\StatutPenaliteRef::factory()->create(['id_statut_penalite' => 'PEN_DUE']);
        \App\Models\StatutPenaliteRef::factory()->create(['id_statut_penalite' => 'PEN_REGLEE']);
        \App\Models\PersonnelAdministratif::factory()->create(['numero_personnel_administratif' => 'ADM-TEST-001']);

        $result = $this->academicJourneyService->regularizePenalite('PEN-2025-0001', 'ADM-TEST-001');
        $this->assertTrue($result);
        $this->assertDatabaseHas('penalite', [
            'id_penalite' => 'PEN-2025-0001',
            'id_statut_penalite' => 'PEN_REGLEE',
            'numero_personnel_traitant' => 'ADM-TEST-001',
        ]);
    }

    // --- Tests pour Logique Métier ---

    public function test_is_student_eligible_for_submission_returns_true_when_eligible()
    {
        // Setup pour l'éligibilité
        $anneeActive = AnneeAcademique::factory()->create(['id_annee_academique' => 'ANNEE-2025-2026', 'est_active' => true]);
        $etudiant = \App\Models\Etudiant::factory()->create(['numero_carte_etudiant' => 'ETU-2025-0001']);
        \App\Models\NiveauEtude::factory()->create(['id_niveau_etude' => 'M2']);
        \App\Models\StatutPaiementRef::factory()->create(['id_statut_paiement' => 'PAIE_OK']);
        \App\Models\StatutPenaliteRef::factory()->create(['id_statut_penalite' => 'PEN_DUE']); // Assurez-vous que PEN_DUE existe
        \App\Models\StatutPenaliteRef::factory()->create(['id_statut_penalite' => 'PEN_REGLEE']); // Assurez-vous que PEN_REGLEE existe

        Inscrire::create([
            'numero_carte_etudiant' => $etudiant->numero_carte_etudiant,
            'id_niveau_etude' => 'M2',
            'id_annee_academique' => $anneeActive->id_annee_academique,
            'montant_inscription' => 1500.00,
            'date_inscription' => now(),
            'id_statut_paiement' => 'PAIE_OK',
        ]);
        \App\Models\Entreprise::factory()->create(['id_entreprise' => 'ENT-001']);
        FaireStage::create([
            'id_entreprise' => 'ENT-001',
            'numero_carte_etudiant' => $etudiant->numero_carte_etudiant,
            'date_debut_stage' => '2025-01-01',
            'sujet_stage' => 'Sujet de stage',
        ]);

        $this->systemServiceMock->shouldReceive('getActiveAcademicYear')->andReturn($anneeActive);

        $this->assertTrue($this->academicJourneyService->isStudentEligibleForSubmission($etudiant->numero_carte_etudiant));
    }

    public function test_is_student_eligible_for_submission_returns_false_when_not_eligible()
    {
        $anneeActive = AnneeAcademique::factory()->create(['id_annee_academique' => 'ANNEE-2025-2026', 'est_active' => true]);
        $etudiant = \App\Models\Etudiant::factory()->create(['numero_carte_etudiant' => 'ETU-2025-0001']);
        \App\Models\NiveauEtude::factory()->create(['id_niveau_etude' => 'M2']);
        \App\Models\StatutPaiementRef::factory()->create(['id_statut_paiement' => 'PAIE_ATTENTE']);
        \App\Models\StatutPenaliteRef::factory()->create(['id_statut_penalite' => 'PEN_DUE']);

        Inscrire::create([
            'numero_carte_etudiant' => $etudiant->numero_carte_etudiant,
            'id_niveau_etude' => 'M2',
            'id_annee_academique' => $anneeActive->id_annee_academique,
            'montant_inscription' => 1500.00,
            'date_inscription' => now(),
            'id_statut_paiement' => 'PAIE_ATTENTE', // Non éligible à cause du paiement
        ]);
        \App\Models\Entreprise::factory()->create(['id_entreprise' => 'ENT-001']);
        FaireStage::create([
            'id_entreprise' => 'ENT-001',
            'numero_carte_etudiant' => $etudiant->numero_carte_etudiant,
            'date_debut_stage' => '2025-01-01',
            'sujet_stage' => 'Sujet de stage',
        ]);
        Penalite::create([
            'id_penalite' => 'PEN-2025-0001',
            'numero_carte_etudiant' => $etudiant->numero_carte_etudiant,
            'id_annee_academique' => $anneeActive->id_annee_academique,
            'type_penalite' => 'Financière',
            'montant_du' => 50.00,
            'motif' => 'Retard',
            'id_statut_penalite' => 'PEN_DUE', // Non éligible à cause de la pénalité
            'date_creation' => now(),
        ]);

        $this->systemServiceMock->shouldReceive('getActiveAcademicYear')->andReturn($anneeActive);

        $this->assertFalse($this->academicJourneyService->isStudentEligibleForSubmission($etudiant->numero_carte_etudiant));
    }

    public function test_record_passage_decision_successfully()
    {
        $anneeActive = AnneeAcademique::factory()->create(['id_annee_academique' => 'ANNEE-2025-2026', 'est_active' => true]);
        $etudiant = \App\Models\Etudiant::factory()->create(['numero_carte_etudiant' => 'ETU-2025-0001']);
        \App\Models\NiveauEtude::factory()->create(['id_niveau_etude' => 'M2']);
        \App\Models\StatutPaiementRef::factory()->create(['id_statut_paiement' => 'PAIE_OK']);
        \App\Models\DecisionPassageRef::factory()->create(['id_decision_passage' => 'DEC_ADMIS']);

        Inscrire::create([
            'numero_carte_etudiant' => $etudiant->numero_carte_etudiant,
            'id_niveau_etude' => 'M2',
            'id_annee_academique' => $anneeActive->id_annee_academique,
            'montant_inscription' => 1500.00,
            'date_inscription' => now(),
            'id_statut_paiement' => 'PAIE_OK',
        ]);

        $this->supervisionServiceMock->shouldReceive('recordAction')->once();

        $result = $this->academicJourneyService->recordPassageDecision($etudiant->numero_carte_etudiant, $anneeActive->id_annee_academique, 'DEC_ADMIS');
        $this->assertTrue($result);
        $this->assertDatabaseHas('inscrire', [
            'numero_carte_etudiant' => $etudiant->numero_carte_etudiant,
            'id_decision_passage' => 'DEC_ADMIS',
        ]);
    }

    public function test_record_passage_decision_for_redoublant_creates_new_inscription()
    {
        $anneeActive = AnneeAcademique::factory()->create(['id_annee_academique' => 'ANNEE-2025-2026', 'est_active' => true, 'libelle_annee_academique' => '2025-2026']);
        $etudiant = \App\Models\Etudiant::factory()->create(['numero_carte_etudiant' => 'ETU-2025-0001']);
        \App\Models\NiveauEtude::factory()->create(['id_niveau_etude' => 'M2']);
        \App\Models\StatutPaiementRef::factory()->create(['id_statut_paiement' => 'PAIE_OK']);
        \App\Models\DecisionPassageRef::factory()->create(['id_decision_passage' => 'DEC_REDOUBLANT']);

        Inscrire::create([
            'numero_carte_etudiant' => $etudiant->numero_carte_etudiant,
            'id_niveau_etude' => 'M2',
            'id_annee_academique' => $anneeActive->id_annee_academique,
            'montant_inscription' => 1500.00,
            'date_inscription' => now(),
            'id_statut_paiement' => 'PAIE_OK',
        ]);

        $this->supervisionServiceMock->shouldReceive('recordAction')->once();
        $this->systemServiceMock->shouldReceive('createAcademicYear')->once(); // Mock la création de la nouvelle année

        $result = $this->academicJourneyService->recordPassageDecision($etudiant->numero_carte_etudiant, $anneeActive->id_annee_academique, 'DEC_REDOUBLANT');
        $this->assertTrue($result);
        $this->assertDatabaseHas('inscrire', [
            'numero_carte_etudiant' => $etudiant->numero_carte_etudiant,
            'id_annee_academique' => 'ANNEE-2026-2027', // Vérifie la nouvelle inscription
            'id_statut_paiement' => 'PAIE_ATTENTE',
        ]);
    }

    public function test_calculate_grades_successfully()
    {
        $etudiant = \App\Models\Etudiant::factory()->create(['numero_carte_etudiant' => 'ETU-2025-0001']);
        $annee = AnneeAcademique::factory()->create(['id_annee_academique' => 'ANNEE-2025-2026']);
        $ue1 = \App\Models\Ue::factory()->create(['id_ue' => 'UE_ALGO', 'credits_ue' => 6]);
        $ecue1_1 = \App\Models\Ecue::factory()->create(['id_ecue' => 'ECUE_ALGO_AVANCE', 'id_ue' => $ue1->id_ue, 'credits_ecue' => 3]);
        $ecue1_2 = \App\Models\Ecue::factory()->create(['id_ecue' => 'ECUE_STRUC_DONNEES', 'id_ue' => $ue1->id_ue, 'credits_ecue' => 3]);

        Evaluer::create(['numero_carte_etudiant' => $etudiant->numero_carte_etudiant, 'id_ecue' => $ecue1_1->id_ecue, 'id_annee_academique' => $annee->id_annee_academique, 'note' => 12.00, 'date_evaluation' => now()]);
        Evaluer::create(['numero_carte_etudiant' => $etudiant->numero_carte_etudiant, 'id_ecue' => $ecue1_2->id_ecue, 'id_annee_academique' => $annee->id_annee_academique, 'note' => 15.00, 'date_evaluation' => now()]);

        $results = $this->academicJourneyService->calculateGrades($etudiant->numero_carte_etudiant, $annee->id_annee_academique);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('general_average', $results);
        $this->assertArrayHasKey('validated_credits', $results);
        $this->assertArrayHasKey('ue_details', $results);

        // (12*3 + 15*3) / (3+3) = (36+45)/6 = 81/6 = 13.5
        $this->assertEquals(13.50, $results['general_average']);
        $this->assertEquals(6, $results['validated_credits']); // UE_ALGO validée
        $this->assertCount(1, $results['ue_details']);
        $this->assertEquals(13.50, $results['ue_details'][0]['average']);
    }

    public function test_calculate_grades_returns_zero_for_no_notes()
    {
        $etudiant = \App\Models\Etudiant::factory()->create(['numero_carte_etudiant' => 'ETU-2025-0001']);
        $annee = AnneeAcademique::factory()->create(['id_annee_academique' => 'ANNEE-2025-2026']);

        $results = $this->academicJourneyService->calculateGrades($etudiant->numero_carte_etudiant, $annee->id_annee_academique);

        $this->assertEquals(0, $results['general_average']);
        $this->assertEquals(0, $results['validated_credits']);
        $this->assertEmpty($results['ue_details']);
    }
}
