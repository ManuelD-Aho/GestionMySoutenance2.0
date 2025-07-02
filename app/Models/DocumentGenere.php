<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentGenere extends Model
{
    use HasFactory;

    protected $table = 'document_genere';
    protected $primaryKey = 'id_document_genere';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_document_genere',
        'id_type_document',
        'chemin_fichier',
        'date_generation',
        'version',
        'id_entite_concernee',
        'type_entite_concernee',
        'numero_utilisateur_concerne',
    ];

    protected $casts = [
        'date_generation' => 'datetime',
        'version' => 'integer',
    ];

    public function typeDocument()
    {
        return $this->belongsTo(TypeDocumentRef::class, 'id_type_document', 'id_type_document');
    }

    public function utilisateurConcerne()
    {
        return $this->belongsTo(Utilisateur::class, 'numero_utilisateur_concerne', 'numero_utilisateur');
    }
}
