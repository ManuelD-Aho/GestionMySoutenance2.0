<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delegation extends Model
{
    use HasFactory;

    protected $table = 'delegation';
    protected $primaryKey = 'id_delegation';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_delegation',
        'id_delegant',
        'id_delegue',
        'id_traitement',
        'date_debut',
        'date_fin',
        'statut',
        'contexte_id',
        'contexte_type',
    ];

    protected $casts = [
        'date_debut' => 'datetime',
        'date_fin' => 'datetime',
    ];

    public function delegant()
    {
        return $this->belongsTo(Utilisateur::class, 'id_delegant', 'numero_utilisateur');
    }

    public function delegue()
    {
        return $this->belongsTo(Utilisateur::class, 'id_delegue', 'numero_utilisateur');
    }

    public function traitement()
    {
        return $this->belongsTo(Traitement::class, 'id_traitement', 'id_traitement');
    }
}
