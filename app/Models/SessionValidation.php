<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionValidation extends Model
{
    use HasFactory;

    protected $table = 'session_validation';
    protected $primaryKey = 'id_session';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_session',
        'nom_session',
        'date_debut_session',
        'date_fin_prevue',
        'date_creation',
        'id_president_session',
        'mode_session',
        'statut_session',
        'nombre_votants_requis',
    ];

    protected $casts = [
        'date_debut_session' => 'datetime',
        'date_fin_prevue' => 'datetime',
        'date_creation' => 'datetime',
        'nombre_votants_requis' => 'integer',
    ];

    // Relations
    public function presidentSession()
    {
        return $this->belongsTo(Enseignant::class, 'id_president_session', 'numero_enseignant');
    }

    public function sessionRapports()
    {
        return $this->hasMany(SessionRapport::class, 'id_session', 'id_session');
    }

    public function voteCommissions()
    {
        return $this->hasMany(VoteCommission::class, 'id_session', 'id_session');
    }
}
