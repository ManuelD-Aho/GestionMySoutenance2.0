<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Occuper extends Model
{
    use HasFactory;

    protected $table = 'occuper';
    protected $primaryKey = ['id_fonction', 'numero_enseignant']; // Clé primaire composite
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_fonction',
        'numero_enseignant',
        'date_debut_occupation',
        'date_fin_occupation',
    ];

    protected $casts = [
        'date_debut_occupation' => 'date',
        'date_fin_occupation' => 'date',
    ];

    // Relations
    public function fonction()
    {
        return $this->belongsTo(Fonction::class, 'id_fonction', 'id_fonction');
    }

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class, 'numero_enseignant', 'numero_enseignant');
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
