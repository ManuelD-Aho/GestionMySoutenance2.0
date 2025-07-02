<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParticipantConversation extends Model
{
    use HasFactory;

    protected $table = 'participant_conversation';
    protected $primaryKey = ['id_conversation', 'numero_utilisateur']; // Clé primaire composite
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_conversation',
        'numero_utilisateur',
    ];

    // Relations
    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'id_conversation', 'id_conversation');
    }

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'numero_utilisateur', 'numero_utilisateur');
    }

    /**
     * Set the keys for a save update query.
     * Required for composite primary keys.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if (!is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ($keys as $key) {
            $query->where($key, '=', $this->original[$key] ?? $this->getAttribute($key));
        }

        return $query;
    }
}
