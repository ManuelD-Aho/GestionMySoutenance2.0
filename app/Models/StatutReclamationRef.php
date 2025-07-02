<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatutReclamationRef extends Model
{
    use HasFactory;

    protected $table = 'statut_reclamation_ref';
    protected $primaryKey = 'id_statut_reclamation';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_statut_reclamation',
        'libelle_statut_reclamation',
    ];

    public function reclamations()
    {
        return $this->hasMany(Reclamation::class, 'id_statut_reclamation', 'id_statut_reclamation');
    }
}
