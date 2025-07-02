<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RapportModeleAssignation extends Model
{
    use HasFactory;

    protected $table = 'rapport_modele_assignation';
    protected $primaryKey = ['id_modele', 'id_niveau_etude']; // Clé primaire composite
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_modele',
        'id_niveau_etude',
    ];

    // Relations
    public function rapportModele()
    {
        return $this->belongsTo(RapportModele::class, 'id_modele', 'id_modele');
    }

    public function niveauEtude()
    {
        return $this->belongsTo(NiveauEtude::class, 'id_niveau_etude', 'id_niveau_etude');
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
