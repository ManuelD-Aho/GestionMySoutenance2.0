<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RapportEtudiant extends Model
{
    use HasFactory;

    protected $table = 'rapport_etudiant';
    protected $primaryKey = 'id_rapport_etudiant';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_rapport_etudiant',
        'libelle_rapport_etudiant',
        'theme',
        'resume',
        'numero_attestation_stage',
        'numero_carte_etudiant',
        'nombre_pages',
        'id_statut_rapport',
        'date_soumission',
        'date_derniere_modif',
    ];

    protected $casts = [
        'nombre_pages' => 'integer',
        'date_soumission' => 'datetime',
        'date_derniere_modif' => 'datetime',
    ];

    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class, 'numero_carte_etudiant', 'numero_carte_etudiant');
    }

    public function statutRapportRef()
    {
        return $this->belongsTo(StatutRapportRef::class, 'id_statut_rapport', 'id_statut_rapport');
    }

    public function affectations()
    {
        return $this->hasMany(Affecter::class, 'id_rapport_etudiant', 'id_rapport_etudiant');
    }

    public function approbations()
    {
        return $this->hasMany(Approuver::class, 'id_rapport_etudiant', 'id_rapport_etudiant');
    }

    public function compteRendus()
    {
        return $this->hasMany(CompteRendu::class, 'id_rapport_etudiant', 'id_rapport_etudiant');
    }

    public function conformiteRapportDetails()
    {
        return $this->hasMany(ConformiteRapportDetail::class, 'id_rapport_etudiant', 'id_rapport_etudiant');
    }

    public function pvSessionRapports()
    {
        return $this->hasMany(PvSessionRapport::class, 'id_rapport_etudiant', 'id_rapport_etudiant');
    }

    public function sectionRapports()
    {
        return $this->hasMany(SectionRapport::class, 'id_rapport_etudiant', 'id_rapport_etudiant');
    }

    public function sessionRapports()
    {
        return $this->hasMany(SessionRapport::class, 'id_rapport_etudiant', 'id_rapport_etudiant');
    }

    public function voteCommissions()
    {
        return $this->hasMany(VoteCommission::class, 'id_rapport_etudiant', 'id_rapport_etudiant');
    }
}
