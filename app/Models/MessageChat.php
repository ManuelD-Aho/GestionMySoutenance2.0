<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageChat extends Model
{
    use HasFactory;

    protected $table = 'message_chat';
    protected $primaryKey = 'id_message_chat';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_message_chat',
        'id_conversation',
        'numero_utilisateur_expediteur',
        'contenu_message',
        'date_envoi',
    ];

    protected $casts = [
        'date_envoi' => 'datetime',
    ];

    // Relations
    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'id_conversation', 'id_conversation');
    }

    public function utilisateurExpediteur()
    {
        return $this->belongsTo(Utilisateur::class, 'numero_utilisateur_expediteur', 'numero_utilisateur');
    }

    public function lecturesMessage()
    {
        return $this->hasMany(LectureMessage::class, 'id_message_chat', 'id_message_chat');
    }
}
