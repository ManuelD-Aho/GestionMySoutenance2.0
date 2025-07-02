<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NiveauAccesDonne extends Model
{
    use HasFactory;

    protected $table = 'niveau_acces_donne';
    protected $primaryKey = 'id_niveau_acces_donne';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_niveau_acces_donne',
        'libelle_niveau_acces_donne',
    ];

    public function utilisateurs()
    {
        return $this->hasMany(Utilisateur::class, 'id_niveau_acces_donne', 'id_niveau_acces_donne');
    }
}
