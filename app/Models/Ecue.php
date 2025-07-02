<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ecue extends Model
{
    use HasFactory;

    protected $table = 'ecue';
    protected $primaryKey = 'id_ecue';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_ecue',
        'libelle_ecue',
        'id_ue',
        'credits_ecue',
    ];

    protected $casts = [
        'credits_ecue' => 'integer',
    ];

    // Relations
    public function ue()
    {
        return $this->belongsTo(Ue::class, 'id_ue', 'id_ue');
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluer::class, 'id_ecue', 'id_ecue');
    }
}
