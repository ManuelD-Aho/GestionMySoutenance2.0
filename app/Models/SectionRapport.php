<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SectionRapport extends Model
{
    use HasFactory;

    protected $table = 'section_rapport';
    protected $primaryKey = ['id_rapport_etudiant', 'titre_section'];
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_rapport_etudiant',
        'titre_section',
        'contenu_section',
        'ordre',
        'date_creation',
        'date_derniere_modif',
    ];

    protected $casts = [
        'ordre' => 'integer',
        'date_creation' => 'datetime',
        'date_derniere_modif' => 'datetime',
    ];

    public function rapportEtudiant()
    {
        return $this->belongsTo(RapportEtudiant::class, 'id_rapport_etudiant', 'id_rapport_etudiant');
    }
}
