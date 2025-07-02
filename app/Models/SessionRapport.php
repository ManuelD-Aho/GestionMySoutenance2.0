<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionRapport extends Model
{
    use HasFactory;

    protected $table = 'session_rapport';
    protected $primaryKey = ['id_session', 'id_rapport_etudiant'];
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_session',
        'id_rapport_etudiant',
    ];

    public function sessionValidation()
    {
        return $this->belongsTo(SessionValidation::class, 'id_session', 'id_session');
    }

    public function rapportEtudiant()
    {
        return $this->belongsTo(RapportEtudiant::class, 'id_rapport_etudiant', 'id_rapport_etudiant');
    }
}
