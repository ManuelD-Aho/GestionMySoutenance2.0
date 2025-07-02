<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entreprise extends Model
{
    use HasFactory;

    protected $table = 'entreprise';
    protected $primaryKey = 'id_entreprise';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_entreprise',
        'libelle_entreprise',
        'secteur_activite',
        'adresse_entreprise',
        'contact_nom',
        'contact_email',
        'contact_telephone',
    ];

    // Relations
    public function faireStages()
    {
        return $this->hasMany(FaireStage::class, 'id_entreprise', 'id_entreprise');
    }
}
