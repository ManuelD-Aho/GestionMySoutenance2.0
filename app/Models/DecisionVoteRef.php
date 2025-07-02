<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DecisionVoteRef extends Model
{
    use HasFactory;

    protected $table = 'decision_vote_ref';
    protected $primaryKey = 'id_decision_vote';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_decision_vote',
        'libelle_decision_vote',
    ];

    // Relations
    public function voteCommissions()
    {
        return $this->hasMany(VoteCommission::class, 'id_decision_vote', 'id_decision_vote');
    }
}
