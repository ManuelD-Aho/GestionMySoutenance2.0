<?php

namespace App\Utils;

use App\Models\Sequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\OperationFailedException;
use App\Services\SupervisionService; // Injection du service de supervision
use App\Services\SystemService; // Injection du service système pour l'année académique

class IdGenerator
{
    protected $supervisionService;
    protected $systemService;

    public function __construct(SupervisionService $supervisionService, SystemService $systemService)
    {
        $this->supervisionService = $supervisionService;
        $this->systemService = $systemService;
    }

    /**
     * Génère un identifiant unique formaté selon la convention PREFIXE-ANNEE-SEQUENCE.
     * Assure l'atomicité de l'opération grâce à une transaction et un verrou de ligne.
     *
     * @param string $prefixe Le préfixe de l'identifiant (ex: 'RAP', 'ETU', 'PV').
     * @return string L'identifiant unique généré.
     * @throws OperationFailedException Si aucune année académique active n'est définie ou si la génération échoue.
     */
    public function generateUniqueId(string $prefixe): string
    {
        $anneeAcademiqueActive = $this->systemService->getActiveAcademicYear();
        if (!$anneeAcademiqueActive) {
            throw new OperationFailedException("Impossible de générer un ID : aucune année académique n'est active.");
        }
        $annee = (int) substr($anneeAcademiqueActive->libelle_annee_academique, 0, 4);

        $nextValue = 0;

        try {
            DB::transaction(function () use ($prefixe, $annee, &$nextValue) {
                // Verrouiller la ligne de la séquence pour l'année en cours
                $sequence = Sequence::where('nom_sequence', $prefixe)
                    ->where('annee', $annee)
                    ->lockForUpdate() // SELECT ... FOR UPDATE
                    ->first();

                $nextValue = $sequence ? $sequence->valeur_actuelle + 1 : 1;

                if ($sequence) {
                    $sequence->valeur_actuelle = $nextValue;
                    $sequence->save();
                } else {
                    Sequence::create([
                        'nom_sequence' => $prefixe,
                        'annee' => $annee,
                        'valeur_actuelle' => $nextValue
                    ]);
                }
            });

            return sprintf('%s-%d-%04d', strtoupper($prefixe), $annee, $nextValue);

        } catch (\Exception $e) {
            // Enregistrer l'échec de génération d'ID
            $this->supervisionService->recordAction(
                Auth::id() ?? 'SYSTEM', // Utilisateur courant ou SYSTEM
                'ECHEC_GENERATION_ID_UNIQUE',
                null,
                'Identifiant',
                ['prefix' => $prefixe, 'year' => $annee, 'error' => $e->getMessage()]
            );
            throw new OperationFailedException("Échec de la génération de l'identifiant pour '{$prefixe}'. Détails : " . $e->getMessage());
        }
    }
}
