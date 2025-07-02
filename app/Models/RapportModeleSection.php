<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RapportModeleSection extends Model
{
    use HasFactory;

    protected $table = 'rapport_modele_section';
    protected $primaryKey = 'id_section_modele';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_section_modele',
        'id_modele',
        'titre_section',
        'contenu_par_defaut',
        'ordre',
    ];

    protected $casts = [
        'ordre' => 'integer',
    ];

    // Relations
    public function rapportModele()
    {
        return $this->belongsTo(RapportModele::class, 'id_modele', 'id_modele');
    }
}
