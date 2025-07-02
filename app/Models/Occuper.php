<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Occuper extends Model
{
    use HasFactory;

    protected $table = 'occuper';
    protected $primaryKey = ['id_fonction', 'numero_enseignant'];
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_fonction',
        'numero_enseignant',
        'date_debut_occupation',
        'date_fin_occupation',
    ];

    protected $casts = [
        'date_debut_occupation' => 'date',
        'date_fin_occupation' => 'date',
    ];

    public function fonction()
    {
        return $this->belongsTo(Fonction::class, 'id_fonction', 'id_fonction');
    }

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class, 'numero_enseignant', 'numero_enseignant');
    }
}
