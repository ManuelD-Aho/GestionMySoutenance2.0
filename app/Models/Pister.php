<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pister extends Model
{
    use HasFactory;

    protected $table = 'pister';
    protected $primaryKey = 'id_piste';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_piste',
        'numero_utilisateur',
        'id_traitement',
        'date_pister',
        'acceder',
    ];

    protected $casts = [
        'date_pister' => 'datetime',
        'acceder' => 'boolean',
    ];

    // Relations
    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'numero_utilisateur', 'numero_utilisateur');
    }

    public function traitement()
    {
        return $this->belongsTo(Traitement::class, 'id_traitement', 'id_traitement');
    }
}
