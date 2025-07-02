<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatutConformiteRef extends Model
{
    use HasFactory;

    protected $table = 'statut_conformite_ref';
    protected $primaryKey = 'id_statut_conformite';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_statut_conformite',
        'libelle_statut_conformite',
    ];

    public function approbations()
    {
        return $this->hasMany(Approuver::class, 'id_statut_conformite', 'id_statut_conformite');
    }
}
