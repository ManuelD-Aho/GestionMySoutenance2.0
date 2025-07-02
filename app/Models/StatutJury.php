<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatutJury extends Model
{
    use HasFactory;

    protected $table = 'statut_jury';
    protected $primaryKey = 'id_statut_jury';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_statut_jury',
        'libelle_statut_jury',
    ];

    public function affectations()
    {
        return $this->hasMany(Affecter::class, 'id_statut_jury', 'id_statut_jury');
    }
}
