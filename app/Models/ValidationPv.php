<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ValidationPv extends Model
{
    use HasFactory;

    protected $table = 'validation_pv';
    protected $primaryKey = ['id_compte_rendu', 'numero_enseignant'];
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_compte_rendu',
        'numero_enseignant',
        'id_decision_validation_pv',
        'date_validation',
        'commentaire_validation_pv',
    ];

    protected $casts = [
        'date_validation' => 'datetime',
    ];

    public function compteRendu()
    {
        return $this->belongsTo(CompteRendu::class, 'id_compte_rendu', 'id_compte_rendu');
    }

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class, 'numero_enseignant', 'numero_enseignant');
    }

    public function decisionValidationPvRef()
    {
        return $this->belongsTo(DecisionValidationPvRef::class, 'id_decision_validation_pv', 'id_decision_validation_pv');
    }
}
