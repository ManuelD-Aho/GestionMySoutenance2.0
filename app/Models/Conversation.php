<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $table = 'conversation';
    protected $primaryKey = 'id_conversation';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_conversation',
        'nom_conversation',
        'date_creation_conv',
        'type_conversation',
    ];

    protected $casts = [
        'date_creation_conv' => 'datetime',
    ];

    // Relations
    public function messagesChat()
    {
        return $this->hasMany(MessageChat::class, 'id_conversation', 'id_conversation');
    }

    public function participantsConversation()
    {
        return $this->hasMany(ParticipantConversation::class, 'id_conversation', 'id_conversation');
    }
}
