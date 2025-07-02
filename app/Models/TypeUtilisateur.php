<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeUtilisateur extends Model
{
    use HasFactory;

    protected $table = 'type_utilisateur';
    protected $primaryKey = 'id_type_utilisateur';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_type_utilisateur',
        'libelle_type_utilisateur',
    ];

    // Relations
    public function utilisateurs()
    {
        return $this->hasMany(Utilisateur::class, 'id_type_utilisateur', 'id_type_utilisateur');
    }
}
