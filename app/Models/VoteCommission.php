<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoteCommission extends Model
{
    use HasFactory;

    protected $table = 'vote_commission';
    protected $primaryKey = 'id_vote';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_vote',
        'id_session',
        'id_rapport_etudiant',
        'numero_enseignant',
        'id_decision_vote',
        'commentaire_vote',
        'date_vote',
        'tour_vote',
    ];

    protected $casts = [
        'date_vote' => 'datetime',
        'tour_vote' => 'integer',
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

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class, 'numero_enseignant', 'numero_enseignant');
    }

    public function decisionVoteRef()
    {
        return $this->belongsTo(DecisionVoteRef::class, 'id_decision_vote', 'id_decision_vote');
    }
}
