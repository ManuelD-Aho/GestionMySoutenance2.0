<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Etudiant extends Model
{
    use HasFactory;

    protected $table = 'etudiant';
    protected $primaryKey = 'numero_carte_etudiant';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'numero_carte_etudiant',
        'nom',
        'prenom',
        'date_naissance',
        'lieu_naissance',
        'pays_naissance',
        'nationalite',
        'sexe',
        'adresse_postale',
        'ville',
        'code_postal',
        'telephone',
        'email_contact_secondaire',
        'numero_utilisateur',
        'contact_urgence_nom',
        'contact_urgence_telephone',
        'contact_urgence_relation',
    ];

    protected $casts = [
        'date_naissance' => 'date',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'numero_utilisateur', 'numero_utilisateur');
    }

    public function inscriptions()
    {
        return $this->hasMany(Inscrire::class, 'numero_carte_etudiant', 'numero_carte_etudiant');
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluer::class, 'numero_carte_etudiant', 'numero_carte_etudiant');
    }

    public function faireStages()
    {
        return $this->hasMany(FaireStage::class, 'numero_carte_etudiant', 'numero_carte_etudiant');
    }

    public function penalites()
    {
        return $this->hasMany(Penalite::class, 'numero_carte_etudiant', 'numero_carte_etudiant');
    }

    public function reclamations()
    {
        return $this->hasMany(Reclamation::class, 'numero_carte_etudiant', 'numero_carte_etudiant');
    }

    public function rapportsEtudiant()
    {
        return $this->hasMany(RapportEtudiant::class, 'numero_carte_etudiant', 'numero_carte_etudiant');
    }
}
