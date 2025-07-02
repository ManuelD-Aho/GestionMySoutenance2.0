<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Affecter extends Model
{
    use HasFactory;

    protected $table = 'affecter';
    protected $primaryKey = ['numero_enseignant', 'id_rapport_etudiant', 'id_statut_jury'];
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'numero_enseignant',
        'id_rapport_etudiant',
        'id_statut_jury',
        'directeur_memoire',
        'date_affectation',
    ];

    protected $casts = [
        'directeur_memoire' => 'boolean',
        'date_affectation' => 'datetime',
    ];

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class, 'numero_enseignant', 'numero_enseignant');
    }

    public function rapportEtudiant()
    {
        return $this->belongsTo(RapportEtudiant::class, 'id_rapport_etudiant', 'id_rapport_etudiant');
    }

    public function statutJury()
    {
        return $this->belongsTo(StatutJury::class, 'id_statut_jury', 'id_statut_jury');
    }
}
