<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CritereConformiteRef extends Model
{
    use HasFactory;

    protected $table = 'critere_conformite_ref';
    protected $primaryKey = 'id_critere';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_critere',
        'libelle_critere',
        'description',
        'est_actif',
    ];

    protected $casts = [
        'est_actif' => 'boolean',
    ];

    public function conformiteRapportDetails()
    {
        return $this->hasMany(ConformiteRapportDetail::class, 'id_critere', 'id_critere');
    }
}
