<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeDocumentRef extends Model
{
    use HasFactory;

    protected $table = 'type_document_ref';
    protected $primaryKey = 'id_type_document';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at

    protected $fillable = [
        'id_type_document',
        'libelle_type_document',
        'requis_ou_non',
    ];

    protected $casts = [
        'requis_ou_non' => 'boolean',
    ];

    // Relations
    public function documentsGeneres()
    {
        return $this->hasMany(DocumentGenere::class, 'id_type_document', 'id_type_document');
    }
}
