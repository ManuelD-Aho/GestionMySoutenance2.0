<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Recevoir;
use App\Models\Conversation;
use App\Models\MessageChat;
use App\Models\ParticipantConversation;
use App\Models\MatriceNotificationRegle;
use App\Models\Utilisateur;
use App\Models\GroupeUtilisateur; // Ajouté pour la relation
use App\Utils\IdGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail; // Façade Mail de Laravel
use App\Exceptions\{ElementNotFoundException, OperationFailedException, EmailSendingException};
use App\Mail\PasswordResetMail; // Vos Mailables
use App\Mail\EmailValidationMail;
use App\Mail\AdminPasswordResetMail;
use App\Mail\GenericNotificationMail; // Mailable générique pour les notifications

class CommunicationService
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

    // --- Section 1: Envoi de Messages ---

    /**
     * Envoie une notification interne à un utilisateur.
     *
     * @param string $userId L'ID de l'utilisateur destinataire.
     * @param string $notificationTemplateId L'ID du modèle de notification.
     * @param array $variables Les variables pour personnaliser le contenu.
     * @return bool
     * @throws ElementNotFoundException Si le modèle de notification n'est pas trouvé.
     * @throws OperationFailedException Si l'enregistrement échoue.
     */
    public function sendInternalNotification(string $userId, string $notificationTemplateId, array $variables = []): bool
    {
        if (!Notification::find($notificationTemplateId)) {
            throw new ElementNotFoundException("Modèle de notification '{$notificationTemplateId}' non trouvé.");
        }

        $receptionId = $this->idGenerator->generateUniqueId('RECEP');

        if (!Recevoir::create([
            'id_reception' => $receptionId,
            'numero_utilisateur' => $userId,
            'id_notification' => $notificationTemplateId,
            'variables_contenu' => !empty($variables) ? json_encode($variables) : null,
            'date_reception' => now(),
            'lue' => false
        ])) {
            throw new OperationFailedException("Échec de l'enregistrement de la notification interne.");
        }
        return true;
    }

    /**
     * Envoie une notification interne à tous les membres d'un groupe d'utilisateurs.
     *
     * @param string $userGroupId L'ID du groupe d'utilisateurs.
     * @param string $notificationTemplateId L'ID du modèle de notification.
     * @param array $variables Les variables pour personnaliser le contenu.
     * @return bool
     */
    public function sendGroupNotification(string $userGroupId, string $notificationTemplateId, array $variables = []): bool
    {
        $members = Utilisateur::where('id_groupe_utilisateur', $userGroupId)
            ->where('statut_compte', 'actif')
            ->get();
        if ($members->isEmpty()) return false;

        $successCount = 0;
        foreach ($members as $member) {
            try {
                if ($this->sendInternalNotification($member->numero_utilisateur, $notificationTemplateId, $variables)) {
                    $successCount++;
                }
            } catch (\Exception $e) {
                Log::warning("Failed to send internal notification to user {$member->numero_utilisateur}: " . $e->getMessage());
            }
        }
        return $successCount > 0;
    }

    /**
     * Envoie un email à un destinataire.
     *
     * @param string $recipientEmail L'adresse email du destinataire.
     * @param string $notificationTemplateId L'ID du modèle de notification/email.
     * @param array $variables Les variables pour personnaliser le contenu.
     * @param array $attachments Les pièces jointes (tableau de ['path' => '...', 'name' => '...']).
     * @return bool
     * @throws ElementNotFoundException Si le modèle de notification n'est pas trouvé.
     * @throws EmailSendingException Si l'envoi de l'email échoue.
     */
    public function sendEmail(string $recipientEmail, string $notificationTemplateId, array $variables = [], array $attachments = []): bool
    {
        $user = Utilisateur::where('email_principal', $recipientEmail)->first();
        if ($user) {
            // Logique pour vérifier les préférences de notification de l'utilisateur
            // Si l'utilisateur a désactivé les emails pour ce type de notification, retourner true.
            // Exemple: $preferences = json_decode($user->preferences_notifications ?? '[]', true);
            // if (isset($preferences[$notificationTemplateId]['email']) && $preferences[$notificationTemplateId]['email'] === false) { return true; }
        }

        $template = Notification::find($notificationTemplateId);
        if (!$template) {
            throw new ElementNotFoundException("Modèle d'email '{$notificationTemplateId}' non trouvé.");
        }

        $subject = $this->personalizeMessage($template->libelle_notification, $variables);
        $bodyContent = $this->personalizeMessage($template->contenu, $variables);

        try {
            $mailable = null;
            switch ($notificationTemplateId) {
                case 'RESET_PASSWORD':
                    $mailable = new PasswordResetMail($variables['reset_link'], $subject);
                    break;
                case 'VALIDATE_EMAIL':
                    $mailable = new EmailValidationMail($variables['validation_link'], $subject);
                    break;
                case 'ADMIN_PASSWORD_RESET':
                    $mailable = new AdminPasswordResetMail($variables['login'], $variables['nouveau_mdp'], $subject);
                    break;
                // Ajoutez d'autres cas pour vos templates d'email spécifiques
                default:
                    // Fallback si pas de Mailable spécifique, utiliser une Mailable générique
                    $mailable = new GenericNotificationMail($subject, $bodyContent);
                    break;
            }

            // Ajouter les pièces jointes à la Mailable
            foreach ($attachments as $attachment) {
                $mailable->attach($attachment['path'], ['as' => $attachment['name']]);
            }

            Mail::to($recipientEmail)->send($mailable); // Envoi de l'email

            $this->supervisionService->recordAction(
                Auth::id() ?? 'SYSTEM',
                'ENVOI_EMAIL_SUCCES',
                null,
                'Email',
                ['recipient' => $recipientEmail, 'template' => $notificationTemplateId]
            );
            return true;
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'envoi de l'email : " . $e->getMessage());
            $this->supervisionService->recordAction(
                Auth::id() ?? 'SYSTEM',
                'ENVOI_EMAIL_ECHEC',
                null,
                'Email',
                ['recipient' => $recipientEmail, 'error' => $e->getMessage(), 'template' => $notificationTemplateId]
            );
            throw new EmailSendingException("Erreur lors de l'envoi de l'email : " . $e->getMessage());
        }
    }

    // --- Section 2: Messagerie Instantanée ---

    /**
     * Démarre une nouvelle conversation.
     *
     * @param array $participantIds Les IDs des utilisateurs participants.
     * @param string|null $conversationName Le nom de la conversation (optionnel).
     * @return string L'ID de la conversation créée.
     * @throws OperationFailedException Si moins de 2 participants sont fournis ou si la création échoue.
     */
    public function startConversation(array $participantIds, ?string $conversationName = null): string
    {
        if (count($participantIds) < 2) {
            throw new OperationFailedException("Une conversation doit avoir au moins 2 participants.");
        }

        $type = count($participantIds) > 2 ? 'Groupe' : 'Direct';
        $conversationId = $this->idGenerator->generateUniqueId('CONV');

        return DB::transaction(function () use ($conversationId, $conversationName, $type, $participantIds) {
            if (!Conversation::create([
                'id_conversation' => $conversationId,
                'nom_conversation' => $conversationName,
                'type_conversation' => $type,
                'date_creation_conv' => now()
            ])) {
                throw new OperationFailedException("Échec de la création de la conversation.");
            }

            foreach ($participantIds as $userId) {
                // Utilisation du Query Builder pour les clés composites de ParticipantConversation
                if (!DB::table('participant_conversation')->insert([
                    'id_conversation' => $conversationId,
                    'numero_utilisateur' => $userId
                ])) {
                    throw new OperationFailedException("Échec de l'ajout du participant {$userId} à la conversation.");
                }
            }
            return $conversationId;
        });
    }

    /**
     * Envoie un message dans une conversation.
     *
     * @param string $conversationId L'ID de la conversation.
     * @param string $senderId L'ID de l'expéditeur.
     * @param string $content Le contenu du message.
     * @param array|null $attachment La pièce jointe (tableau de 'path' et 'name').
     * @return string L'ID du message envoyé.
     * @throws OperationFailedException Si l'envoi du message échoue.
     */
    public function sendMessage(string $conversationId, string $senderId, string $content, ?array $attachment = null): string
    {
        $messageId = $this->idGenerator->generateUniqueId('MSG');
        if (!MessageChat::create([
            'id_message_chat' => $messageId,
            'id_conversation' => $conversationId,
            'numero_utilisateur_expediteur' => $senderId,
            'contenu_message' => $content,
            'piece_jointe_path' => $attachment['path'] ?? null,
            'piece_jointe_name' => $attachment['name'] ?? null,
            'date_envoi' => now()
        ])) {
            throw new OperationFailedException("Échec de l'envoi du message.");
        }
        return $messageId;
    }

    /**
     * Liste les conversations d'un utilisateur.
     *
     * @param string $userId L'ID de l'utilisateur.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listUserConversations(string $userId): \Illuminate\Database\Eloquent\Collection
    {
        return Conversation::whereHas('participantsConversation', function ($query) use ($userId) {
            $query->where('numero_utilisateur', $userId);
        })->get();
    }

    /**
     * Liste les messages d'une conversation.
     *
     * @param string $conversationId L'ID de la conversation.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listConversationMessages(string $conversationId): \Illuminate\Database\Eloquent\Collection
    {
        return MessageChat::where('id_conversation', $conversationId)
            ->orderBy('date_envoi')
            ->get();
    }

    // --- Section 3: Consultation & Gestion des Notifications ---

    /**
     * Liste les notifications non lues pour un utilisateur.
     *
     * @param string $userId L'ID de l'utilisateur.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listUnreadNotifications(string $userId): \Illuminate\Database\Eloquent\Collection
    {
        return Recevoir::where('numero_utilisateur', $userId)
            ->where('lue', false)
            ->with('notification') // Charger la relation pour obtenir le libellé
            ->orderByDesc('date_reception')
            ->get();
    }

    /**
     * Marque une notification comme lue.
     *
     * @param string $receptionId L'ID de la réception de notification.
     * @return bool
     * @throws ElementNotFoundException Si la réception n'est pas trouvée.
     */
    public function markNotificationAsRead(string $receptionId): bool
    {
        $reception = Recevoir::find($receptionId);
        if (!$reception) {
            throw new ElementNotFoundException("Réception de notification non trouvée.");
        }
        $reception->lue = true;
        $reception->date_lecture = now();
        return $reception->save();
    }

    /**
     * Liste tous les modèles de notification.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listNotificationModels(): \Illuminate\Database\Eloquent\Collection
    {
        return Notification::all();
    }

    /**
     * Met à jour un modèle de notification.
     *
     * @param string $id L'ID du modèle.
     * @param string $label Le nouveau libellé.
     * @param string $content Le nouveau contenu.
     * @return bool
     * @throws ElementNotFoundException Si le modèle n'est pas trouvé.
     */
    public function updateNotificationModel(string $id, string $label, string $content): bool
    {
        $notification = Notification::find($id);
        if (!$notification) {
            throw new ElementNotFoundException("Modèle de notification non trouvé.");
        }
        return $notification->update(['libelle_notification' => $label, 'contenu' => $content]);
    }

    /**
     * Liste toutes les règles de la matrice de notification.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listNotificationMatrixRules(): \Illuminate\Database\Eloquent\Collection
    {
        return MatriceNotificationRegle::with(['actionDeclencheur', 'groupeDestinataire'])->get();
    }

    /**
     * Met à jour une règle de la matrice de notification.
     *
     * @param string $ruleId L'ID de la règle.
     * @param string $channel Le canal de notification.
     * @param bool $isActive Indique si la règle est active.
     * @return bool
     * @throws ElementNotFoundException Si la règle n'est pas trouvée.
     */
    public function updateNotificationMatrixRule(string $ruleId, string $channel, bool $isActive): bool
    {
        $rule = MatriceNotificationRegle::find($ruleId);
        if (!$rule) {
            throw new ElementNotFoundException("Règle de notification non trouvée.");
        }
        return $rule->update([
            'canal_notification' => $channel,
            'est_active' => $isActive
        ]);
    }

    /**
     * Archive les conversations inactives.
     *
     * @param int $inactiveDays Le nombre de jours d'inactivité.
     * @return int Le nombre de conversations archivées.
     */
    public function archiveInactiveConversations(int $inactiveDays): int
    {
        $dateLimit = now()->subDays($inactiveDays);

        $archivedCount = DB::table('conversation as c')
            ->leftJoin(DB::raw('(SELECT id_conversation, MAX(date_envoi) as last_message_date FROM message_chat GROUP BY id_conversation) as mc'), 'c.id_conversation', '=', 'mc.id_conversation')
            ->where(function ($query) use ($dateLimit) {
                $query->whereNull('mc.last_message_date')
                    ->where('c.date_creation_conv', '<', $dateLimit);
            })
            ->orWhere(function ($query) use ($dateLimit) {
                $query->whereNotNull('mc.last_message_date')
                    ->where('mc.last_message_date', '<', $dateLimit)
                    ->where('c.type_conversation', '!=', 'Archivée'); // Assurez-vous que 'statut' est bien 'type_conversation' ou ajustez
            })
            ->update(['type_conversation' => 'Archivée']); // Assurez-vous que 'type_conversation' est la bonne colonne pour le statut

        if ($archivedCount > 0) {
            $this->supervisionService->recordAction(Auth::id() ?? 'SYSTEM', 'ARCHIVAGE_CONVERSATIONS', null, 'Conversation', ['count' => $archivedCount, 'days_inactive' => $inactiveDays]);
        }
        return $archivedCount;
    }

    /**
     * Personnalise un message avec des variables.
     *
     * @param string $message Le message avec des placeholders.
     * @param array $variables Les variables de remplacement.
     * @return string Le message personnalisé.
     */
    protected function personalizeMessage(string $message, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $message = str_replace("{{{$key}}}", htmlspecialchars((string)$value), $message);
        }
        return $message;
    }
}
