<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\SupervisionService;
use App\Services\SystemService; // Nécessaire pour IdGenerator
use App\Utils\IdGenerator;
use App\Models\Enregistrer;
use App\Models\Pister;
use App\Models\Action;
use App\Models\QueueJob;
use App\Models\Utilisateur;
use App\Models\RapportEtudiant;
use App\Models\StatutRapportRef;
use App\Models\StatutReclamationRef;
use App\Exceptions\ElementNotFoundException;
use App\Exceptions\OperationFailedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;

class SupervisionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $supervisionService;
    protected $idGeneratorMock;
    protected $systemServiceMock; // Mock de SystemService

    protected function setUp(): void
    {
        parent::setUp();

        $this->systemServiceMock = Mockery::mock(SystemService::class);
        // IdGenerator a besoin de SystemService, donc nous devons le mocker pour le constructeur d'IdGenerator
        $this->idGeneratorMock = Mockery::mock(IdGenerator::class, [$this->supervisionService, $this->systemServiceMock]);

        $this->supervisionService = new SupervisionService(
            $this->idGeneratorMock
        );

        // Créer un utilisateur authentifié pour les tests qui enregistrent des actions
        $this->actingAs(Utilisateur::factory()->create(['numero_utilisateur' => 'USR-TEST-001']));

        // Créer les actions de référence nécessaires
        Action::factory()->create(['id_action' => 'TEST_ACTION', 'libelle_action' => 'Test Action', 'categorie_action' => 'Test']);
        Action::factory()->create(['id_action' => 'PURGE_LOGS', 'libelle_action' => 'Purge Logs', 'categorie_action' => 'Maintenance']);
        Action::factory()->create(['id_action' => 'HANDLE_ASYNC_TASK', 'libelle_action' => 'Handle Async Task', 'categorie_action' => 'Maintenance']);
        Action::factory()->create(['id_action' => 'ECHEC_GENERATION_ID_UNIQUE', 'libelle_action' => 'Échec Génération ID Unique', 'categorie_action' => 'Système']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // --- Tests pour recordAction ---

    public function test_record_action_successfully_with_existing_action()
    {
        $userId = Auth::id();
        $actionId = 'TEST_ACTION';
        $entityId = 'ENT-001';
        $entityType = 'Entity';
        $details = ['key' => 'value'];

        $this->idGeneratorMock->shouldReceive('generateUniqueId')->once()->andReturn('LOG-001');

        $result = $this->supervisionService->recordAction($userId, $actionId, $entityId, $entityType, $details);

        $this->assertTrue($result);
        $this->assertDatabaseHas('enregistrer', [
            'id_enregistrement' => 'LOG-001',
            'numero_utilisateur' => $userId,
            'id_action' => $actionId,
            'id_entite_concernee' => $entityId,
            'type_entite_concernee' => $entityType,
            'details_action' => json_encode($details),
        ]);
    }

    public function test_record_action_successfully_with_new_action()
    {
        $userId = Auth::id();
        $actionId = 'NEW_ACTION';
        $details = ['new_key' => 'new_value'];

        $this->idGeneratorMock->shouldReceive('generateUniqueId')->once()->andReturn('LOG-002');

        $result = $this->supervisionService->recordAction($userId, $actionId, null, null, $details);

        $this->assertTrue($result);
        $this->assertDatabaseHas('action', ['id_action' => 'NEW_ACTION', 'libelle_action' => 'New Action', 'categorie_action' => 'Dynamique']);
        $this->assertDatabaseHas('enregistrer', ['id_enregistrement' => 'LOG-002', 'id_action' => 'NEW_ACTION']);
    }

    // --- Tests pour recordAccess ---

    public function test_record_access_successfully()
    {
        $userId = Auth::id();
        $traitementId = 'TRAIT_ADMIN_DASHBOARD_ACCEDER';

        $this->idGeneratorMock->shouldReceive('generateUniqueId')->once()->andReturn('PISTE-001');

        $result = $this->supervisionService->recordAccess($userId, $traitementId);

        $this->assertTrue($result);
        $this->assertDatabaseHas('pister', [
            'id_piste' => 'PISTE-001',
            'numero_utilisateur' => $userId,
            'id_traitement' => $traitementId,
            'acceder' => true,
        ]);
    }

    // --- Tests pour consultLogs ---

    public function test_consult_logs_returns_filtered_and_paginated_data()
    {
        $user1 = Utilisateur::factory()->create(['numero_utilisateur' => 'USR-001', 'login_utilisateur' => 'user1']);
        $user2 = Utilisateur::factory()->create(['numero_utilisateur' => 'USR-002', 'login_utilisateur' => 'user2']);
        $action1 = Action::factory()->create(['id_action' => 'ACTION-001']);
        $action2 = Action::factory()->create(['id_action' => 'ACTION-002']);

        Enregistrer::factory()->create(['numero_utilisateur' => $user1->numero_utilisateur, 'id_action' => $action1->id_action, 'date_action' => now()->subDays(5)]);
        Enregistrer::factory()->create(['numero_utilisateur' => $user2->numero_utilisateur, 'id_action' => $action2->id_action, 'date_action' => now()->subDays(2)]);
        Enregistrer::factory()->create(['numero_utilisateur' => $user1->numero_utilisateur, 'id_action' => $action2->id_action, 'date_action' => now()->subDays(1)]);

        $filters = ['numero_utilisateur' => $user1->numero_utilisateur];
        $logs = $this->supervisionService->consultLogs($filters);

        $this->assertCount(2, $logs);
        $this->assertEquals($user1->numero_utilisateur, $logs->first()->numero_utilisateur);
    }

    // --- Tests pour purgeOldLogs ---

    public function test_purge_old_logs_deletes_records_before_date_limit()
    {
        Enregistrer::factory()->create(['date_action' => now()->subDays(10)]);
        Enregistrer::factory()->create(['date_action' => now()->subDays(5)]);
        Enregistrer::factory()->create(['date_action' => now()->subDays(1)]);

        $this->idGeneratorMock->shouldReceive('generateUniqueId')->once()->andReturn('LOG-PURGE-001'); // Pour l'action de purge

        $deletedCount = $this->supervisionService->purgeOldLogs(now()->subDays(7)->toDateString());

        $this->assertEquals(1, $deletedCount);
        $this->assertDatabaseCount('enregistrer', 2); // 2 logs devraient rester
        $this->assertDatabaseMissing('enregistrer', ['date_action' => now()->subDays(10)->toDateString()]);
        $this->supervisionServiceMock->shouldHaveReceived('recordAction')->once(); // Vérifie l'enregistrement de l'action de purge
    }

    // --- Tests pour consultErrorLogs ---

    public function test_consult_error_logs_returns_file_content()
    {
        $logContent = "Line 1\nLine 2\nLine 3";
        $logFilePath = storage_path('logs/test_error.log');
        file_put_contents($logFilePath, $logContent);

        $content = $this->supervisionService->consultErrorLogs($logFilePath);

        $this->assertEquals($logContent, $content);
        unlink($logFilePath); // Nettoyage
    }

    public function test_consult_error_logs_throws_exception_if_file_not_found()
    {
        $this->expectException(OperationFailedException::class);
        $this->supervisionService->consultErrorLogs('non_existent_file.log');
    }

    // --- Tests pour listAsyncTasks ---

    public function test_list_async_tasks_returns_filtered_tasks()
    {
        QueueJob::factory()->create(['job_name' => 'Job1', 'status' => 'pending']);
        QueueJob::factory()->create(['job_name' => 'Job2', 'status' => 'completed']);
        QueueJob::factory()->create(['job_name' => 'Job3', 'status' => 'pending']);

        $tasks = $this->supervisionService->listAsyncTasks(['status' => 'pending']);

        $this->assertCount(2, $tasks);
        $this->assertEquals('Job1', $tasks->first()->job_name);
    }

    // --- Tests pour manageAsyncTask ---

    public function test_manage_async_task_relances_task()
    {
        $task = QueueJob::factory()->create(['job_name' => 'FailedJob', 'status' => 'failed']);

        $this->supervisionServiceMock->shouldReceive('recordAction')->once();

        $result = $this->supervisionService->manageAsyncTask($task->id, 'relancer');

        $this->assertTrue($result);
        $this->assertDatabaseHas('queue_jobs', ['job_name' => 'FailedJob', 'status' => 'failed', 'error_message' => 'Relancée par administrateur.']); // Ancienne tâche marquée
        $this->assertDatabaseHas('queue_jobs', ['job_name' => 'FailedJob', 'status' => 'pending', 'attempts' => 0]); // Nouvelle tâche créée
    }

    public function test_manage_async_task_deletes_task()
    {
        $task = QueueJob::factory()->create(['job_name' => 'JobToDelete', 'status' => 'completed']);

        $this->supervisionServiceMock->shouldReceive('recordAction')->once();

        $result = $this->supervisionService->manageAsyncTask($task->id, 'supprimer');

        $this->assertTrue($result);
        $this->assertDatabaseMissing('queue_jobs', ['id' => $task->id]);
    }

    public function test_manage_async_task_throws_element_not_found_exception()
    {
        $this->expectException(ElementNotFoundException::class);
        $this->supervisionService->manageAsyncTask(999, 'supprimer');
    }

    // --- Tests pour generateAdminDashboardStats ---

    public function test_generate_admin_dashboard_stats_returns_correct_data()
    {
        // Setup des données pour les statistiques
        Utilisateur::factory()->count(2)->create(['statut_compte' => 'actif']);
        Utilisateur::factory()->create(['statut_compte' => 'bloque']);

        RapportEtudiant::factory()->create(['id_rapport_etudiant' => 'RAP-001', 'id_statut_rapport' => 'RAP_SOUMIS']);
        RapportEtudiant::factory()->create(['id_rapport_etudiant' => 'RAP-002', 'id_statut_rapport' => 'RAP_CONF']);
        StatutRapportRef::factory()->create(['id_statut_rapport' => 'RAP_SOUMIS', 'libelle_statut_rapport' => 'Soumis']);
        StatutRapportRef::factory()->create(['id_statut_rapport' => 'RAP_CONF', 'libelle_statut_rapport' => 'Conforme']);

        QueueJob::factory()->create(['job_name' => 'JobA', 'status' => 'pending']);
        QueueJob::factory()->create(['job_name' => 'JobB', 'status' => 'failed']);

        Enregistrer::factory()->create(['id_action' => 'LOGIN', 'date_action' => now()->subDays(1)]);
        Enregistrer::factory()->create(['id_action' => 'LOGOUT', 'date_action' => now()->subDays(2)]);
        Enregistrer::factory()->create(['id_action' => 'LOGIN', 'date_action' => now()->subDays(8)]); // Hors période

        Reclamation::factory()->create(['id_reclamation' => 'RECLA-001', 'id_statut_reclamation' => 'RECLA_OUVERTE']);
        StatutReclamationRef::factory()->create(['id_statut_reclamation' => 'RECLA_OUVERTE', 'libelle_statut_reclamation' => 'Ouverte']);

        $stats = $this->supervisionService->generateAdminDashboardStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('users', $stats);
        $this->assertArrayHasKey('reports', $stats);
        $this->assertArrayHasKey('queue', $stats);
        $this->assertArrayHasKey('recent_activity', $stats);
        $this->assertArrayHasKey('complaints', $stats);

        $this->assertEquals(3, $stats['users']['total']);
        $this->assertEquals(2, $stats['users']['actif']);
        $this->assertEquals(1, $stats['users']['bloque']);

        $this->assertEquals(1, $stats['reports']['Soumis']);
        $this->assertEquals(1, $stats['reports']['Conforme']);

        $this->assertEquals(1, $stats['queue']['pending']);
        $this->assertEquals(1, $stats['queue']['failed']);

        $this->assertEquals(1, $stats['recent_activity']['LOGIN']);
        $this->assertEquals(1, $stats['recent_activity']['LOGOUT']);

        $this->assertEquals(1, $stats['complaints']['Ouverte']);
    }
}
