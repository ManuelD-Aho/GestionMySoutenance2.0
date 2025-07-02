<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Utilisateur extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'utilisateur';
    protected $primaryKey = 'numero_utilisateur';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Pas de created_at/updated_at par défaut pour cette table

    // Si vous voulez que Laravel gère les colonnes date_creation et derniere_connexion comme timestamps :
    // const CREATED_AT = 'date_creation';
    // const UPDATED_AT = 'derniere_connexion'; // Ou une autre colonne pour les mises à jour

    protected $fillable = [
        'numero_utilisateur',
        'login_utilisateur',
        'email_principal',
        'mot_de_passe',
        'date_creation',
        'derniere_connexion',
        'token_reset_mdp',
        'date_expiration_token_reset',
        'token_validation_email',
        'email_valide',
        'tentatives_connexion_echouees',
        'compte_bloque_jusqua',
        'preferences_2fa_active',
        'secret_2fa',
        'photo_profil',
        'statut_compte',
        'id_niveau_acces_donne',
        'id_groupe_utilisateur',
        'id_type_utilisateur',
    ];

    protected $hidden = [
        'mot_de_passe',
        'token_reset_mdp',
        'secret_2fa',
    ];

    protected $casts = [
        'date_creation' => 'datetime',
        'derniere_connexion' => 'datetime',
        'date_expiration_token_reset' => 'datetime',
        'email_valide' => 'boolean',
        'tentatives_connexion_echouees' => 'integer',
        'compte_bloque_jusqua' => 'datetime',
        'preferences_2fa_active' => 'boolean',
        'mot_de_passe' => 'hashed', // Laravel gère le hachage automatiquement
    ];

    // Relations
    public function niveauAccesDonne()
    {
        return $this->belongsTo(NiveauAccesDonne::class, 'id_niveau_acces_donne', 'id_niveau_acces_donne');
    }

    public function groupeUtilisateur()
    {
        return $this->belongsTo(GroupeUtilisateur::class, 'id_groupe_utilisateur', 'id_groupe_utilisateur');
    }

    public function typeUtilisateur()
    {
        return $this->belongsTo(TypeUtilisateur::class, 'id_type_utilisateur', 'id_type_utilisateur');
    }

    public function enseignant()
    {
        return $this->hasOne(Enseignant::class, 'numero_utilisateur', 'numero_utilisateur');
    }

    public function etudiant()
    {
        return $this->hasOne(Etudiant::class, 'numero_utilisateur', 'numero_utilisateur');
    }

    public function personnelAdministratif()
    {
        return $this->hasOne(PersonnelAdministratif::class, 'numero_utilisateur', 'numero_utilisateur');
    }

    public function historiqueMotsDePasse()
    {
        return $this->hasMany(HistoriqueMotDePasse::class, 'numero_utilisateur', 'numero_utilisateur');
    }

    public function delegationsDelegant()
    {
        return $this->hasMany(Delegation::class, 'id_delegant', 'numero_utilisateur');
    }

    public function delegationsDelegue()
    {
        return $this->hasMany(Delegation::class, 'id_delegue', 'numero_utilisateur');
    }

    public function documentsGeneres()
    {
        return $this->hasMany(DocumentGenere::class, 'numero_utilisateur_concerne', 'numero_utilisateur');
    }

    public function enregistrements()
    {
        return $this->hasMany(Enregistrer::class, 'numero_utilisateur', 'numero_utilisateur');
    }

    public function lecturesMessage()
    {
        return $this->hasMany(LectureMessage::class, 'numero_utilisateur', 'numero_utilisateur');
    }

    public function messagesChat()
    {
        return $this->hasMany(MessageChat::class, 'numero_utilisateur_expediteur', 'numero_utilisateur');
    }

    public function participantsConversation()
    {
        return $this->hasMany(ParticipantConversation::class, 'numero_utilisateur', 'numero_utilisateur');
    }

    public function pistes()
    {
        return $this->hasMany(Pister::class, 'numero_utilisateur', 'numero_utilisateur');
    }

    public function receptions()
    {
        return $this->hasMany(Recevoir::class, 'numero_utilisateur', 'numero_utilisateur');
    }

    public function sessions()
    {
        return $this->hasMany(Session::class, 'user_id', 'numero_utilisateur');
    }

    public function compteRendusRediges()
    {
        return $this->hasMany(CompteRendu::class, 'id_redacteur', 'numero_utilisateur');
    }
}
