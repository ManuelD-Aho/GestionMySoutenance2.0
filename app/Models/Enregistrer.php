<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enregistrer extends Model
{
    use HasFactory;

    protected $table = 'enregistrer';
    protected $primaryKey = 'id_enregistrement';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_enregistrement',
        'numero_utilisateur',
        'id_action',
        'date_action',
        'adresse_ip',
        'user_agent',
        'id_entite_concernee',
        'type_entite_concernee',
        'details_action',
        'session_id_utilisateur',
    ];

    protected $casts = [
        'date_action' => 'datetime',
        'details_action' => 'array',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'numero_utilisateur', 'numero_utilisateur');
    }

    public function action()
    {
        return $this->belongsTo(Action::class, 'id_action', 'id_action');
    }
}
