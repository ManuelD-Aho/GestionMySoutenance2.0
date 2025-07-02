<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attribuer extends Model
{
    use HasFactory;

    protected $table = 'attribuer';
    protected $primaryKey = ['numero_enseignant', 'id_specialite']; // Clé primaire composite
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'numero_enseignant',
        'id_specialite',
    ];

    // Relations
    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class, 'numero_enseignant', 'numero_enseignant');
    }

    public function specialite()
    {
        return $this->belongsTo(Specialite::class, 'id_specialite', 'id_specialite');
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
