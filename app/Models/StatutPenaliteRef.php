<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatutPenaliteRef extends Model
{
    use HasFactory;

    protected $table = 'statut_penalite_ref';
    protected $primaryKey = 'id_statut_penalite';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_statut_penalite',
        'libelle_statut_penalite',
    ];

    public function penalites()
    {
        return $this->hasMany(Penalite::class, 'id_statut_penalite', 'id_statut_penalite');
    }
}
