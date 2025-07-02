<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionRapport extends Model
{
    use HasFactory;

    protected $table = 'session_rapport';
    protected $primaryKey = ['id_session', 'id_rapport_etudiant']; // Clé primaire composite
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_session',
        'id_rapport_etudiant',
    ];

    // Relations
    public function sessionValidation()
    {
        return $this->belongsTo(SessionValidation::class, 'id_session', 'id_session');
    }

    public function rapportEtudiant()
    {
        return $this->belongsTo(RapportEtudiant::class, 'id_rapport_etudiant', 'id_rapport_etudiant');
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
