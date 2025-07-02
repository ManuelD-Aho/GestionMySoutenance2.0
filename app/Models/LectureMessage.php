<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LectureMessage extends Model
{
    use HasFactory;

    protected $table = 'lecture_message';
    protected $primaryKey = ['id_message_chat', 'numero_utilisateur'];
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_message_chat',
        'numero_utilisateur',
        'date_lecture',
    ];

    protected $casts = [
        'date_lecture' => 'datetime',
    ];

    public function messageChat()
    {
        return $this->belongsTo(MessageChat::class, 'id_message_chat', 'id_message_chat');
    }

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'numero_utilisateur', 'numero_utilisateur');
    }
}
