<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompteRendu extends Model
{
    use HasFactory;

    protected $table = 'compte_rendu';
    protected $primaryKey = 'id_compte_rendu';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_compte_rendu',
        'id_rapport_etudiant',
        'type_pv',
        'libelle_compte_rendu',
        'date_creation_pv',
        'id_statut_pv',
        'id_redacteur',
        'date_limite_approbation',
    ];

    protected $casts = [
        'date_creation_pv' => 'datetime',
        'date_limite_approbation' => 'datetime',
    ];

    public function rapportEtudiant()
    {
        return $this->belongsTo(RapportEtudiant::class, 'id_rapport_etudiant', 'id_rapport_etudiant');
    }

    public function statutPvRef()
    {
        return $this->belongsTo(StatutPvRef::class, 'id_statut_pv', 'id_statut_pv');
    }

    public function redacteur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_redacteur', 'numero_utilisateur');
    }

    public function pvSessionRapports()
    {
        return $this->hasMany(PvSessionRapport::class, 'id_compte_rendu', 'id_compte_rendu');
    }

    public function validationsPv()
    {
        return $this->hasMany(ValidationPv::class, 'id_compte_rendu', 'id_compte_rendu');
    }

    public function rendus()
    {
        return $this->hasMany(Rendre::class, 'id_compte_rendu', 'id_compte_rendu');
    }
}
