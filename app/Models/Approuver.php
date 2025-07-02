<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Approuver extends Model
{
    use HasFactory;

    protected $table = 'approuver';
    protected $primaryKey = ['numero_personnel_administratif', 'id_rapport_etudiant'];
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'numero_personnel_administratif',
        'id_rapport_etudiant',
        'id_statut_conformite',
        'commentaire_conformite',
        'date_verification_conformite',
    ];

    protected $casts = [
        'date_verification_conformite' => 'datetime',
    ];

    public function personnelAdministratif()
    {
        return $this->belongsTo(PersonnelAdministratif::class, 'numero_personnel_administratif', 'numero_personnel_administratif');
    }

    public function rapportEtudiant()
    {
        return $this->belongsTo(RapportEtudiant::class, 'id_rapport_etudiant', 'id_rapport_etudiant');
    }

    public function statutConformiteRef()
    {
        return $this->belongsTo(StatutConformiteRef::class, 'id_statut_conformite', 'id_statut_conformite');
    }
}
