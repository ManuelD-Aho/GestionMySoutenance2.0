<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueueJob extends Model
{
    use HasFactory;

    protected $table = 'queue_jobs';
    protected $primaryKey = 'id';
    public $incrementing = true; // Cette table a une PK auto-incrémentée
    protected $keyType = 'integer';
    public $timestamps = false; // Utilise des colonnes de timestamps personnalisées

    // Mappage des colonnes de timestamps personnalisées
    // const CREATED_AT = 'created_at'; // Déjà par défaut
    // const UPDATED_AT = null; // Pas de colonne updated_at dans votre schéma pour cette table

    protected $fillable = [
        'job_name',
        'payload',
        'status',
        'attempts',
        'created_at',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array', // Assuming payload is JSON
        'attempts' => 'integer',
        'created_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
