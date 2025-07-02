<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatutRapportRef extends Model
{
    use HasFactory;

    protected $table = 'statut_rapport_ref';
    protected $primaryKey = 'id_statut_rapport';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_statut_rapport',
        'libelle_statut_rapport',
        'etape_workflow',
    ];

    protected $casts = [
        'etape_workflow' => 'integer',
    ];

    // Relations
    public function rapportsEtudiant()
    {
        return $this->hasMany(RapportEtudiant::class, 'id_statut_rapport', 'id_statut_rapport');
    }
}
