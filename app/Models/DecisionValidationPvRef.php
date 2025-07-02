<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DecisionValidationPvRef extends Model
{
    use HasFactory;

    protected $table = 'decision_validation_pv_ref';
    protected $primaryKey = 'id_decision_validation_pv';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_decision_validation_pv',
        'libelle_decision_validation_pv',
    ];

    public function validationPvs()
    {
        return $this->hasMany(ValidationPv::class, 'id_decision_validation_pv', 'id_decision_validation_pv');
    }
}
