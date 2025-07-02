<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Acquerir extends Model
{
    use HasFactory;

    protected $table = 'acquerir';
    protected $primaryKey = ['id_grade', 'numero_enseignant'];
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_grade',
        'numero_enseignant',
        'date_acquisition',
    ];

    protected $casts = [
        'date_acquisition' => 'date',
    ];

    public function grade()
    {
        return $this->belongsTo(Grade::class, 'id_grade', 'id_grade');
    }

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class, 'numero_enseignant', 'numero_enseignant');
    }
}
