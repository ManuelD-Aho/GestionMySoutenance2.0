<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoriqueMotDePasse extends Model
{
    use HasFactory;

    protected $table = 'historique_mot_de_passe';
    protected $primaryKey = 'id_historique_mdp';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_historique_mdp',
        'numero_utilisateur',
        'mot_de_passe_hache',
        'date_changement',
    ];

    protected $casts = [
        'date_changement' => 'datetime',
    ];

    // Relations
    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'numero_utilisateur', 'numero_utilisateur');
    }
}
