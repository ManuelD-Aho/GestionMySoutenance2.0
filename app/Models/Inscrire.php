<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inscrire extends Model
{
    use HasFactory;

    protected $table = 'inscrire';
    protected $primaryKey = ['numero_carte_etudiant', 'id_niveau_etude', 'id_annee_academique']; // Clé primaire composite
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'numero_carte_etudiant',
        'id_niveau_etude',
        'id_annee_academique',
        'montant_inscription',
        'date_inscription',
        'id_statut_paiement',
        'date_paiement',
        'numero_recu_paiement',
        'id_decision_passage',
    ];

    protected $casts = [
        'montant_inscription' => 'decimal:2',
        'date_inscription' => 'datetime',
        'date_paiement' => 'datetime',
    ];

    // Relations
    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class, 'numero_carte_etudiant', 'numero_carte_etudiant');
    }

    public function niveauEtude()
    {
        return $this->belongsTo(NiveauEtude::class, 'id_niveau_etude', 'id_niveau_etude');
    }

    public function anneeAcademique()
    {
        return $this->belongsTo(AnneeAcademique::class, 'id_annee_academique', 'id_annee_academique');
    }

    public function statutPaiementRef()
    {
        return $this->belongsTo(StatutPaiementRef::class, 'id_statut_paiement', 'id_statut_paiement');
    }

    public function decisionPassageRef()
    {
        return $this->belongsTo(DecisionPassageRef::class, 'id_decision_passage', 'id_decision_passage');
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
