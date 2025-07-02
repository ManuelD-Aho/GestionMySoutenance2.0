<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    use HasFactory;

    protected $table = 'sequences';
    protected $primaryKey = ['nom_sequence', 'annee']; // Clé primaire composite
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'nom_sequence',
        'annee',
        'valeur_actuelle',
    ];

    protected $casts = [
        'annee' => 'integer',
        'valeur_actuelle' => 'integer',
    ];

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
