<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penalite extends Model
{
    use HasFactory;

    protected $table = 'penalite';
    protected $primaryKey = 'id_penalite';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_penalite',
        'numero_carte_etudiant',
        'id_annee_academique',
        'type_penalite',
        'montant_du',
        'motif',
        'id_statut_penalite',
        'date_creation',
        'date_regularisation',
        'numero_personnel_traitant',
    ];

    protected $casts = [
        'montant_du' => 'decimal:2',
        'date_creation' => 'datetime',
        'date_regularisation' => 'datetime',
    ];

    // Relations
    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class, 'numero_carte_etudiant', 'numero_carte_etudiant');
    }

    public function anneeAcademique()
    {
        return $this->belongsTo(AnneeAcademique::class, 'id_annee_academique', 'id_annee_academique');
    }

    public function statutPenaliteRef()
    {
        return $this->belongsTo(StatutPenaliteRef::class, 'id_statut_penalite', 'id_statut_penalite');
    }

    public function personnelTraitant()
    {
        return $this->belongsTo(PersonnelAdministratif::class, 'numero_personnel_traitant', 'numero_personnel_administratif');
    }
}
