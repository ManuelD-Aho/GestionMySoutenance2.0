<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fonction extends Model
{
    use HasFactory;

    protected $table = 'fonction';
    protected $primaryKey = 'id_fonction';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_fonction',
        'libelle_fonction',
    ];

    public function occupations()
    {
        return $this->hasMany(Occuper::class, 'id_fonction', 'id_fonction');
    }
}
