<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnneeAcademique extends Model
{
    use HasFactory;

    protected $table = 'annee_academique';
    protected $primaryKey = 'id_annee_academique';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_annee_academique',
        'libelle_annee_academique',
        'date_debut',
        'date_fin',
        'est_active',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'est_active' => 'boolean',
    ];

    // Relations
    public function inscriptions()
    {
        return $this->hasMany(Inscrire::class, 'id_annee_academique', 'id_annee_academique');
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluer::class, 'id_annee_academique', 'id_annee_academique');
    }

    public function penalites()
    {
        return $this->hasMany(Penalite::class, 'id_annee_academique', 'id_annee_academique');
    }
}
