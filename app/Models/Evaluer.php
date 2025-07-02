<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluer extends Model
{
    use HasFactory;

    protected $table = 'evaluer';
    protected $primaryKey = ['numero_carte_etudiant', 'id_ecue', 'id_annee_academique'];
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'numero_carte_etudiant',
        'id_ecue',
        'id_annee_academique',
        'date_evaluation',
        'note',
    ];

    protected $casts = [
        'date_evaluation' => 'datetime',
        'note' => 'decimal:2',
    ];

    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class, 'numero_carte_etudiant', 'numero_carte_etudiant');
    }

    public function ecue()
    {
        return $this->belongsTo(Ecue::class, 'id_ecue', 'id_ecue');
    }

    public function anneeAcademique()
    {
        return $this->belongsTo(AnneeAcademique::class, 'id_annee_academique', 'id_annee_academique');
    }
}
