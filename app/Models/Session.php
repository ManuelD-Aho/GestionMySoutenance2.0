<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    protected $primaryKey = 'session_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'session_id', 'session_data', 'session_last_activity', 'session_lifetime', 'user_id'
    ];

    protected $casts = [
        'session_last_activity' => 'integer',
        'session_lifetime' => 'integer',
        'session_data' => 'string',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'user_id', 'numero_utilisateur');
    }
}
