<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notification';
    protected $primaryKey = 'id_notification';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_notification',
        'libelle_notification',
        'contenu',
    ];

    public function receptions()
    {
        return $this->hasMany(Recevoir::class, 'id_notification', 'id_notification');
    }
}
