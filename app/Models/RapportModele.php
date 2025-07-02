<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RapportModele extends Model
{
    use HasFactory;

    protected $table = 'rapport_modele';
    protected $primaryKey = 'id_modele';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_modele',
        'nom_modele',
        'description',
        'version',
        'statut',
    ];

    // Relations
    public function assignations()
    {
        return $this->hasMany(RapportModeleAssignation::class, 'id_modele', 'id_modele');
    }

    public function sections()
    {
        return $this->hasMany(RapportModeleSection::class, 'id_modele', 'id_modele');
    }
}
