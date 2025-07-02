<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluer extends Model
{
    use HasFactory;

    protected $table = 'evaluer';
    protected $primaryKey = ['numero_carte_etudiant', 'id_ecue', 'id_annee_academique']; // Clé primaire composite
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'numero_carte_etudiant',
        'id_ecue',
        'id_annee_academique',
        'date_evaluation',
        'note',
    ];

    protected $casts = [
        'date_evaluation' => 'datetime',
        'note' => 'decimal:2',
    ];

    // Relations
    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class, 'numero_carte_etudiant', 'numero_carte_etudiant');
    }

    public function ecue()
    {
        return $this->belongsTo(Ecue::class, 'id_ecue', 'id_ecue');
    }

    public function anneeAcademique()
    {
        return $this->belongsTo(AnneeAcademique::class, 'id_annee_academique', 'id_annee_academique');
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
