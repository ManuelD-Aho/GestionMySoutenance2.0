<?php

namespace App\Services;

use App\Models\ParametreSysteme;
use App\Models\AnneeAcademique;
use App\Models\Sequence;
use App\Models\Traitement; // Pour la gestion des menus
use App\Utils\IdGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Exceptions\{ElementNotFoundException, OperationFailedException, DuplicateEntryException};

class SystemService
{
    protected $idGenerator;
    protected $supervisionService;

    public function __construct(
        IdGenerator $idGenerator,
        SupervisionService $supervisionService
    ) {
        $this->idGenerator = $idGenerator;
        $this->supervisionService = $supervisionService;
    }

    /**
     * Récupère un paramètre système par sa clé.
     * Utilise le cache pour optimiser les lectures fréquentes.
     *
     * @param string $key La clé du paramètre.
     * @param mixed $default La valeur par défaut si le paramètre n'existe pas.
     * @return mixed La valeur du paramètre.
     */
    public function getParametre(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever('system_parameter_' . $key, function () use ($key, $default) {
            $param = ParametreSysteme::find($key);
            return $param ? $param->valeur : $default;
        });
    }

    /**
     * Récupère tous les paramètres système.
     * Utilise le cache pour optimiser les lectures.
     *
     * @return array
     */
    public function getAllParametres(): array
    {
        return Cache::rememberForever('all_system_parameters', function () {
            return ParametreSysteme::all()->pluck('valeur', 'cle')->toArray();
        });
    }

    /**
     * Met à jour un ou plusieurs paramètres système.
     * Invalide le cache après la mise à jour.
     *
     * @param array $parameters Un tableau associatif de clés => valeurs.
     * @return bool
     * @throws OperationFailedException Si la mise à jour échoue.
     */
    public function setParametres(array $parameters): bool
    {
        return DB::transaction(function () use ($parameters) {
            foreach ($parameters as $key => $value) {
                // Utilisation du Query Builder pour les mises à jour directes sur la clé primaire
                $affectedRows = DB::table('parametres_systeme')
                    ->where('cle', $key)
                    ->update(['valeur' => (string) $value]);

                if ($affectedRows === 0) {
                    // Si aucune ligne n'a été affectée, cela peut signifier que la clé n'existe pas.
                    // On peut choisir de l'insérer ou de lancer une exception.
                    // Ici, nous lançons une exception si la clé n'existe pas.
                    if (!ParametreSysteme::where('cle', $key)->exists()) {
                        throw new ElementNotFoundException("Paramètre système '{$key}' non trouvé.");
                    }
                    throw new OperationFailedException("Échec de la mise à jour du paramètre '{$key}'.");
                }
                Cache::forget('system_parameter_' . $key); // Invalide le cache du paramètre spécifique
            }
            Cache::forget('all_system_parameters'); // Invalide le cache de tous les paramètres

            $this->supervisionService->recordAction(Auth::id() ?? 'SYSTEM', 'MISE_AJOUR_PARAMETRES', null, null, ['parameters' => $parameters]);
            return true;
        });
    }

    /**
     * Active ou désactive le mode maintenance.
     *
     * @param bool $active True pour activer, false pour désactiver.
     * @param string $message Le message à afficher en mode maintenance.
     * @return bool
     */
    public function activateMaintenanceMode(bool $active, string $message = "Le site est en cours de maintenance."): bool
    {
        return $this->setParametres([
            'MAINTENANCE_MODE_ENABLED' => $active ? '1' : '0',
            'MAINTENANCE_MODE_MESSAGE' => $message
        ]);
    }

    /**
     * Vérifie si le mode maintenance est activé.
     *
     * @return bool
     */
    public function isMaintenanceModeActive(): bool
    {
        return (bool) $this->getParametre('MAINTENANCE_MODE_ENABLED', false);
    }

    /**
     * Crée une nouvelle année académique.
     *
     * @param string $label Le libellé de l'année (ex: '2023-2024').
     * @param string $startDate La date de début.
     * @param string $endDate La date de fin.
     * @param bool $isActive Indique si elle doit être active.
     * @return string L'ID de l'année académique créée.
     * @throws DuplicateEntryException Si l'année académique existe déjà.
     * @throws OperationFailedException Si la création échoue.
     */
    public function createAcademicYear(string $label, string $startDate, string $endDate, bool $isActive = false): string
    {
        $idYear = "ANNEE-" . str_replace('/', '-', $label); // Génération de l'ID
        if (AnneeAcademique::find($idYear)) {
            throw new DuplicateEntryException("L'année académique '{$label}' existe déjà.");
        }

        return DB::transaction(function () use ($idYear, $label, $startDate, $endDate, $isActive) {
            if (!AnneeAcademique::create([
                'id_annee_academique' => $idYear,
                'libelle_annee_academique' => $label,
                'date_debut' => $startDate,
                'date_fin' => $endDate,
                'est_active' => $isActive
            ])) {
                throw new OperationFailedException("Échec de la création de l'année académique.");
            }

            if ($isActive) {
                $this->setActiveAcademicYear($idYear);
            }

            $this->supervisionService->recordAction(Auth::id(), 'CREATE_ANNEE_ACADEMIQUE', $idYear, 'AnneeAcademique');
            return $idYear;
        });
    }

    /**
     * Lit une année académique par son ID.
     *
     * @param string $idAcademicYear L'ID de l'année académique.
     * @return AnneeAcademique|null
     */
    public function readAcademicYear(string $idAcademicYear): ?AnneeAcademique
    {
        return AnneeAcademique::find($idAcademicYear);
    }

    /**
     * Met à jour une année académique.
     *
     * @param string $idAcademicYear L'ID de l'année académique.
     * @param array $data Les données à mettre à jour.
     * @return bool
     * @throws ElementNotFoundException Si l'année académique n'est pas trouvée.
     * @throws OperationFailedException Si la mise à jour échoue.
     */
    public function updateAcademicYear(string $idAcademicYear, array $data): bool
    {
        $year = AnneeAcademique::find($idAcademicYear);
        if (!$year) {
            throw new ElementNotFoundException("Année académique non trouvée.");
        }

        $success = $year->update($data);

        if ($success) {
            $this->supervisionService->recordAction(Auth::id(), 'UPDATE_ANNEE_ACADEMIQUE', $idAcademicYear, 'AnneeAcademique', ['data' => $data]);
        }
        return $success;
    }

    /**
     * Supprime une année académique.
     *
     * @param string $idAcademicYear L'ID de l'année académique.
     * @return bool
     * @throws OperationFailedException Si l'année académique est liée à des inscriptions.
     */
    public function deleteAcademicYear(string $idAcademicYear): bool
    {
        // Vérifier les dépendances avant suppression
        if (Inscrire::where('id_annee_academique', $idAcademicYear)->exists()) {
            throw new OperationFailedException("Suppression impossible : des inscriptions sont liées à cette année académique.");
        }

        $success = AnneeAcademique::destroy($idAcademicYear);
        if (!$success) {
            throw new ElementNotFoundException("Année académique non trouvée ou impossible à supprimer.");
        }
        $this->supervisionService->recordAction(Auth::id(), 'DELETE_ANNEE_ACADEMIQUE', $idAcademicYear, 'AnneeAcademique');
        return (bool) $success;
    }

    /**
     * Liste toutes les années académiques.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listAcademicYears(): \Illuminate\Database\Eloquent\Collection
    {
        return AnneeAcademique::orderByDesc('date_debut')->get();
    }

    /**
     * Récupère l'année académique active.
     *
     * @return AnneeAcademique|null
     */
    public function getActiveAcademicYear(): ?AnneeAcademique
    {
        return AnneeAcademique::where('est_active', true)->first();
    }

    /**
     * Définit une année académique comme active.
     *
     * @param string $idAcademicYear L'ID de l'année académique à activer.
     * @return bool
     * @throws ElementNotFoundException Si l'année académique n'est pas trouvée.
     * @throws OperationFailedException Si l'activation échoue.
     */
    public function setActiveAcademicYear(string $idAcademicYear): bool
    {
        return DB::transaction(function () use ($idAcademicYear) {
            // Désactiver toutes les années académiques actives
            AnneeAcademique::where('est_active', true)->update(['est_active' => false]);

            // Activer l'année spécifiée
            $year = AnneeAcademique::find($idAcademicYear);
            if (!$year) {
                throw new ElementNotFoundException("Année académique '{$idAcademicYear}' non trouvée.");
            }
            $year->est_active = true;
            $success = $year->save();

            if ($success) {
                $this->supervisionService->recordAction(Auth::id(), 'CHANGEMENT_ANNEE_ACTIVE', $idAcademicYear, 'AnneeAcademique');
            }
            return $success;
        });
    }

    /**
     * Gère les opérations CRUD sur les tables de référentiel.
     *
     * @param string $operation L'opération à effectuer ('list', 'read', 'create', 'update', 'delete').
     * @param string $referentialName Le nom de la table du référentiel.
     * @param string|null $id L'ID de l'entrée (pour read, update, delete).
     * @param array|null $data Les données de l'entrée (pour create, update).
     * @return mixed
     * @throws \InvalidArgumentException Si l'opération est inconnue ou les paramètres sont manquants.
     * @throws ElementNotFoundException Si l'entrée n'est pas trouvée.
     * @throws OperationFailedException Si l'opération échoue.
     * @throws DuplicateEntryException Si une entrée avec des attributs uniques existe déjà lors de la création.
     */
    public function manageReferential(string $operation, string $referentialName, ?string $id = null, ?array $data = null): mixed
    {
        // Mapper le nom du référentiel à son modèle Eloquent correspondant
        $modelClass = 'App\\Models\\' . Str::studly($referentialName);
        if (!class_exists($modelClass) || !is_subclass_of($modelClass, \Illuminate\Database\Eloquent\Model::class)) {
            throw new \InvalidArgumentException("Modèle Eloquent non trouvé pour le référentiel '{$referentialName}'.");
        }
        $modelInstance = new $modelClass();
        $primaryKey = $modelInstance->getKeyName();

        switch (strtolower($operation)) {
            case 'list':
                return $modelClass::all();
            case 'read':
                if ($id === null) throw new \InvalidArgumentException("L'ID est requis pour l'opération 'read'.");
                return $modelClass::find($id);
            case 'create':
                if ($data === null) throw new \InvalidArgumentException("Les données sont requises pour 'create'.");
                try {
                    $result = $modelClass::create($data);
                    $this->supervisionService->recordAction(Auth::id() ?? 'SYSTEM', 'CREATE_REFERENTIEL', $result->{$primaryKey}, $referentialName, $data);
                    return $result;
                } catch (\Illuminate\Database\QueryException $e) {
                    if ($e->getCode() == 23000) { // Code SQL pour violation de contrainte d'unicité
                        throw new DuplicateEntryException("Une entrée avec des attributs uniques similaires existe déjà dans le référentiel '{$referentialName}'.");
                    }
                    throw new OperationFailedException("Échec de la création dans le référentiel '{$referentialName}'. Détails : " . $e->getMessage());
                }
            case 'update':
                if ($id === null || $data === null) throw new \InvalidArgumentException("L'ID et les données sont requis pour 'update'.");
                $entity = $modelClass::find($id);
                if (!$entity) throw new ElementNotFoundException("Entrée '{$id}' non trouvée dans le référentiel '{$referentialName}'.");
                $success = $entity->update($data);
                if ($success) {
                    $this->supervisionService->recordAction(Auth::id() ?? 'SYSTEM', 'UPDATE_REFERENTIEL', $id, $referentialName, $data);
                }
                return $success;
            case 'delete':
                if ($id === null) throw new \InvalidArgumentException("L'ID est requis pour 'delete'.");
                $success = $modelClass::destroy($id);
                if (!$success) throw new ElementNotFoundException("Entrée '{$id}' non trouvée ou impossible à supprimer dans le référentiel '{$referentialName}'.");
                $this->supervisionService->recordAction(Auth::id() ?? 'SYSTEM', 'DELETE_REFERENTIEL', $id, $referentialName);
                return (bool) $success;
            default:
                throw new \InvalidArgumentException("Opération '{$operation}' non reconnue sur le référentiel.");
        }
    }

    /**
     * Met à jour la structure hiérarchique et l'ordre d'affichage des éléments de menu.
     *
     * @param array $menuStructure Un tableau représentant la nouvelle hiérarchie et l'ordre.
     * @return bool True si la mise à jour est réussie, false sinon.
     * @throws OperationFailedException Si la mise à jour échoue.
     */
    public function updateMenuStructure(array $menuStructure): bool
    {
        return DB::transaction(function () use ($menuStructure) {
            foreach ($menuStructure as $item) {
                if (!isset($item['id']) || !isset($item['ordre'])) {
                    throw new OperationFailedException("Structure de menu invalide: id ou ordre manquant.");
                }
                $dataToUpdate = [
                    'ordre_affichage' => (int) $item['ordre'],
                    'id_parent_traitement' => $item['parent'] ?? null
                ];
                $traitement = Traitement::find($item['id']);
                if (!$traitement) {
                    throw new ElementNotFoundException("Élément de menu '{$item['id']}' non trouvé.");
                }
                if (!$traitement->update($dataToUpdate)) {
                    throw new OperationFailedException("Échec de la mise à jour de l'élément de menu: " . $item['id']);
                }
            }
            $this->supervisionService->recordAction(Auth::id() ?? 'SYSTEM', 'UPDATE_MENU_STRUCTURE', null, 'Menu', ['structure' => $menuStructure]);
            return true;
        });
    }
}
