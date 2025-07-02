<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupeUtilisateur extends Model
{
    use HasFactory;

    protected $table = 'groupe_utilisateur';
    protected $primaryKey = 'id_groupe_utilisateur';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_groupe_utilisateur',
        'libelle_groupe_utilisateur',
    ];

    // Relations
    public function utilisateurs()
    {
        return $this->hasMany(Utilisateur::class, 'id_groupe_utilisateur', 'id_groupe_utilisateur');
    }

    public function rattachements()
    {
        return $this->hasMany(Rattacher::class, 'id_groupe_utilisateur', 'id_groupe_utilisateur');
    }

    public function matriceNotificationRegles()
    {
        return $this->hasMany(MatriceNotificationRegle::class, 'id_groupe_destinataire', 'id_groupe_utilisateur');
    }
}
