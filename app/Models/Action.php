<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Action extends Model
{
    use HasFactory;

    protected $table = 'action';
    protected $primaryKey = 'id_action';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_action',
        'libelle_action',
        'categorie_action',
    ];

    // Relations
    public function enregistrements()
    {
        return $this->hasMany(Enregistrer::class, 'id_action', 'id_action');
    }

    public function matriceNotificationRegles()
    {
        return $this->hasMany(MatriceNotificationRegle::class, 'id_action_declencheur', 'id_action');
    }
}
