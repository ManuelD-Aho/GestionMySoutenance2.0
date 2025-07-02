<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recevoir extends Model
{
    use HasFactory;

    protected $table = 'recevoir';
    protected $primaryKey = 'id_reception';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_reception',
        'numero_utilisateur',
        'id_notification',
        'date_reception',
        'lue',
        'date_lecture',
    ];

    protected $casts = [
        'date_reception' => 'datetime',
        'lue' => 'boolean',
        'date_lecture' => 'datetime',
    ];

    // Relations
    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'numero_utilisateur', 'numero_utilisateur');
    }

    public function notification()
    {
        return $this->belongsTo(Notification::class, 'id_notification', 'id_notification');
    }
}
