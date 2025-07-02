<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    use HasFactory;

    protected $table = 'grade';
    protected $primaryKey = 'id_grade';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_grade',
        'libelle_grade',
        'abreviation_grade',
    ];

    public function acquisitions()
    {
        return $this->hasMany(Acquerir::class, 'id_grade', 'id_grade');
    }
}
