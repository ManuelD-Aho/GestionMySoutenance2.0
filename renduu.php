<?php

// generate_models.php

$models = [
    'Acquerir' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Acquerir extends Model
{
    use HasFactory;

    protected $table = \'acquerir\';
    protected $primaryKey = [\'id_grade\', \'numero_enseignant\'];
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_grade\',
        \'numero_enseignant\',
        \'date_acquisition\',
    ];

    protected $casts = [
        \'date_acquisition\' => \'date\',
    ];

    public function grade()
    {
        return $this->belongsTo(Grade::class, \'id_grade\', \'id_grade\');
    }

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class, \'numero_enseignant\', \'numero_enseignant\');
    }
}
',
    'Action' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Action extends Model
{
    use HasFactory;

    protected $table = \'action\';
    protected $primaryKey = \'id_action\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_action\',
        \'libelle_action\',
        \'categorie_action\',
    ];

    public function enregistrements()
    {
        return $this->hasMany(Enregistrer::class, \'id_action\', \'id_action\');
    }

    public function matriceNotificationRegles()
    {
        return $this->hasMany(MatriceNotificationRegle::class, \'id_action_declencheur\', \'id_action\');
    }
}
',
    'Affecter' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Affecter extends Model
{
    use HasFactory;

    protected $table = \'affecter\';
    protected $primaryKey = [\'numero_enseignant\', \'id_rapport_etudiant\', \'id_statut_jury\'];
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'numero_enseignant\',
        \'id_rapport_etudiant\',
        \'id_statut_jury\',
        \'directeur_memoire\',
        \'date_affectation\',
    ];

    protected $casts = [
        \'directeur_memoire\' => \'boolean\',
        \'date_affectation\' => \'datetime\',
    ];

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class, \'numero_enseignant\', \'numero_enseignant\');
    }

    public function rapportEtudiant()
    {
        return $this->belongsTo(RapportEtudiant::class, \'id_rapport_etudiant\', \'id_rapport_etudiant\');
    }

    public function statutJury()
    {
        return $this->belongsTo(StatutJury::class, \'id_statut_jury\', \'id_statut_jury\');
    }
}
',
    'AnneeAcademique' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnneeAcademique extends Model
{
    use HasFactory;

    protected $table = \'annee_academique\';
    protected $primaryKey = \'id_annee_academique\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_annee_academique\',
        \'libelle_annee_academique\',
        \'date_debut\',
        \'date_fin\',
        \'est_active\',
    ];

    protected $casts = [
        \'date_debut\' => \'date\',
        \'date_fin\' => \'date\',
        \'est_active\' => \'boolean\',
    ];

    public function inscriptions()
    {
        return $this->hasMany(Inscrire::class, \'id_annee_academique\', \'id_annee_academique\');
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluer::class, \'id_annee_academique\', \'id_annee_academique\');
    }

    public function penalites()
    {
        return $this->hasMany(Penalite::class, \'id_annee_academique\', \'id_annee_academique\');
    }
}
',
    'Approuver' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Approuver extends Model
{
    use HasFactory;

    protected $table = \'approuver\';
    protected $primaryKey = [\'numero_personnel_administratif\', \'id_rapport_etudiant\'];
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'numero_personnel_administratif\',
        \'id_rapport_etudiant\',
        \'id_statut_conformite\',
        \'commentaire_conformite\',
        \'date_verification_conformite\',
    ];

    protected $casts = [
        \'date_verification_conformite\' => \'datetime\',
    ];

    public function personnelAdministratif()
    {
        return $this->belongsTo(PersonnelAdministratif::class, \'numero_personnel_administratif\', \'numero_personnel_administratif\');
    }

    public function rapportEtudiant()
    {
        return $this->belongsTo(RapportEtudiant::class, \'id_rapport_etudiant\', \'id_rapport_etudiant\');
    }

    public function statutConformiteRef()
    {
        return $this->belongsTo(StatutConformiteRef::class, \'id_statut_conformite\', \'id_statut_conformite\');
    }
}
',
    'Attribuer' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attribuer extends Model
{
    use HasFactory;

    protected $table = \'attribuer\';
    protected $primaryKey = [\'numero_enseignant\', \'id_specialite\'];
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'numero_enseignant\',
        \'id_specialite\',
    ];

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class, \'numero_enseignant\', \'numero_enseignant\');
    }

    public function specialite()
    {
        return $this->belongsTo(Specialite::class, \'id_specialite\', \'id_specialite\');
    }
}
',
    'CompteRendu' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompteRendu extends Model
{
    use HasFactory;

    protected $table = \'compte_rendu\';
    protected $primaryKey = \'id_compte_rendu\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_compte_rendu\',
        \'id_rapport_etudiant\',
        \'type_pv\',
        \'libelle_compte_rendu\',
        \'date_creation_pv\',
        \'id_statut_pv\',
        \'id_redacteur\',
        \'date_limite_approbation\',
    ];

    protected $casts = [
        \'date_creation_pv\' => \'datetime\',
        \'date_limite_approbation\' => \'datetime\',
    ];

    public function rapportEtudiant()
    {
        return $this->belongsTo(RapportEtudiant::class, \'id_rapport_etudiant\', \'id_rapport_etudiant\');
    }

    public function statutPvRef()
    {
        return $this->belongsTo(StatutPvRef::class, \'id_statut_pv\', \'id_statut_pv\');
    }

    public function redacteur()
    {
        return $this->belongsTo(Utilisateur::class, \'id_redacteur\', \'numero_utilisateur\');
    }

    public function pvSessionRapports()
    {
        return $this->hasMany(PvSessionRapport::class, \'id_compte_rendu\', \'id_compte_rendu\');
    }

    public function validationsPv()
    {
        return $this->hasMany(ValidationPv::class, \'id_compte_rendu\', \'id_compte_rendu\');
    }

    public function rendus()
    {
        return $this->hasMany(Rendre::class, \'id_compte_rendu\', \'id_compte_rendu\');
    }
}
',
    'ConformiteRapportDetail' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConformiteRapportDetail extends Model
{
    use HasFactory;

    protected $table = \'conformite_rapport_details\';
    protected $primaryKey = \'id_conformite_detail\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_conformite_detail\',
        \'id_rapport_etudiant\',
        \'id_critere\',
        \'statut_validation\',
        \'commentaire\',
        \'date_verification\',
    ];

    protected $casts = [
        \'date_verification\' => \'datetime\',
    ];

    public function rapportEtudiant()
    {
        return $this->belongsTo(RapportEtudiant::class, \'id_rapport_etudiant\', \'id_rapport_etudiant\');
    }

    public function critereConformiteRef()
    {
        return $this->belongsTo(CritereConformiteRef::class, \'id_critere\', \'id_critere\');
    }
}
',
    'Conversation' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $table = \'conversation\';
    protected $primaryKey = \'id_conversation\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_conversation\',
        \'nom_conversation\',
        \'date_creation_conv\',
        \'type_conversation\',
    ];

    protected $casts = [
        \'date_creation_conv\' => \'datetime\',
    ];

    public function messagesChat()
    {
        return $this->hasMany(MessageChat::class, \'id_conversation\', \'id_conversation\');
    }

    public function participantsConversation()
    {
        return $this->hasMany(ParticipantConversation::class, \'id_conversation\', \'id_conversation\');
    }
}
',
    'CritereConformiteRef' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CritereConformiteRef extends Model
{
    use HasFactory;

    protected $table = \'critere_conformite_ref\';
    protected $primaryKey = \'id_critere\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_critere\',
        \'libelle_critere\',
        \'description\',
        \'est_actif\',
    ];

    protected $casts = [
        \'est_actif\' => \'boolean\',
    ];

    public function conformiteRapportDetails()
    {
        return $this->hasMany(ConformiteRapportDetail::class, \'id_critere\', \'id_critere\');
    }
}
',
    'DecisionPassageRef' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DecisionPassageRef extends Model
{
    use HasFactory;

    protected $table = \'decision_passage_ref\';
    protected $primaryKey = \'id_decision_passage\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_decision_passage\',
        \'libelle_decision_passage\',
    ];

    public function inscriptions()
    {
        return $this->hasMany(Inscrire::class, \'id_decision_passage\', \'id_decision_passage\');
    }
}
',
    'DecisionValidationPvRef' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DecisionValidationPvRef extends Model
{
    use HasFactory;

    protected $table = \'decision_validation_pv_ref\';
    protected $primaryKey = \'id_decision_validation_pv\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_decision_validation_pv\',
        \'libelle_decision_validation_pv\',
    ];

    public function validationPvs()
    {
        return $this->hasMany(ValidationPv::class, \'id_decision_validation_pv\', \'id_decision_validation_pv\');
    }
}
',
    'DecisionVoteRef' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DecisionVoteRef extends Model
{
    use HasFactory;

    protected $table = \'decision_vote_ref\';
    protected $primaryKey = \'id_decision_vote\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_decision_vote\',
        \'libelle_decision_vote\',
    ];

    public function voteCommissions()
    {
        return $this->hasMany(VoteCommission::class, \'id_decision_vote\', \'id_decision_vote\');
    }
}
',
    'Delegation' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delegation extends Model
{
    use HasFactory;

    protected $table = \'delegation\';
    protected $primaryKey = \'id_delegation\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_delegation\',
        \'id_delegant\',
        \'id_delegue\',
        \'id_traitement\',
        \'date_debut\',
        \'date_fin\',
        \'statut\',
        \'contexte_id\',
        \'contexte_type\',
    ];

    protected $casts = [
        \'date_debut\' => \'datetime\',
        \'date_fin\' => \'datetime\',
    ];

    public function delegant()
    {
        return $this->belongsTo(Utilisateur::class, \'id_delegant\', \'numero_utilisateur\');
    }

    public function delegue()
    {
        return $this->belongsTo(Utilisateur::class, \'id_delegue\', \'numero_utilisateur\');
    }

    public function traitement()
    {
        return $this->belongsTo(Traitement::class, \'id_traitement\', \'id_traitement\');
    }
}
',
    'DocumentGenere' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentGenere extends Model
{
    use HasFactory;

    protected $table = \'document_genere\';
    protected $primaryKey = \'id_document_genere\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_document_genere\',
        \'id_type_document\',
        \'chemin_fichier\',
        \'date_generation\',
        \'version\',
        \'id_entite_concernee\',
        \'type_entite_concernee\',
        \'numero_utilisateur_concerne\',
    ];

    protected $casts = [
        \'date_generation\' => \'datetime\',
        \'version\' => \'integer\',
    ];

    public function typeDocument()
    {
        return $this->belongsTo(TypeDocumentRef::class, \'id_type_document\', \'id_type_document\');
    }

    public function utilisateurConcerne()
    {
        return $this->belongsTo(Utilisateur::class, \'numero_utilisateur_concerne\', \'numero_utilisateur\');
    }
}
',
    'Ecue' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ecue extends Model
{
    use HasFactory;

    protected $table = \'ecue\';
    protected $primaryKey = \'id_ecue\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_ecue\',
        \'libelle_ecue\',
        \'id_ue\',
        \'credits_ecue\',
    ];

    protected $casts = [
        \'credits_ecue\' => \'integer\',
    ];

    public function ue()
    {
        return $this->belongsTo(Ue::class, \'id_ue\', \'id_ue\');
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluer::class, \'id_ecue\', \'id_ecue\');
    }
}
',
    'Enregistrer' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enregistrer extends Model
{
    use HasFactory;

    protected $table = \'enregistrer\';
    protected $primaryKey = \'id_enregistrement\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_enregistrement\',
        \'numero_utilisateur\',
        \'id_action\',
        \'date_action\',
        \'adresse_ip\',
        \'user_agent\',
        \'id_entite_concernee\',
        \'type_entite_concernee\',
        \'details_action\',
        \'session_id_utilisateur\',
    ];

    protected $casts = [
        \'date_action\' => \'datetime\',
        \'details_action\' => \'array\',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, \'numero_utilisateur\', \'numero_utilisateur\');
    }

    public function action()
    {
        return $this->belongsTo(Action::class, \'id_action\', \'id_action\');
    }
}
',
    'Enseignant' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enseignant extends Model
{
    use HasFactory;

    protected $table = \'enseignant\';
    protected $primaryKey = \'numero_enseignant\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'numero_enseignant\',
        \'nom\',
        \'prenom\',
        \'telephone_professionnel\',
        \'email_professionnel\',
        \'numero_utilisateur\',
        \'date_naissance\',
        \'lieu_naissance\',
        \'pays_naissance\',
        \'nationalite\',
        \'sexe\',
        \'adresse_postale\',
        \'ville\',
        \'code_postal\',
        \'telephone_personnel\',
        \'email_personnel_secondaire\',
    ];

    protected $casts = [
        \'date_naissance\' => \'date\',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, \'numero_utilisateur\', \'numero_utilisateur\');
    }

    public function gradesAcquis()
    {
        return $this->hasMany(Acquerir::class, \'numero_enseignant\', \'numero_enseignant\');
    }

    public function fonctionsOccupees()
    {
        return $this->hasMany(Occuper::class, \'numero_enseignant\', \'numero_enseignant\');
    }

    public function specialitesAttribuees()
    {
        return $this->hasMany(Attribuer::class, \'numero_enseignant\', \'numero_enseignant\');
    }

    public function affectations()
    {
        return $this->hasMany(Affecter::class, \'numero_enseignant\', \'numero_enseignant\');
    }

    public function rendus()
    {
        return $this->hasMany(Rendre::class, \'numero_enseignant\', \'numero_enseignant\');
    }

    public function validationPvs()
    {
        return $this->hasMany(ValidationPv::class, \'numero_enseignant\', \'numero_enseignant\');
    }

    public function voteCommissions()
    {
        return $this->hasMany(VoteCommission::class, \'numero_enseignant\', \'numero_enseignant\');
    }

    public function sessionsValidation()
    {
        return $this->hasMany(SessionValidation::class, \'id_president_session\', \'numero_enseignant\');
    }
}
',
    'Entreprise' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entreprise extends Model
{
    use HasFactory;

    protected $table = \'entreprise\';
    protected $primaryKey = \'id_entreprise\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_entreprise\',
        \'libelle_entreprise\',
        \'secteur_activite\',
        \'adresse_entreprise\',
        \'contact_nom\',
        \'contact_email\',
        \'contact_telephone\',
    ];

    public function faireStages()
    {
        return $this->hasMany(FaireStage::class, \'id_entreprise\', \'id_entreprise\');
    }
}
',
    'Etudiant' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Etudiant extends Model
{
    use HasFactory;

    protected $table = \'etudiant\';
    protected $primaryKey = \'numero_carte_etudiant\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'numero_carte_etudiant\',
        \'nom\',
        \'prenom\',
        \'date_naissance\',
        \'lieu_naissance\',
        \'pays_naissance\',
        \'nationalite\',
        \'sexe\',
        \'adresse_postale\',
        \'ville\',
        \'code_postal\',
        \'telephone\',
        \'email_contact_secondaire\',
        \'numero_utilisateur\',
        \'contact_urgence_nom\',
        \'contact_urgence_telephone\',
        \'contact_urgence_relation\',
    ];

    protected $casts = [
        \'date_naissance\' => \'date\',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, \'numero_utilisateur\', \'numero_utilisateur\');
    }

    public function inscriptions()
    {
        return $this->hasMany(Inscrire::class, \'numero_carte_etudiant\', \'numero_carte_etudiant\');
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluer::class, \'numero_carte_etudiant\', \'numero_carte_etudiant\');
    }

    public function faireStages()
    {
        return $this->hasMany(FaireStage::class, \'numero_carte_etudiant\', \'numero_carte_etudiant\');
    }

    public function penalites()
    {
        return $this->hasMany(Penalite::class, \'numero_carte_etudiant\', \'numero_carte_etudiant\');
    }

    public function reclamations()
    {
        return $this->hasMany(Reclamation::class, \'numero_carte_etudiant\', \'numero_carte_etudiant\');
    }

    public function rapportsEtudiant()
    {
        return $this->hasMany(RapportEtudiant::class, \'numero_carte_etudiant\', \'numero_carte_etudiant\');
    }
}
',
    'Evaluer' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluer extends Model
{
    use HasFactory;

    protected $table = \'evaluer\';
    protected $primaryKey = [\'numero_carte_etudiant\', \'id_ecue\', \'id_annee_academique\'];
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'numero_carte_etudiant\',
        \'id_ecue\',
        \'id_annee_academique\',
        \'date_evaluation\',
        \'note\',
    ];

    protected $casts = [
        \'date_evaluation\' => \'datetime\',
        \'note\' => \'decimal:2\',
    ];

    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class, \'numero_carte_etudiant\', \'numero_carte_etudiant\');
    }

    public function ecue()
    {
        return $this->belongsTo(Ecue::class, \'id_ecue\', \'id_ecue\');
    }

    public function anneeAcademique()
    {
        return $this->belongsTo(AnneeAcademique::class, \'id_annee_academique\', \'id_annee_academique\');
    }
}
',
    'FaireStage' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaireStage extends Model
{
    use HasFactory;

    protected $table = \'faire_stage\';
    protected $primaryKey = [\'id_entreprise\', \'numero_carte_etudiant\'];
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_entreprise\',
        \'numero_carte_etudiant\',
        \'date_debut_stage\',
        \'date_fin_stage\',
        \'sujet_stage\',
        \'nom_tuteur_entreprise\',
    ];

    protected $casts = [
        \'date_debut_stage\' => \'date\',
        \'date_fin_stage\' => \'date\',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class, \'id_entreprise\', \'id_entreprise\');
    }

    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class, \'numero_carte_etudiant\', \'numero_carte_etudiant\');
    }
}
',
    'Fonction' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fonction extends Model
{
    use HasFactory;

    protected $table = \'fonction\';
    protected $primaryKey = \'id_fonction\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_fonction\',
        \'libelle_fonction\',
    ];

    public function occupations()
    {
        return $this->hasMany(Occuper::class, \'id_fonction\', \'id_fonction\');
    }
}
',
    'Grade' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    use HasFactory;

    protected $table = \'grade\';
    protected $primaryKey = \'id_grade\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_grade\',
        \'libelle_grade\',
        \'abreviation_grade\',
    ];

    public function acquisitions()
    {
        return $this->hasMany(Acquerir::class, \'id_grade\', \'id_grade\');
    }
}
',
    'GroupeUtilisateur' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupeUtilisateur extends Model
{
    use HasFactory;

    protected $table = \'groupe_utilisateur\';
    protected $primaryKey = \'id_groupe_utilisateur\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_groupe_utilisateur\',
        \'libelle_groupe_utilisateur\',
    ];

    public function utilisateurs()
    {
        return $this->hasMany(Utilisateur::class, \'id_groupe_utilisateur\', \'id_groupe_utilisateur\');
    }

    public function rattachements()
    {
        return $this->hasMany(Rattacher::class, \'id_groupe_utilisateur\', \'id_groupe_utilisateur\');
    }

    public function matriceNotificationRegles()
    {
        return $this->hasMany(MatriceNotificationRegle::class, \'id_groupe_destinataire\', \'id_groupe_utilisateur\');
    }
}
',
    'HistoriqueMotDePasse' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoriqueMotDePasse extends Model
{
    use HasFactory;

    protected $table = \'historique_mot_de_passe\';
    protected $primaryKey = \'id_historique_mdp\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_historique_mdp\',
        \'numero_utilisateur\',
        \'mot_de_passe_hache\',
        \'date_changement\',
    ];

    protected $casts = [
        \'date_changement\' => \'datetime\',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, \'numero_utilisateur\', \'numero_utilisateur\');
    }
}
',
    'Inscrire' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inscrire extends Model
{
    use HasFactory;

    protected $table = \'inscrire\';
    protected $primaryKey = [\'numero_carte_etudiant\', \'id_niveau_etude\', \'id_annee_academique\'];
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'numero_carte_etudiant\',
        \'id_niveau_etude\',
        \'id_annee_academique\',
        \'montant_inscription\',
        \'date_inscription\',
        \'id_statut_paiement\',
        \'date_paiement\',
        \'numero_recu_paiement\',
        \'id_decision_passage\',
    ];

    protected $casts = [
        \'montant_inscription\' => \'decimal:2\',
        \'date_inscription\' => \'datetime\',
        \'date_paiement\' => \'datetime\',
    ];

    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class, \'numero_carte_etudiant\', \'numero_carte_etudiant\');
    }

    public function niveauEtude()
    {
        return $this->belongsTo(NiveauEtude::class, \'id_niveau_etude\', \'id_niveau_etude\');
    }

    public function anneeAcademique()
    {
        return $this->belongsTo(AnneeAcademique::class, \'id_annee_academique\', \'id_annee_academique\');
    }

    public function statutPaiementRef()
    {
        return $this->belongsTo(StatutPaiementRef::class, \'id_statut_paiement\', \'id_statut_paiement\');
    }

    public function decisionPassageRef()
    {
        return $this->belongsTo(DecisionPassageRef::class, \'id_decision_passage\', \'id_decision_passage\');
    }
}
',
    'LectureMessage' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LectureMessage extends Model
{
    use HasFactory;

    protected $table = \'lecture_message\';
    protected $primaryKey = [\'id_message_chat\', \'numero_utilisateur\'];
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_message_chat\',
        \'numero_utilisateur\',
        \'date_lecture\',
    ];

    protected $casts = [
        \'date_lecture\' => \'datetime\',
    ];

    public function messageChat()
    {
        return $this->belongsTo(MessageChat::class, \'id_message_chat\', \'id_message_chat\');
    }

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, \'numero_utilisateur\', \'numero_utilisateur\');
    }
}
',
    'MatriceNotificationRegle' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatriceNotificationRegle extends Model
{
    use HasFactory;

    protected $table = \'matrice_notification_regles\';
    protected $primaryKey = \'id_regle\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_regle\',
        \'id_action_declencheur\',
        \'id_groupe_destinataire\',
        \'canal_notification\',
        \'est_active\',
    ];

    protected $casts = [
        \'est_active\' => \'boolean\',
    ];

    public function actionDeclencheur()
    {
        return $this->belongsTo(Action::class, \'id_action_declencheur\', \'id_action\');
    }

    public function groupeDestinataire()
    {
        return $this->belongsTo(GroupeUtilisateur::class, \'id_groupe_destinataire\', \'id_groupe_utilisateur\');
    }
}
',
    'MessageChat' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageChat extends Model
{
    use HasFactory;

    protected $table = \'message_chat\';
    protected $primaryKey = \'id_message_chat\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_message_chat\',
        \'id_conversation\',
        \'numero_utilisateur_expediteur\',
        \'contenu_message\',
        \'date_envoi\',
    ];

    protected $casts = [
        \'date_envoi\' => \'datetime\',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, \'id_conversation\', \'id_conversation\');
    }

    public function utilisateurExpediteur()
    {
        return $this->belongsTo(Utilisateur::class, \'numero_utilisateur_expediteur\', \'numero_utilisateur\');
    }

    public function lecturesMessage()
    {
        return $this->hasMany(LectureMessage::class, \'id_message_chat\', \'id_message_chat\');
    }
}
',
    'NiveauAccesDonne' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NiveauAccesDonne extends Model
{
    use HasFactory;

    protected $table = \'niveau_acces_donne\';
    protected $primaryKey = \'id_niveau_acces_donne\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_niveau_acces_donne\',
        \'libelle_niveau_acces_donne\',
    ];

    public function utilisateurs()
    {
        return $this->hasMany(Utilisateur::class, \'id_niveau_acces_donne\', \'id_niveau_acces_donne\');
    }
}
',
    'NiveauEtude' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NiveauEtude extends Model
{
    use HasFactory;

    protected $table = \'niveau_etude\';
    protected $primaryKey = \'id_niveau_etude\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_niveau_etude\',
        \'libelle_niveau_etude\',
    ];

    public function inscriptions()
    {
        return $this->hasMany(Inscrire::class, \'id_niveau_etude\', \'id_niveau_etude\');
    }

    public function rapportModeleAssignations()
    {
        return $this->hasMany(RapportModeleAssignation::class, \'id_niveau_etude\', \'id_niveau_etude\');
    }
}
',
    'Notification' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = \'notification\';
    protected $primaryKey = \'id_notification\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_notification\',
        \'libelle_notification\',
        \'contenu\',
    ];

    public function receptions()
    {
        return $this->hasMany(Recevoir::class, \'id_notification\', \'id_notification\');
    }
}
',
    'Occuper' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Occuper extends Model
{
    use HasFactory;

    protected $table = \'occuper\';
    protected $primaryKey = [\'id_fonction\', \'numero_enseignant\'];
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_fonction\',
        \'numero_enseignant\',
        \'date_debut_occupation\',
        \'date_fin_occupation\',
    ];

    protected $casts = [
        \'date_debut_occupation\' => \'date\',
        \'date_fin_occupation\' => \'date\',
    ];

    public function fonction()
    {
        return $this->belongsTo(Fonction::class, \'id_fonction\', \'id_fonction\');
    }

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class, \'numero_enseignant\', \'numero_enseignant\');
    }
}
',
    'ParametreSysteme' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParametreSysteme extends Model
{
    use HasFactory;

    protected $table = \'parametres_systeme\';
    protected $primaryKey = \'cle\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'cle\',
        \'valeur\',
        \'description\',
        \'type\',
    ];
}
',
    'ParticipantConversation' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParticipantConversation extends Model
{
    use HasFactory;

    protected $table = \'participant_conversation\';
    protected $primaryKey = [\'id_conversation\', \'numero_utilisateur\'];
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_conversation\',
        \'numero_utilisateur\',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, \'id_conversation\', \'id_conversation\');
    }

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, \'numero_utilisateur\', \'numero_utilisateur\');
    }
}
',
    'Penalite' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penalite extends Model
{
    use HasFactory;

    protected $table = \'penalite\';
    protected $primaryKey = \'id_penalite\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_penalite\',
        \'numero_carte_etudiant\',
        \'id_annee_academique\',
        \'type_penalite\',
        \'montant_du\',
        \'motif\',
        \'id_statut_penalite\',
        \'date_creation\',
        \'date_regularisation\',
        \'numero_personnel_traitant\',
    ];

    protected $casts = [
        \'montant_du\' => \'decimal:2\',
        \'date_creation\' => \'datetime\',
        \'date_regularisation\' => \'datetime\',
    ];

    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class, \'numero_carte_etudiant\', \'numero_carte_etudiant\');
    }

    public function anneeAcademique()
    {
        return $this->belongsTo(AnneeAcademique::class, \'id_annee_academique\', \'id_annee_academique\');
    }

    public function statutPenaliteRef()
    {
        return $this->belongsTo(StatutPenaliteRef::class, \'id_statut_penalite\', \'id_statut_penalite\');
    }

    public function personnelTraitant()
    {
        return $this->belongsTo(PersonnelAdministratif::class, \'numero_personnel_traitant\', \'numero_personnel_administratif\');
    }
}
',
    'PersonnelAdministratif' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonnelAdministratif extends Model
{
    use HasFactory;

    protected $table = \'personnel_administratif\';
    protected $primaryKey = \'numero_personnel_administratif\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'numero_personnel_administratif\',
        \'nom\',
        \'prenom\',
        \'telephone_professionnel\',
        \'email_professionnel\',
        \'date_affectation_service\',
        \'responsabilites_cles\',
        \'numero_utilisateur\',
        \'date_naissance\',
        \'lieu_naissance\',
        \'pays_naissance\',
        \'nationalite\',
        \'sexe\',
        \'adresse_postale\',
        \'ville\',
        \'code_postal\',
        \'telephone_personnel\',
        \'email_personnel_secondaire\',
    ];

    protected $casts = [
        \'date_affectation_service\' => \'date\',
        \'date_naissance\' => \'date\',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, \'numero_utilisateur\', \'numero_utilisateur\');
    }

    public function approbations()
    {
        return $this->hasMany(Approuver::class, \'numero_personnel_administratif\', \'numero_personnel_administratif\');
    }

    public function penalites()
    {
        return $this->hasMany(Penalite::class, \'numero_personnel_traitant\', \'numero_personnel_administratif\');
    }

    public function reclamations()
    {
        return $this->hasMany(Reclamation::class, \'numero_personnel_traitant\', \'numero_personnel_administratif\');
    }
}
',
    'Pister' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pister extends Model
{
    use HasFactory;

    protected $table = \'pister\';
    protected $primaryKey = \'id_piste\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_piste\',
        \'numero_utilisateur\',
        \'id_traitement\',
        \'date_pister\',
        \'acceder\',
    ];

    protected $casts = [
        \'date_pister\' => \'datetime\',
        \'acceder\' => \'boolean\',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, \'numero_utilisateur\', \'numero_utilisateur\');
    }

    public function traitement()
    {
        return $this->belongsTo(Traitement::class, \'id_traitement\', \'id_traitement\');
    }
}
',
    'PvSessionRapport' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PvSessionRapport extends Model
{
    use HasFactory;

    protected $table = \'pv_session_rapport\';
    protected $primaryKey = [\'id_compte_rendu\', \'id_rapport_etudiant\'];
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_compte_rendu\',
        \'id_rapport_etudiant\',
    ];

    public function compteRendu()
    {
        return $this->belongsTo(CompteRendu::class, \'id_compte_rendu\', \'id_compte_rendu\');
    }

    public function rapportEtudiant()
    {
        return $this->belongsTo(RapportEtudiant::class, \'id_rapport_etudiant\', \'id_rapport_etudiant\');
    }
}
',
    'QueueJob' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueueJob extends Model
{
    use HasFactory;

    protected $table = \'queue_jobs\';
    protected $primaryKey = \'id\';
    public $incrementing = true;
    protected $keyType = \'integer\';
    public $timestamps = false; // Using custom created_at, started_at, completed_at

    protected $fillable = [
        \'job_name\',
        \'payload\',
        \'status\',
        \'attempts\',
        \'created_at\',
        \'started_at\',
        \'completed_at\',
        \'error_message\',
    ];

    protected $casts = [
        \'payload\' => \'array\', // Assuming payload is JSON
        \'attempts\' => \'integer\',
        \'created_at\' => \'datetime\',
        \'started_at\' => \'datetime\',
        \'completed_at\' => \'datetime\',
    ];
}
',
    'RapportEtudiant' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RapportEtudiant extends Model
{
    use HasFactory;

    protected $table = \'rapport_etudiant\';
    protected $primaryKey = \'id_rapport_etudiant\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_rapport_etudiant\',
        \'libelle_rapport_etudiant\',
        \'theme\',
        \'resume\',
        \'numero_attestation_stage\',
        \'numero_carte_etudiant\',
        \'nombre_pages\',
        \'id_statut_rapport\',
        \'date_soumission\',
        \'date_derniere_modif\',
    ];

    protected $casts = [
        \'nombre_pages\' => \'integer\',
        \'date_soumission\' => \'datetime\',
        \'date_derniere_modif\' => \'datetime\',
    ];

    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class, \'numero_carte_etudiant\', \'numero_carte_etudiant\');
    }

    public function statutRapportRef()
    {
        return $this->belongsTo(StatutRapportRef::class, \'id_statut_rapport\', \'id_statut_rapport\');
    }

    public function affectations()
    {
        return $this->hasMany(Affecter::class, \'id_rapport_etudiant\', \'id_rapport_etudiant\');
    }

    public function approbations()
    {
        return $this->hasMany(Approuver::class, \'id_rapport_etudiant\', \'id_rapport_etudiant\');
    }

    public function compteRendus()
    {
        return $this->hasMany(CompteRendu::class, \'id_rapport_etudiant\', \'id_rapport_etudiant\');
    }

    public function conformiteRapportDetails()
    {
        return $this->hasMany(ConformiteRapportDetail::class, \'id_rapport_etudiant\', \'id_rapport_etudiant\');
    }

    public function pvSessionRapports()
    {
        return $this->hasMany(PvSessionRapport::class, \'id_rapport_etudiant\', \'id_rapport_etudiant\');
    }

    public function sectionRapports()
    {
        return $this->hasMany(SectionRapport::class, \'id_rapport_etudiant\', \'id_rapport_etudiant\');
    }

    public function sessionRapports()
    {
        return $this->hasMany(SessionRapport::class, \'id_rapport_etudiant\', \'id_rapport_etudiant\');
    }

    public function voteCommissions()
    {
        return $this->hasMany(VoteCommission::class, \'id_rapport_etudiant\', \'id_rapport_etudiant\');
    }
}
',
    'RapportModele' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RapportModele extends Model
{
    use HasFactory;

    protected $table = \'rapport_modele\';
    protected $primaryKey = \'id_modele\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_modele\',
        \'nom_modele\',
        \'description\',
        \'version\',
        \'statut\',
    ];

    public function assignations()
    {
        return $this->hasMany(RapportModeleAssignation::class, \'id_modele\', \'id_modele\');
    }

    public function sections()
    {
        return $this->hasMany(RapportModeleSection::class, \'id_modele\', \'id_modele\');
    }
}
',
    'RapportModeleAssignation' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RapportModeleAssignation extends Model
{
    use HasFactory;

    protected $table = \'rapport_modele_assignation\';
    protected $primaryKey = [\'id_modele\', \'id_niveau_etude\'];
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_modele\',
        \'id_niveau_etude\',
    ];

    public function rapportModele()
    {
        return $this->belongsTo(RapportModele::class, \'id_modele\', \'id_modele\');
    }

    public function niveauEtude()
    {
        return $this->belongsTo(NiveauEtude::class, \'id_niveau_etude\', \'id_niveau_etude\');
    }
}
',
    'RapportModeleSection' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RapportModeleSection extends Model
{
    use HasFactory;

    protected $table = \'rapport_modele_section\';
    protected $primaryKey = \'id_section_modele\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_section_modele\',
        \'id_modele\',
        \'titre_section\',
        \'contenu_par_defaut\',
        \'ordre\',
    ];

    protected $casts = [
        \'ordre\' => \'integer\',
    ];

    public function rapportModele()
    {
        return $this->belongsTo(RapportModele::class, \'id_modele\', \'id_modele\');
    }
}
',
    'Rattacher' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rattacher extends Model
{
    use HasFactory;

    protected $table = \'rattacher\';
    protected $primaryKey = [\'id_groupe_utilisateur\', \'id_traitement\'];
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_groupe_utilisateur\',
        \'id_traitement\',
    ];

    public function groupeUtilisateur()
    {
        return $this->belongsTo(GroupeUtilisateur::class, \'id_groupe_utilisateur\', \'id_groupe_utilisateur\');
    }

    public function traitement()
    {
        return $this->belongsTo(Traitement::class, \'id_traitement\', \'id_traitement\');
    }
}
',
    'Recevoir' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recevoir extends Model
{
    use HasFactory;

    protected $table = \'recevoir\';
    protected $primaryKey = \'id_reception\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_reception\',
        \'numero_utilisateur\',
        \'id_notification\',
        \'date_reception\',
        \'lue\',
        \'date_lecture\',
    ];

    protected $casts = [
        \'date_reception\' => \'datetime\',
        \'lue\' => \'boolean\',
        \'date_lecture\' => \'datetime\',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, \'numero_utilisateur\', \'numero_utilisateur\');
    }

    public function notification()
    {
        return $this->belongsTo(Notification::class, \'id_notification\', \'id_notification\');
    }
}
',
    'Reclamation' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reclamation extends Model
{
    use HasFactory;

    protected $table = \'reclamation\';
    protected $primaryKey = \'id_reclamation\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_reclamation\',
        \'numero_carte_etudiant\',
        \'sujet_reclamation\',
        \'description_reclamation\',
        \'date_soumission\',
        \'id_statut_reclamation\',
        \'reponse_reclamation\',
        \'date_reponse\',
        \'numero_personnel_traitant\',
    ];

    protected $casts = [
        \'date_soumission\' => \'datetime\',
        \'date_reponse\' => \'datetime\',
    ];

    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class, \'numero_carte_etudiant\', \'numero_carte_etudiant\');
    }

    public function statutReclamationRef()
    {
        return $this->belongsTo(StatutReclamationRef::class, \'id_statut_reclamation\', \'id_statut_reclamation\');
    }

    public function personnelTraitant()
    {
        return $this->belongsTo(PersonnelAdministratif::class, \'numero_personnel_traitant\', \'numero_personnel_administratif\');
    }
}
',
    'Rendre' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rendre extends Model
{
    use HasFactory;

    protected $table = \'rendre\';
    protected $primaryKey = [\'numero_enseignant\', \'id_compte_rendu\'];
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'numero_enseignant\',
        \'id_compte_rendu\',
        \'date_action_sur_pv\',
    ];

    protected $casts = [
        \'date_action_sur_pv\' => \'datetime\',
    ];

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class, \'numero_enseignant\', \'numero_enseignant\');
    }

    public function compteRendu()
    {
        return $this->belongsTo(CompteRendu::class, \'id_compte_rendu\', \'id_compte_rendu\');
    }
}
',
    'SectionRapport' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SectionRapport extends Model
{
    use HasFactory;

    protected $table = \'section_rapport\';
    protected $primaryKey = [\'id_rapport_etudiant\', \'titre_section\'];
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_rapport_etudiant\',
        \'titre_section\',
        \'contenu_section\',
        \'ordre\',
        \'date_creation\',
        \'date_derniere_modif\',
    ];

    protected $casts = [
        \'ordre\' => \'integer\',
        \'date_creation\' => \'datetime\',
        \'date_derniere_modif\' => \'datetime\',
    ];

    public function rapportEtudiant()
    {
        return $this->belongsTo(RapportEtudiant::class, \'id_rapport_etudiant\', \'id_rapport_etudiant\');
    }
}
',
    'Sequence' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    use HasFactory;

    protected $table = \'sequences\';
    protected $primaryKey = [\'nom_sequence\', \'annee\'];
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'nom_sequence\',
        \'annee\',
        \'valeur_actuelle\',
    ];

    protected $casts = [
        \'annee\' => \'integer\',
        \'valeur_actuelle\' => \'integer\',
    ];
}
',
    'Session' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    protected $primaryKey = \'session_id\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'session_id\', \'session_data\', \'session_last_activity\', \'session_lifetime\', \'user_id\'
    ];

    protected $casts = [
        \'session_last_activity\' => \'integer\',
        \'session_lifetime\' => \'integer\',
        \'session_data\' => \'string\',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, \'user_id\', \'numero_utilisateur\');
    }
}
',
    'SessionRapport' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionRapport extends Model
{
    use HasFactory;

    protected $table = \'session_rapport\';
    protected $primaryKey = [\'id_session\', \'id_rapport_etudiant\'];
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_session\',
        \'id_rapport_etudiant\',
    ];

    public function sessionValidation()
    {
        return $this->belongsTo(SessionValidation::class, \'id_session\', \'id_session\');
    }

    public function rapportEtudiant()
    {
        return $this->belongsTo(RapportEtudiant::class, \'id_rapport_etudiant\', \'id_rapport_etudiant\');
    }
}
',
    'SessionValidation' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionValidation extends Model
{
    use HasFactory;

    protected $table = \'session_validation\';
    protected $primaryKey = \'id_session\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_session\',
        \'nom_session\',
        \'date_debut_session\',
        \'date_fin_prevue\',
        \'date_creation\',
        \'id_president_session\',
        \'mode_session\',
        \'statut_session\',
        \'nombre_votants_requis\',
    ];

    protected $casts = [
        \'date_debut_session\' => \'datetime\',
        \'date_fin_prevue\' => \'datetime\',
        \'date_creation\' => \'datetime\',
        \'nombre_votants_requis\' => \'integer\',
    ];

    public function presidentSession()
    {
        return $this->belongsTo(Enseignant::class, \'id_president_session\', \'numero_enseignant\');
    }

    public function sessionRapports()
    {
        return $this->hasMany(SessionRapport::class, \'id_session\', \'id_session\');
    }

    public function voteCommissions()
    {
        return $this->hasMany(VoteCommission::class, \'id_session\', \'id_session\');
    }
}
',
    'Specialite' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Specialite extends Model
{
    use HasFactory;

    protected $table = \'specialite\';
    protected $primaryKey = \'id_specialite\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_specialite\',
        \'libelle_specialite\',
        \'numero_enseignant_specialite\',
    ];

    public function enseignantSpecialite()
    {
        return $this->belongsTo(Enseignant::class, \'numero_enseignant_specialite\', \'numero_enseignant\');
    }

    public function attributions()
    {
        return $this->hasMany(Attribuer::class, \'id_specialite\', \'id_specialite\');
    }
}
',
    'StatutConformiteRef' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatutConformiteRef extends Model
{
    use HasFactory;

    protected $table = \'statut_conformite_ref\';
    protected $primaryKey = \'id_statut_conformite\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_statut_conformite\',
        \'libelle_statut_conformite\',
    ];

    public function approbations()
    {
        return $this->hasMany(Approuver::class, \'id_statut_conformite\', \'id_statut_conformite\');
    }
}
',
    'StatutJury' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatutJury extends Model
{
    use HasFactory;

    protected $table = \'statut_jury\';
    protected $primaryKey = \'id_statut_jury\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_statut_jury\',
        \'libelle_statut_jury\',
    ];

    public function affectations()
    {
        return $this->hasMany(Affecter::class, \'id_statut_jury\', \'id_statut_jury\');
    }
}
',
    'StatutPaiementRef' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatutPaiementRef extends Model
{
    use HasFactory;

    protected $table = \'statut_paiement_ref\';
    protected $primaryKey = \'id_statut_paiement\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_statut_paiement\',
        \'libelle_statut_paiement\',
    ];

    public function inscriptions()
    {
        return $this->hasMany(Inscrire::class, \'id_statut_paiement\', \'id_statut_paiement\');
    }
}
',
    'StatutPenaliteRef' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatutPenaliteRef extends Model
{
    use HasFactory;

    protected $table = \'statut_penalite_ref\';
    protected $primaryKey = \'id_statut_penalite\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_statut_penalite\',
        \'libelle_statut_penalite\',
    ];

    public function penalites()
    {
        return $this->hasMany(Penalite::class, \'id_statut_penalite\', \'id_statut_penalite\');
    }
}
',
    'StatutPvRef' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatutPvRef extends Model
{
    use HasFactory;

    protected $table = \'statut_pv_ref\';
    protected $primaryKey = \'id_statut_pv\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_statut_pv\',
        \'libelle_statut_pv\',
    ];

    public function compteRendus()
    {
        return $this->hasMany(CompteRendu::class, \'id_statut_pv\', \'id_statut_pv\');
    }
}
',
    'StatutRapportRef' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatutRapportRef extends Model
{
    use HasFactory;

    protected $table = \'statut_rapport_ref\';
    protected $primaryKey = \'id_statut_rapport\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_statut_rapport\',
        \'libelle_statut_rapport\',
        \'etape_workflow\',
    ];

    protected $casts = [
        \'etape_workflow\' => \'integer\',
    ];

    public function rapportsEtudiant()
    {
        return $this->hasMany(RapportEtudiant::class, \'id_statut_rapport\', \'id_statut_rapport\');
    }
}
',
    'StatutReclamationRef' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatutReclamationRef extends Model
{
    use HasFactory;

    protected $table = \'statut_reclamation_ref\';
    protected $primaryKey = \'id_statut_reclamation\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_statut_reclamation\',
        \'libelle_statut_reclamation\',
    ];

    public function reclamations()
    {
        return $this->hasMany(Reclamation::class, \'id_statut_reclamation\', \'id_statut_reclamation\');
    }
}
',
    'Traitement' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Traitement extends Model
{
    use HasFactory;

    protected $table = \'traitement\';
    protected $primaryKey = \'id_traitement\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_traitement\',
        \'libelle_traitement\',
        \'id_parent_traitement\',
        \'icone_class\',
        \'url_associee\',
        \'ordre_affichage\',
    ];

    protected $casts = [
        \'ordre_affichage\' => \'integer\',
    ];

    public function parentTraitement()
    {
        return $this->belongsTo(Traitement::class, \'id_parent_traitement\', \'id_traitement\');
    }

    public function enfants()
    {
        return $this->hasMany(Traitement::class, \'id_parent_traitement\', \'id_traitement\');
    }

    public function rattachements()
    {
        return $this->hasMany(Rattacher::class, \'id_traitement\', \'id_traitement\');
    }

    public function delegations()
    {
        return $this->hasMany(Delegation::class, \'id_traitement\', \'id_traitement\');
    }

    public function pistes()
    {
        return $this->hasMany(Pister::class, \'id_traitement\', \'id_traitement\');
    }
}
',
    'TypeDocumentRef' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeDocumentRef extends Model
{
    use HasFactory;

    protected $table = \'type_document_ref\';
    protected $primaryKey = \'id_type_document\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_type_document\',
        \'libelle_type_document\',
        \'requis_ou_non\',
    ];

    protected $casts = [
        \'requis_ou_non\' => \'boolean\',
    ];

    public function documentsGeneres()
    {
        return $this->hasMany(DocumentGenere::class, \'id_type_document\', \'id_type_document\');
    }
}
',
    'TypeUtilisateur' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeUtilisateur extends Model
{
    use HasFactory;

    protected $table = \'type_utilisateur\';
    protected $primaryKey = \'id_type_utilisateur\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_type_utilisateur\',
        \'libelle_type_utilisateur\',
    ];

    public function utilisateurs()
    {
        return $this->hasMany(Utilisateur::class, \'id_type_utilisateur\', \'id_type_utilisateur\');
    }
}
',
    'Ue' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ue extends Model
{
    use HasFactory;

    protected $table = \'ue\';
    protected $primaryKey = \'id_ue\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_ue\',
        \'libelle_ue\',
        \'credits_ue\',
    ];

    protected $casts = [
        \'credits_ue\' => \'integer\',
    ];

    public function ecues()
    {
        return $this->hasMany(Ecue::class, \'id_ue\', \'id_ue\');
    }
}
',
    'Utilisateur' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Utilisateur extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = \'utilisateur\';
    protected $primaryKey = \'numero_utilisateur\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false; // Using custom date_creation, derniere_connexion

    protected $fillable = [
        \'numero_utilisateur\',
        \'login_utilisateur\',
        \'email_principal\',
        \'mot_de_passe\',
        \'date_creation\',
        \'derniere_connexion\',
        \'token_reset_mdp\',
        \'date_expiration_token_reset\',
        \'token_validation_email\',
        \'email_valide\',
        \'tentatives_connexion_echouees\',
        \'compte_bloque_jusqua\',
        \'preferences_2fa_active\',
        \'secret_2fa\',
        \'photo_profil\',
        \'statut_compte\',
        \'id_niveau_acces_donne\',
        \'id_groupe_utilisateur\',
        \'id_type_utilisateur\',
    ];

    protected $hidden = [
        \'mot_de_passe\',
        \'token_reset_mdp\',
        \'secret_2fa\',
    ];

    protected $casts = [
        \'date_creation\' => \'datetime\',
        \'derniere_connexion\' => \'datetime\',
        \'date_expiration_token_reset\' => \'datetime\',
        \'email_valide\' => \'boolean\',
        \'tentatives_connexion_echouees\' => \'integer\',
        \'compte_bloque_jusqua\' => \'datetime\',
        \'preferences_2fa_active\' => \'boolean\',
        \'mot_de_passe\' => \'hashed\',
    ];

    public function niveauAccesDonne()
    {
        return $this->belongsTo(NiveauAccesDonne::class, \'id_niveau_acces_donne\', \'id_niveau_acces_donne\');
    }

    public function groupeUtilisateur()
    {
        return $this->belongsTo(GroupeUtilisateur::class, \'id_groupe_utilisateur\', \'id_groupe_utilisateur\');
    }

    public function typeUtilisateur()
    {
        return $this->belongsTo(TypeUtilisateur::class, \'id_type_utilisateur\', \'id_type_utilisateur\');
    }

    public function enseignant()
    {
        return $this->hasOne(Enseignant::class, \'numero_utilisateur\', \'numero_utilisateur\');
    }

    public function etudiant()
    {
        return $this->hasOne(Etudiant::class, \'numero_utilisateur\', \'numero_utilisateur\');
    }

    public function personnelAdministratif()
    {
        return $this->hasOne(PersonnelAdministratif::class, \'numero_utilisateur\', \'numero_utilisateur\');
    }

    public function historiqueMotsDePasse()
    {
        return $this->hasMany(HistoriqueMotDePasse::class, \'numero_utilisateur\', \'numero_utilisateur\');
    }

    public function delegationsDelegant()
    {
        return $this->hasMany(Delegation::class, \'id_delegant\', \'numero_utilisateur\');
    }

    public function delegationsDelegue()
    {
        return $this->hasMany(Delegation::class, \'id_delegue\', \'numero_utilisateur\');
    }

    public function documentsGeneres()
    {
        return $this->hasMany(DocumentGenere::class, \'numero_utilisateur_concerne\', \'numero_utilisateur\');
    }

    public function enregistrements()
    {
        return $this->hasMany(Enregistrer::class, \'numero_utilisateur\', \'numero_utilisateur\');
    }

    public function lecturesMessage()
    {
        return $this->hasMany(LectureMessage::class, \'numero_utilisateur\', \'numero_utilisateur\');
    }

    public function messagesChat()
    {
        return $this->hasMany(MessageChat::class, \'numero_utilisateur_expediteur\', \'numero_utilisateur\');
    }

    public function participantsConversation()
    {
        return $this->hasMany(ParticipantConversation::class, \'numero_utilisateur\', \'numero_utilisateur\');
    }

    public function pistes()
    {
        return $this->hasMany(Pister::class, \'numero_utilisateur\', \'numero_utilisateur\');
    }

    public function receptions()
    {
        return $this->hasMany(Recevoir::class, \'numero_utilisateur\', \'numero_utilisateur\');
    }

    public function sessions()
    {
        return $this->hasMany(Session::class, \'user_id\', \'numero_utilisateur\');
    }

    public function compteRendusRediges()
    {
        return $this->hasMany(CompteRendu::class, \'id_redacteur\', \'numero_utilisateur\');
    }
}
',
    'ValidationPv' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ValidationPv extends Model
{
    use HasFactory;

    protected $table = \'validation_pv\';
    protected $primaryKey = [\'id_compte_rendu\', \'numero_enseignant\'];
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_compte_rendu\',
        \'numero_enseignant\',
        \'id_decision_validation_pv\',
        \'date_validation\',
        \'commentaire_validation_pv\',
    ];

    protected $casts = [
        \'date_validation\' => \'datetime\',
    ];

    public function compteRendu()
    {
        return $this->belongsTo(CompteRendu::class, \'id_compte_rendu\', \'id_compte_rendu\');
    }

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class, \'numero_enseignant\', \'numero_enseignant\');
    }

    public function decisionValidationPvRef()
    {
        return $this->belongsTo(DecisionValidationPvRef::class, \'id_decision_validation_pv\', \'id_decision_validation_pv\');
    }
}
',
    'VoteCommission' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoteCommission extends Model
{
    use HasFactory;

    protected $table = \'vote_commission\';
    protected $primaryKey = \'id_vote\';
    public $incrementing = false;
    protected $keyType = \'string\';
    public $timestamps = false;

    protected $fillable = [
        \'id_vote\',
        \'id_session\',
        \'id_rapport_etudiant\',
        \'numero_enseignant\',
        \'id_decision_vote\',
        \'commentaire_vote\',
        \'date_vote\',
        \'tour_vote\',
    ];

    protected $casts = [
        \'date_vote\' => \'datetime\',
        \'tour_vote\' => \'integer\',
    ];

    public function sessionValidation()
    {
        return $this->belongsTo(SessionValidation::class, \'id_session\', \'id_session\');
    }

    public function rapportEtudiant()
    {
        return $this->belongsTo(RapportEtudiant::class, \'id_rapport_etudiant\', \'id_rapport_etudiant\');
    }

    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class, \'numero_enseignant\', \'numero_enseignant\');
    }

    public function decisionVoteRef()
    {
        return $this->belongsTo(DecisionVoteRef::class, \'id_decision_vote\', \'id_decision_vote\');
    }
}
',
];

$outputDir = __DIR__ . '/app/Models/';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

foreach ($models as $modelName => $modelContent) {
    $filePath = $outputDir . $modelName . '.php';
    file_put_contents($filePath, $modelContent);
    echo "Created: " . $filePath . "\n";
}

echo "\nAll 69 Eloquent models have been generated successfully!\n";

?>
