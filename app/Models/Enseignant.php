<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enseignant extends Model
{
    use HasFactory;

    protected $table = 'enseignant';
    protected $primaryKey = 'numero_enseignant';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'numero_enseignant',
        'nom',
        'prenom',
        'telephone_professionnel',
        'email_professionnel',
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
        'date_naissance' => 'date',
    ];

    // Relations
    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'numero_utilisateur', 'numero_utilisateur');
    }

    public function gradesAcquis()
    {
        return $this->hasMany(Acquerir::class, 'numero_enseignant', 'numero_enseignant');
    }

    public function fonctionsOccupees()
    {
        return $this->hasMany(Occuper::class, 'numero_enseignant', 'numero_enseignant');
    }

    public function specialitesAttribuees()
    {
        return $this->hasMany(Attribuer::class, 'numero_enseignant', 'numero_enseignant');
    }

    public function affectations()
    {
        return $this->hasMany(Affecter::class, 'numero_enseignant', 'numero_enseignant');
    }

    public function rendus()
    {
        return $this->hasMany(Rendre::class, 'numero_enseignant', 'numero_enseignant');
    }

    public function validationPvs()
    {
        return $this->hasMany(ValidationPv::class, 'numero_enseignant', 'numero_enseignant');
    }

    public function voteCommissions()
    {
        return $this->hasMany(VoteCommission::class, 'numero_enseignant', 'numero_enseignant');
    }

    public function sessionsValidation()
    {
        return $this->hasMany(SessionValidation::class, 'id_president_session', 'numero_enseignant');
    }
}
