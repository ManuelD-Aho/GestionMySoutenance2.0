<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonnelAdministratif extends Model
{
    use HasFactory;

    protected $table = 'personnel_administratif';
    protected $primaryKey = 'numero_personnel_administratif';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'numero_personnel_administratif',
        'nom',
        'prenom',
        'telephone_professionnel',
        'email_professionnel',
        'date_affectation_service',
        'responsabilites_cles',
        'numero_utilisateur',
        'date_naissance',
        'lieu_naissance',
        'pays_naissance',
        'nationalite',
        'sexe',
        'adresse_postale',
        'ville',
        'code_postal',
        'telephone_personnel',
        'email_personnel_secondaire',
    ];

    protected $casts = [
        'date_affectation_service' => 'date',
        'date_naissance' => 'date',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'numero_utilisateur', 'numero_utilisateur');
    }

    public function approbations()
    {
        return $this->hasMany(Approuver::class, 'numero_personnel_administratif', 'numero_personnel_administratif');
    }

    public function penalites()
    {
        return $this->hasMany(Penalite::class, 'numero_personnel_traitant', 'numero_personnel_administratif');
    }

    public function reclamations()
    {
        return $this->hasMany(Reclamation::class, 'numero_personnel_traitant', 'numero_personnel_administratif');
    }
}
