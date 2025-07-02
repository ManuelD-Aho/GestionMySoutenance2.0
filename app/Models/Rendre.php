<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rendre extends Model
{
    use HasFactory;

    protected $table = 'rendre';
    protected $primaryKey = ['numero_enseignant', 'id_compte_rendu'];
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'numero_enseignant',
        'id_compte_rendu',
        'date_action_sur_pv',
    ];

    protected $casts = [
        'date_action_sur_pv' => 'datetime',
    ];

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class, 'numero_enseignant', 'numero_enseignant');
    }

    public function compteRendu()
    {
        return $this->belongsTo(CompteRendu::class, 'id_compte_rendu', 'id_compte_rendu');
    }
}
