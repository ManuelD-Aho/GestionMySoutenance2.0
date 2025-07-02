<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatutPvRef extends Model
{
    use HasFactory;

    protected $table = 'statut_pv_ref';
    protected $primaryKey = 'id_statut_pv';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_statut_pv',
        'libelle_statut_pv',
    ];

    // Relations
    public function compteRendus()
    {
        return $this->hasMany(CompteRendu::class, 'id_statut_pv', 'id_statut_pv');
    }
}
