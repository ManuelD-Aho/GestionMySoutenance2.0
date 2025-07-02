<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attribuer extends Model
{
    use HasFactory;

    protected $table = 'attribuer';
    protected $primaryKey = ['numero_enseignant', 'id_specialite'];
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'numero_enseignant',
        'id_specialite',
    ];

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class, 'numero_enseignant', 'numero_enseignant');
    }

    public function specialite()
    {
        return $this->belongsTo(Specialite::class, 'id_specialite', 'id_specialite');
    }
}
