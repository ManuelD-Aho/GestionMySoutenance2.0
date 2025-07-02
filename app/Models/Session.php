<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory; // Ajouté pour la cohérence

class Session extends Model
{
    use HasFactory; // Ajouté pour la cohérence

    protected $table = 'sessions';
    protected $primaryKey = 'session_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'session_id',
        'session_data',
        'session_last_activity',
        'session_lifetime',
        'user_id',
    ];

    protected $casts = [
        'session_last_activity' => 'integer',
        'session_lifetime' => 'integer',
        // 'session_data' => 'array', // Si les données sont stockées en JSON, sinon 'string'
    ];

    // Relations
    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'user_id', 'numero_utilisateur');
    }
}
