<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DecisionPassageRef extends Model
{
    use HasFactory;

    protected $table = 'decision_passage_ref';
    protected $primaryKey = 'id_decision_passage';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_decision_passage',
        'libelle_decision_passage',
    ];

    // Relations
    public function inscriptions()
    {
        return $this->hasMany(Inscrire::class, 'id_decision_passage', 'id_decision_passage');
    }
}
