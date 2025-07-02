<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    // Indique à Eloquent que la clé primaire n'est pas 'id'
    protected $primaryKey = 'session_id';

    // Indique à Eloquent que la clé primaire n'est pas auto-incrémentée
    public $incrementing = false;

    // Indique à Eloquent que la clé primaire est une chaîne de caractères
    protected $keyType = 'string';

    // Indique à Eloquent que la table n'a pas de timestamps (created_at, updated_at)
    public $timestamps = false;

    // Les attributs qui sont assignables en masse (si vous utilisez Session::create)
    protected $fillable = [
        'session_id', 'session_data', 'session_last_activity', 'session_lifetime', 'user_id'
    ];

    // Les attributs qui doivent être castés
    protected $casts = [
        'session_last_activity' => 'integer',
        'session_lifetime' => 'integer',
        'session_data' => 'string', // Ou 'array' si vous voulez que Laravel désérialise automatiquement
    ];
}
