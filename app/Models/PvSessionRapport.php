<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PvSessionRapport extends Model
{
    use HasFactory;

    protected $table = 'pv_session_rapport';
    protected $primaryKey = ['id_compte_rendu', 'id_rapport_etudiant'];
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_compte_rendu',
        'id_rapport_etudiant',
    ];

    public function compteRendu()
    {
        return $this->belongsTo(CompteRendu::class, 'id_compte_rendu', 'id_compte_rendu');
    }

    public function rapportEtudiant()
    {
        return $this->belongsTo(RapportEtudiant::class, 'id_rapport_etudiant', 'id_rapport_etudiant');
    }
}
