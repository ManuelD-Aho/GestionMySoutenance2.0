<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Specialite extends Model
{
    use HasFactory;

    protected $table = 'specialite';
    protected $primaryKey = 'id_specialite';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_specialite',
        'libelle_specialite',
        'numero_enseignant_specialite',
    ];

    public function enseignantSpecialite()
    {
        return $this->belongsTo(Enseignant::class, 'numero_enseignant_specialite', 'numero_enseignant');
    }

    public function attributions()
    {
        return $this->hasMany(Attribuer::class, 'id_specialite', 'id_specialite');
    }
}
