<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rendre extends Model
{
    use HasFactory;

    protected $table = 'rendre';
    protected $primaryKey = ['numero_enseignant', 'id_compte_rendu']; // Clé primaire composite
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'numero_enseignant',
        'id_compte_rendu',
        'date_action_sur_pv',
    ];

    protected $casts = [
        'date_action_sur_pv' => 'datetime',
    ];

    // Relations
    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class, 'numero_enseignant', 'numero_enseignant');
    }

    public function compteRendu()
    {
        return $this->belongsTo(CompteRendu::class, 'id_compte_rendu', 'id_compte_rendu');
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
