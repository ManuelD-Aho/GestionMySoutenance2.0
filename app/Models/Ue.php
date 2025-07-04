<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ue extends Model
{
    use HasFactory;

    protected $table = 'ue';
    protected $primaryKey = 'id_ue';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_ue',
        'libelle_ue',
        'credits_ue',
    ];

    protected $casts = [
        'credits_ue' => 'integer',
    ];

    // Relations
    public function ecues()
    {
        return $this->hasMany(Ecue::class, 'id_ue', 'id_ue');
    }
}
