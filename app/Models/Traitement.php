<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Traitement extends Model
{
    use HasFactory;

    protected $table = 'traitement';
    protected $primaryKey = 'id_traitement';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_traitement',
        'libelle_traitement',
        'id_parent_traitement',
        'icone_class',
        'url_associee',
        'ordre_affichage',
    ];

    protected $casts = [
        'ordre_affichage' => 'integer',
    ];

    // Relations
    public function parentTraitement()
    {
        return $this->belongsTo(Traitement::class, 'id_parent_traitement', 'id_traitement');
    }

    public function enfants()
    {
        return $this->hasMany(Traitement::class, 'id_parent_traitement', 'id_traitement');
    }

    public function rattachements()
    {
        return $this->hasMany(Rattacher::class, 'id_traitement', 'id_traitement');
    }

    public function delegations()
    {
        return $this->hasMany(Delegation::class, 'id_traitement', 'id_traitement');
    }

    public function pistes()
    {
        return $this->hasMany(Pister::class, 'id_traitement', 'id_traitement');
    }
}
