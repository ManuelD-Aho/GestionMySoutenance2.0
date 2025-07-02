<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatutPaiementRef extends Model
{
    use HasFactory;

    protected $table = 'statut_paiement_ref';
    protected $primaryKey = 'id_statut_paiement';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_statut_paiement',
        'libelle_statut_paiement',
    ];

    public function inscriptions()
    {
        return $this->hasMany(Inscrire::class, 'id_statut_paiement', 'id_statut_paiement');
    }
}
