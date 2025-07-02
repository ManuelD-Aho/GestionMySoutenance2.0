<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RapportModeleAssignation extends Model
{
    use HasFactory;

    protected $table = 'rapport_modele_assignation';
    protected $primaryKey = ['id_modele', 'id_niveau_etude'];
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_modele',
        'id_niveau_etude',
    ];

    public function rapportModele()
    {
        return $this->belongsTo(RapportModele::class, 'id_modele', 'id_modele');
    }

    public function niveauEtude()
    {
        return $this->belongsTo(NiveauEtude::class, 'id_niveau_etude', 'id_niveau_etude');
    }
}
