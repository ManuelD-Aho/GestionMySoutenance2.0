<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reclamation extends Model
{
    use HasFactory;

    protected $table = 'reclamation';
    protected $primaryKey = 'id_reclamation';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_reclamation',
        'numero_carte_etudiant',
        'sujet_reclamation',
        'description_reclamation',
        'date_soumission',
        'id_statut_reclamation',
        'reponse_reclamation',
        'date_reponse',
        'numero_personnel_traitant',
    ];

    protected $casts = [
        'date_soumission' => 'datetime',
        'date_reponse' => 'datetime',
    ];

    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class, 'numero_carte_etudiant', 'numero_carte_etudiant');
    }

    public function statutReclamationRef()
    {
        return $this->belongsTo(StatutReclamationRef::class, 'id_statut_reclamation', 'id_statut_reclamation');
    }

    public function personnelTraitant()
    {
        return $this->belongsTo(PersonnelAdministratif::class, 'numero_personnel_traitant', 'numero_personnel_administratif');
    }
}
