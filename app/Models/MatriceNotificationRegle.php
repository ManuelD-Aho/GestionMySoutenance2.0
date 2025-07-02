<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatriceNotificationRegle extends Model
{
    use HasFactory;

    protected $table = 'matrice_notification_regles';
    protected $primaryKey = 'id_regle';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_regle',
        'id_action_declencheur',
        'id_groupe_destinataire',
        'canal_notification',
        'est_active',
    ];

    protected $casts = [
        'est_active' => 'boolean',
    ];

    public function actionDeclencheur()
    {
        return $this->belongsTo(Action::class, 'id_action_declencheur', 'id_action');
    }

    public function groupeDestinataire()
    {
        return $this->belongsTo(GroupeUtilisateur::class, 'id_groupe_destinataire', 'id_groupe_utilisateur');
    }
}
