<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SectionRapport extends Model
{
    use HasFactory;

    protected $table = 'section_rapport';
    protected $primaryKey = ['id_rapport_etudiant', 'titre_section']; // Clé primaire composite
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_rapport_etudiant',
        'titre_section',
        'contenu_section',
        'ordre',
        'date_creation',
        'date_derniere_modif',
    ];

    protected $casts = [
        'ordre' => 'integer',
        'date_creation' => 'datetime',
        'date_derniere_modif' => 'datetime',
    ];

    // Relations
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
