<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    use HasFactory;

    protected $table = 'sequences';
    protected $primaryKey = ['nom_sequence', 'annee'];
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'nom_sequence',
        'annee',
        'valeur_actuelle',
    ];

    protected $casts = [
        'annee' => 'integer',
        'valeur_actuelle' => 'integer',
    ];
}
