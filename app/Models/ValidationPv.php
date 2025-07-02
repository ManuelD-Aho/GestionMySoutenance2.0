<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ValidationPv extends Model
{
    use HasFactory;

    protected $table = 'validation_pv';
    protected $primaryKey = ['id_compte_rendu', 'numero_enseignant']; // Clé primaire composite
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

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

    // Relations
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

    /**
     * Set the keys for a save update query.
     * Required for composite primary keys.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if (!is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ($keys as $key) {
            $query->where($key, '=', $this->original[$key] ?? $this->getAttribute($key));
        }

        return $query;
    }
}
