<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Affecter extends Model
{
    use HasFactory;

    protected $table = 'affecter';
    protected $primaryKey = ['numero_enseignant', 'id_rapport_etudiant', 'id_statut_jury']; // Clé primaire composite
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'numero_enseignant',
        'id_rapport_etudiant',
        'id_statut_jury',
        'directeur_memoire',
        'date_affectation',
    ];

    protected $casts = [
        'directeur_memoire' => 'boolean',
        'date_affectation' => 'datetime',
    ];

    // Relations
    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class, 'numero_enseignant', 'numero_enseignant');
    }

    public function rapportEtudiant()
    {
        return $this->belongsTo(RapportEtudiant::class, 'id_rapport_etudiant', 'id_rapport_etudiant');
    }

    public function statutJury()
    {
        return $this->belongsTo(StatutJury::class, 'id_statut_jury', 'id_statut_jury');
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
