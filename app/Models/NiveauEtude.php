<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NiveauEtude extends Model
{
    use HasFactory;

    protected $table = 'niveau_etude';
    protected $primaryKey = 'id_niveau_etude';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_niveau_etude',
        'libelle_niveau_etude',
    ];

    public function inscriptions()
    {
        return $this->hasMany(Inscrire::class, 'id_niveau_etude', 'id_niveau_etude');
    }

    public function rapportModeleAssignations()
    {
        return $this->hasMany(RapportModeleAssignation::class, 'id_niveau_etude', 'id_niveau_etude');
    }
}
