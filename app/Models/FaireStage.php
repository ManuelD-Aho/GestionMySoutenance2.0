<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaireStage extends Model
{
    use HasFactory;

    protected $table = 'faire_stage';
    protected $primaryKey = ['id_entreprise', 'numero_carte_etudiant']; // Clé primaire composite
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_entreprise',
        'numero_carte_etudiant',
        'date_debut_stage',
        'date_fin_stage',
        'sujet_stage',
        'nom_tuteur_entreprise',
    ];

    protected $casts = [
        'date_debut_stage' => 'date',
        'date_fin_stage' => 'date',
    ];

    // Relations
    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class, 'id_entreprise', 'id_entreprise');
    }

    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class, 'numero_carte_etudiant', 'numero_carte_etudiant');
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
