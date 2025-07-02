<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rattacher extends Model
{
    use HasFactory;

    protected $table = 'rattacher';
    protected $primaryKey = ['id_groupe_utilisateur', 'id_traitement']; // Clé primaire composite
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_groupe_utilisateur',
        'id_traitement',
    ];

    // Relations
    public function groupeUtilisateur()
    {
        return $this->belongsTo(GroupeUtilisateur::class, 'id_groupe_utilisateur', 'id_groupe_utilisateur');
    }

    public function traitement()
    {
        return $this->belongsTo(Traitement::class, 'id_traitement', 'id_traitement');
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
