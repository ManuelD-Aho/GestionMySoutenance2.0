<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\DefenseWorkflowService;
use App\Services\AcademicJourneyService;
use App\Services\SecurityService;
use App\Services\SupervisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Gère l'affichage du tableau de bord principal de l'étudiant.
 */
class StudentDashboardController extends Controller
{
    protected $defenseWorkflowService;
    protected $academicJourneyService;
    protected $securityService;
    protected $supervisionService;

    public function __construct(
        DefenseWorkflowService $defenseWorkflowService,
        AcademicJourneyService $academicJourneyService,
        SecurityService $securityService,
        SupervisionService $supervisionService
    ) {
        $this->defenseWorkflowService = $defenseWorkflowService;
        $this->academicJourneyService = $academicJourneyService;
        $this->securityService = $securityService;
        $this->supervisionService = $supervisionService;

        // Appliquer le middleware de permission pour l'accès au tableau de bord étudiant
        $this->middleware('can:access-student-dashboard');
    }

    /**
     * Affiche le tableau de bord avec le statut du rapport et les alertes.
     *
     * @param Request $request L'objet requête HTTP.
     * @return \Illuminate\View\View|\Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        try {
            $report = $this->defenseWorkflowService->readReportForActiveAcademicYear($user->numero_utilisateur);
            $workflowSteps = $this->defenseWorkflowService->getWorkflowStepsForReport($report ? $report->id_rapport_etudiant : null);
            $isEligible = $this->academicJourneyService->isStudentEligibleForSubmission($user->numero_utilisateur);

            return view('Student.dashboard_student', [
                'title' => 'Mon Tableau de Bord',
                'report' => $report,
                'workflowSteps' => $workflowSteps,
                'isEligible' => $isEligible,
            ]);

        } catch (\Exception $e) {
            Log::error("Erreur StudentDashboardController::index : " . $e->getMessage());
            return response()->view('errors.500', ['message' => "Une erreur est survenue lors du chargement de votre tableau de bord."], 500);
        }
    }
}
