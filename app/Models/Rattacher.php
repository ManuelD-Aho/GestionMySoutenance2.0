<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rattacher extends Model
{
    use HasFactory;

    protected $table = 'rattacher';
    protected $primaryKey = ['id_groupe_utilisateur', 'id_traitement'];
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_groupe_utilisateur',
        'id_traitement',
    ];

    public function groupeUtilisateur()
    {
        return $this->belongsTo(GroupeUtilisateur::class, 'id_groupe_utilisateur', 'id_groupe_utilisateur');
    }

    public function traitement()
    {
        return $this->belongsTo(Traitement::class, 'id_traitement', 'id_traitement');
    }
}
