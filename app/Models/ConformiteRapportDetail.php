<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConformiteRapportDetail extends Model
{
    use HasFactory;

    protected $table = 'conformite_rapport_details';
    protected $primaryKey = 'id_conformite_detail';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_conformite_detail',
        'id_rapport_etudiant',
        'id_critere',
        'statut_validation',
        'commentaire',
        'date_verification',
    ];

    protected $casts = [
        'date_verification' => 'datetime',
    ];

    public function rapportEtudiant()
    {
        return $this->belongsTo(RapportEtudiant::class, 'id_rapport_etudiant', 'id_rapport_etudiant');
    }

    public function critereConformiteRef()
    {
        return $this->belongsTo(CritereConformiteRef::class, 'id_critere', 'id_critere');
    }
}
