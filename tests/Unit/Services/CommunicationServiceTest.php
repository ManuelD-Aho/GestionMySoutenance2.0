<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\CommunicationService;
use App\Services\SupervisionService;
use App\Services\SystemService;
use App\Utils\IdGenerator;
use App\Models\Notification;
use App\Models\Recevoir;
use App\Models\Utilisateur;
use App\Models\GroupeUtilisateur;
use App\Models\MatriceNotificationRegle;
use App\Models\Conversation;
use App\Models\MessageChat;
use App\Models\ParticipantConversation;
use App\Exceptions\ElementNotFoundException;
use App\Exceptions\OperationFailedException;
use App\Exceptions\EmailSendingException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Mockery;

class CommunicationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $communicationService;
    protected $idGeneratorMock;
    protected $supervisionServiceMock;
    protected $systemServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idGeneratorMock = Mockery::mock(IdGenerator::class);
        $this->supervisionServiceMock = Mockery::mock(SupervisionService::class);
        $this->systemServiceMock = Mockery::mock(SystemService::class);

        $this->communicationService = new CommunicationService(
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

    // --- Tests pour sendInternalNotification ---

    public function test_send_internal_notification_successfully()
    {
        Notification::factory()->create(['id_notification' => 'TEST_NOTIF']);
        Utilisateur::factory()->create(['numero_utilisateur' => 'ETU-001']);

        $this->idGeneratorMock->shouldReceive('generateUniqueId')->once()->andReturn('RECEP-001');

        $result = $this->communicationService->sendInternalNotification('ETU-001', 'TEST_NOTIF', ['var' => 'value']);

        $this->assertTrue($result);
        $this->assertDatabaseHas('recevoir', [
            'id_reception' => 'RECEP-001',
            'numero_utilisateur' => 'ETU-001',
            'id_notification' => 'TEST_NOTIF',
            'variables_contenu' => json_encode(['var' => 'value']),
            'lue' => false,
        ]);
    }

    public function test_send_internal_notification_throws_element_not_found_exception_for_invalid_template()
    {
        $this->expectException(ElementNotFoundException::class);
        $this->communicationService->sendInternalNotification('ETU-001', 'NON_EXISTENT_NOTIF');
    }

    // --- Tests pour sendGroupNotification ---

    public function test_send_group_notification_successfully()
    {
        $group = GroupeUtilisateur::factory()->create(['id_groupe_utilisateur' => 'GRP-TEST']);
        Notification::factory()->create(['id_notification' => 'GROUP_NOTIF']);
        Utilisateur::factory()->count(2)->create(['id_groupe_utilisateur' => $group->id_groupe_utilisateur, 'statut_compte' => 'actif']);
        Utilisateur::factory()->create(['id_groupe_utilisateur' => $group->id_groupe_utilisateur, 'statut_compte' => 'inactif']); // Inactif ne doit pas recevoir

        $this->idGeneratorMock->shouldReceive('generateUniqueId')->times(2)->andReturn('RECEP-001', 'RECEP-002');

        $result = $this->communicationService->sendGroupNotification($group->id_groupe_utilisateur, 'GROUP_NOTIF');

        $this->assertTrue($result);
        $this->assertDatabaseCount('recevoir', 2); // Seulement 2 actifs
    }

    public function test_send_group_notification_returns_false_if_no_active_members()
    {
        $group = GroupeUtilisateur::factory()->create(['id_groupe_utilisateur' => 'GRP-TEST']);
        Notification::factory()->create(['id_notification' => 'GROUP_NOTIF']);
        Utilisateur::factory()->count(2)->create(['id_groupe_utilisateur' => $group->id_groupe_utilisateur, 'statut_compte' => 'inactif']);

        $result = $this->communicationService->sendGroupNotification($group->id_groupe_utilisateur, 'GROUP_NOTIF');

        $this->assertFalse($result);
        $this->assertDatabaseCount('recevoir', 0);
    }

    // --- Tests pour sendEmail ---

    public function test_send_email_successfully()
    {
        Mail::fake(); // Active le faux mailer de Laravel
        Notification::factory()->create(['id_notification' => 'TEST_EMAIL', 'libelle_notification' => 'Test Subject', 'contenu' => 'Hello {{name}}']);
        Utilisateur::factory()->create(['email_principal' => 'test@example.com']);

        $this->systemServiceMock->shouldReceive('getParametre')->andReturn('smtp.test.com', true, 'user@test.com', 'password', 'tls', 587, 'from@test.com', 'App Name');

        $result = $this->communicationService->sendEmail('test@example.com', 'TEST_EMAIL', ['name' => 'John Doe']);

        $this->assertTrue($result);
        Mail::assertSent(\App\Mail\GenericNotificationMail::class, function ($mail) {
            return $mail->hasTo('test@example.com') &&
                $mail->mailSubject === 'Test Subject' &&
                Str::contains($mail->mailContent, 'Hello John Doe');
        });
        $this->supervisionServiceMock->shouldHaveReceived('recordAction')->once()->with(Mockery::any(), 'ENVOI_EMAIL_SUCCES', Mockery::any(), 'Email', Mockery::any());
    }

    public function test_send_email_throws_email_sending_exception_on_failure()
    {
        Mail::fake(); // Active le faux mailer de Laravel
        Mail::shouldReceive('to->send')->andThrow(new \Exception('Mailer error')); // Simule une erreur d'envoi

        Notification::factory()->create(['id_notification' => 'TEST_EMAIL', 'libelle_notification' => 'Test Subject', 'contenu' => 'Hello']);
        Utilisateur::factory()->create(['email_principal' => 'test@example.com']);

        $this->systemServiceMock->shouldReceive('getParametre')->andReturn('smtp.test.com', true, 'user@test.com', 'password', 'tls', 587, 'from@test.com', 'App Name');
        $this->supervisionServiceMock->shouldReceive('recordAction')->once()->with(Mockery::any(), 'ENVOI_EMAIL_ECHEC', Mockery::any(), 'Email', Mockery::any());

        $this->expectException(EmailSendingException::class);
        $this->communicationService->sendEmail('test@example.com', 'TEST_EMAIL');
    }

    // --- Tests pour Messagerie Instantanée ---

    public function test_start_conversation_successfully()
    {
        Utilisateur::factory()->count(2)->create(['numero_utilisateur' => 'USR-001', 'USR-002']);

        $this->idGeneratorMock->shouldReceive('generateUniqueId')->once()->andReturn('CONV-001');

        $conversationId = $this->communicationService->startConversation(['USR-001', 'USR-002'], 'Test Conversation');

        $this->assertEquals('CONV-001', $conversationId);
        $this->assertDatabaseHas('conversation', ['id_conversation' => 'CONV-001', 'nom_conversation' => 'Test Conversation', 'type_conversation' => 'Direct']);
        $this->assertDatabaseHas('participant_conversation', ['id_conversation' => 'CONV-001', 'numero_utilisateur' => 'USR-001']);
        $this->assertDatabaseHas('participant_conversation', ['id_conversation' => 'CONV-001', 'numero_utilisateur' => 'USR-002']);
    }

    public function test_send_message_successfully()
    {
        Conversation::factory()->create(['id_conversation' => 'CONV-001']);
        Utilisateur::factory()->create(['numero_utilisateur' => 'USR-001']);

        $this->idGeneratorMock->shouldReceive('generateUniqueId')->once()->andReturn('MSG-001');

        $messageId = $this->communicationService->sendMessage('CONV-001', 'USR-001', 'Hello World');

        $this->assertEquals('MSG-001', $messageId);
        $this->assertDatabaseHas('message_chat', ['id_message_chat' => 'MSG-001', 'id_conversation' => 'CONV-001', 'numero_utilisateur_expediteur' => 'USR-001', 'contenu_message' => 'Hello World']);
    }

    // --- Tests pour Consultation & Gestion des Notifications ---

    public function test_list_unread_notifications_successfully()
    {
        $user = Utilisateur::factory()->create(['numero_utilisateur' => 'ETU-001']);
        $notif1 = Notification::factory()->create(['id_notification' => 'NOTIF-001', 'libelle_notification' => 'Notif 1']);
        $notif2 = Notification::factory()->create(['id_notification' => 'NOTIF-002', 'libelle_notification' => 'Notif 2']);

        Recevoir::create(['id_reception' => 'RECEP-001', 'numero_utilisateur' => $user->numero_utilisateur, 'id_notification' => $notif1->id_notification, 'date_reception' => now(), 'lue' => false]);
        Recevoir::create(['id_reception' => 'RECEP-002', 'numero_utilisateur' => $user->numero_utilisateur, 'id_notification' => $notif2->id_notification, 'date_reception' => now(), 'lue' => true]);

        $unreadNotifications = $this->communicationService->listUnreadNotifications($user->numero_utilisateur);

        $this->assertCount(1, $unreadNotifications);
        $this->assertEquals('NOTIF-001', $unreadNotifications->first()->id_notification);
    }

    public function test_mark_notification_as_read_successfully()
    {
        $user = Utilisateur::factory()->create(['numero_utilisateur' => 'ETU-001']);
        $notif = Notification::factory()->create(['id_notification' => 'NOTIF-001']);
        $reception = Recevoir::create(['id_reception' => 'RECEP-001', 'numero_utilisateur' => $user->numero_utilisateur, 'id_notification' => $notif->id_notification, 'date_reception' => now(), 'lue' => false]);

        $result = $this->communicationService->markNotificationAsRead($reception->id_reception);

        $this->assertTrue($result);
        $this->assertDatabaseHas('recevoir', ['id_reception' => $reception->id_reception, 'lue' => true]);
    }

    public function test_update_notification_model_successfully()
    {
        $notif = Notification::factory()->create(['id_notification' => 'NOTIF-001', 'libelle_notification' => 'Old Label', 'contenu' => 'Old Content']);

        $result = $this->communicationService->updateNotificationModel($notif->id_notification, 'New Label', 'New Content');

        $this->assertTrue($result);
        $this->assertDatabaseHas('notification', ['id_notification' => 'NOTIF-001', 'libelle_notification' => 'New Label', 'contenu' => 'New Content']);
    }

    public function test_update_notification_matrix_rule_successfully()
    {
        $rule = MatriceNotificationRegle::factory()->create(['id_regle' => 'REGLE-001', 'canal_notification' => 'Interne', 'est_active' => true]);

        $result = $this->communicationService->updateNotificationMatrixRule('REGLE-001', 'Email', false);

        $this->assertTrue($result);
        $this->assertDatabaseHas('matrice_notification_regles', ['id_regle' => 'REGLE-001', 'canal_notification' => 'Email', 'est_active' => false]);
    }

    public function test_archive_inactive_conversations_successfully()
    {
        // Créer des conversations et messages pour tester l'archivage
        $conv1 = Conversation::factory()->create(['id_conversation' => 'CONV-001', 'date_creation_conv' => now()->subDays(60)]);
        $conv2 = Conversation::factory()->create(['id_conversation' => 'CONV-002', 'date_creation_conv' => now()->subDays(10)]);
        $conv3 = Conversation::factory()->create(['id_conversation' => 'CONV-003', 'date_creation_conv' => now()->subDays(60)]);

        MessageChat::create(['id_message_chat' => 'MSG-001', 'id_conversation' => $conv1->id_conversation, 'numero_utilisateur_expediteur' => 'USR-TEST-001', 'contenu_message' => 'Old message', 'date_envoi' => now()->subDays(50)]);
        MessageChat::create(['id_message_chat' => 'MSG-002', 'id_conversation' => $conv2->id_conversation, 'numero_utilisateur_expediteur' => 'USR-TEST-001', 'contenu_message' => 'Recent message', 'date_envoi' => now()->subDays(5)]);
        // Conv3 n'a pas de message, donc date_creation_conv est utilisée

        $this->supervisionServiceMock->shouldReceive('recordAction')->once();

        $archivedCount = $this->communicationService->archiveInactiveConversations(30); // Archive celles de plus de 30 jours

        $this->assertEquals(1, $archivedCount); // CONV-003 devrait être archivée
        $this->assertDatabaseHas('conversation', ['id_conversation' => 'CONV-003', 'type_conversation' => 'Archivée']);
        $this->assertDatabaseMissing('conversation', ['id_conversation' => 'CONV-001', 'type_conversation' => 'Archivée']); // CONV-001 a un message plus récent que la date limite
        $this->assertDatabaseMissing('conversation', ['id_conversation' => 'CONV-002', 'type_conversation' => 'Archivée']);
    }
}
