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
    public $timestamps = false;

    protected $fillable = [
        'id_modele',
        'nom_modele',
        'description',
        'version',
        'statut',
    ];

    public function assignations()
    {
        return $this->hasMany(RapportModeleAssignation::class, 'id_modele', 'id_modele');
    }

    public function sections()
    {
        return $this->hasMany(RapportModeleSection::class, 'id_modele', 'id_modele');
    }
}
