<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log; // N'oubliez pas d'importer la façade Loguse App\Http\Controllers\HomeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\ConfigurationController;
use App\Http\Controllers\Admin\SupervisionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Commission\CommissionDashboardController;
use App\Http\Controllers\Commission\WorkflowCommissionController;
use App\Http\Controllers\Student\StudentDashboardController;
use App\Http\Controllers\Student\ProfilStudentController;
use App\Http\Controllers\Student\ReportController;
use App\Http\Controllers\AdministrativePersonnel\AdministrativePersonnelDashboardController;
use App\Http\Controllers\AdministrativePersonnel\SchoolingController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// --- Routes Publiques et Authentification ---
Route::get('/', function () {
    Log::info('Accès à la page d\'accueil.'); // Ligne de test
    return view('welcome');
});
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'handleForgotPassword'])->name('password.email');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
Route::post('/reset-password/{token}', [AuthController::class, 'handleResetPassword'])->name('password.update');
Route::get('/validate-email/{token}', [AuthController::class, 'validateEmail'])->name('verification.verify');
Route::get('/2fa', [AuthController::class, 'show2faForm'])->name('2fa.show');
Route::post('/2fa', [AuthController::class, 'handle2faVerification'])->name('2fa.verify');

// --- Routes Protégées (nécessitent une connexion) ---
Route::middleware(['auth'])->group(function () { // 'auth' est le middleware d'authentification de Laravel
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // --- Section Administration ---
    Route::prefix('admin')->name('admin.')->middleware('can:access-admin-dashboard')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Gestion des utilisateurs
        Route::get('/users', [UserController::class, 'list'])->name('users.list');
        Route::get('/users/create', [UserController::class, 'showCreateUserForm'])->name('users.create');
        Route::post('/users', [UserController::class, 'create'])->name('users.store');
        Route::get('/users/{id}', [UserController::class, 'show'])->name('users.show');
        Route::put('/users/{id}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{id}', [UserController::class, 'delete'])->name('users.destroy');
        // Route pour les actions génériques sur utilisateur (changement de statut, reset mdp, impersonate)
        // Ces routes devraient être plus spécifiques dans une application réelle
        Route::post('/users/{id}/action', [UserController::class, 'handleUserAction'])->name('users.action');


        // Configuration
        Route::get('/configuration', [ConfigurationController::class, 'index'])->name('configuration.index');
        Route::post('/configuration/parameters', [ConfigurationController::class, 'handleSystemParameters'])->name('configuration.parameters');
        Route::post('/configuration/academic-years', [ConfigurationController::class, 'handleAcademicYearAction'])->name('configuration.academic-years');
        Route::post('/configuration/referentials', [ConfigurationController::class, 'handleReferentialAction'])->name('configuration.referentials');
        Route::get('/configuration/referentials/{entityName}', [ConfigurationController::class, 'getReferentialDetails'])->name('configuration.referentials.details');
        Route::post('/configuration/documents', [ConfigurationController::class, 'handleDocumentModelAction'])->name('configuration.documents');
        Route::post('/configuration/notifications', [ConfigurationController::class, 'handleNotificationAction'])->name('configuration.notifications');
        Route::post('/configuration/menus', [ConfigurationController::class, 'handleMenuOrder'])->name('configuration.menus');
        Route::post('/configuration/cache/clear', [ConfigurationController::class, 'clearCache'])->name('configuration.cache.clear');

        // Supervision
        Route::get('/supervision', [SupervisionController::class, 'index'])->name('supervision.index');
        Route::get('/supervision/logs/{id}', [SupervisionController::class, 'getAuditLogDetails'])->name('supervision.logs.details');
        Route::post('/supervision/logs/purge', [SupervisionController::class, 'purgeAuditLogs'])->name('supervision.logs.purge');
        Route::post('/supervision/tasks/{idTache}', [SupervisionController::class, 'handleTaskAction'])->name('supervision.tasks.action');
    });

    // --- Section Étudiant ---
    Route::prefix('student')->name('student.')->middleware('can:access-student-dashboard')->group(function () {
        Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
        Route::get('/profile', [ProfilStudentController::class, 'show'])->name('profile.show');
        Route::post('/profile', [ProfilStudentController::class, 'update'])->name('profile.update');
        Route::post('/profile/photo', [ProfilStudentController::class, 'handlePhotoUpload'])->name('profile.photo');

        // Rapports
        Route::get('/report/edit', [ReportController::class, 'edit'])->name('report.edit');
        Route::post('/report/create-from-template', [ReportController::class, 'create'])->name('report.create-from-template');
        Route::get('/report/edit/{idRapport}', [ReportController::class, 'show'])->name('report.show');
        Route::post('/report/save/{idRapport}', [ReportController::class, 'save'])->name('report.save');
        Route::post('/report/submit/{idRapport}', [ReportController::class, 'submit'])->name('report.submit');
        Route::post('/report/submit-corrections/{idRapport}', [ReportController::class, 'submitCorrections'])->name('report.submit-corrections');
    });

    // --- Section Commission ---
    Route::prefix('commission')->name('commission.')->middleware('can:access-commission-dashboard')->group(function () {
        Route::get('/dashboard', [CommissionDashboardController::class, 'index'])->name('dashboard');

        // Workflow de session
        Route::get('/workflow', [WorkflowCommissionController::class, 'index'])->name('workflow.index');
        Route::post('/workflow/sessions/create', [WorkflowCommissionController::class, 'create'])->name('workflow.sessions.create');
        Route::post('/workflow/reports/{idRapport}/vote', [WorkflowCommissionController::class, 'vote'])->name('workflow.reports.vote');
        Route::post('/workflow/sessions/{idSession}/initiate-pv', [WorkflowCommissionController::class, 'initiatePv'])->name('workflow.sessions.initiate-pv');
        Route::post('/workflow/pv/{idCompteRendu}/approve', [WorkflowCommissionController::class, 'approvePv'])->name('workflow.pv.approve');
        Route::post('/workflow/pv/{idCompteRendu}/force-validation', [WorkflowCommissionController::class, 'forcePvValidation'])->name('workflow.pv.force-validation');
    });

    // --- Section Personnel Administratif ---
    Route::prefix('administrative-personnel')->name('administrative-personnel.')->middleware('can:access-administrative-personnel-dashboard')->group(function () {
        Route::get('/dashboard', [AdministrativePersonnelDashboardController::class, 'index'])->name('dashboard');

        // Conformité
        Route::get('/compliance/queue', [SchoolingController::class, 'complianceQueue'])->name('compliance.queue');
        Route::get('/compliance/verify/{idReport}', [SchoolingController::class, 'showCompliance'])->name('compliance.verify');
        Route::post('/compliance/process/{idReport}', [SchoolingController::class, 'processCompliance'])->name('compliance.process');

        // Scolarité
        Route::get('/schooling/students', [SchoolingController::class, 'index'])->name('schooling.students.index');
        Route::get('/schooling/students/{idStudent}', [SchoolingController::class, 'showStudent'])->name('schooling.students.show');
        Route::post('/schooling/students/activate-account', [SchoolingController::class, 'activateAccount'])->name('schooling.students.activate-account');
        Route::post('/schooling/students/inscription', [SchoolingController::class, 'handleInscriptionUpdate'])->name('schooling.students.inscription');
        Route::post('/schooling/students/note', [SchoolingController::class, 'handleNoteEntry'])->name('schooling.students.note');
        Route::post('/schooling/students/{studentNumber}/internship/{companyId}/validate', [SchoolingController::class, 'validateInternship'])->name('schooling.students.internship.validate');
        Route::post('/schooling/penalties/{idPenalty}/regularize', [SchoolingController::class, 'regularizePenalty'])->name('schooling.penalties.regularize');
        Route::post('/schooling/complaints/{idComplaint}/respond', [SchoolingController::class, 'handleComplaintResponse'])->name('schooling.complaints.respond');
        Route::post('/schooling/complaints/{idComplaint}/close', [SchoolingController::class, 'closeComplaint'])->name('schooling.complaints.close');
        Route::get('/schooling/students/export/{format}', [SchoolingController::class, 'exportStudents'])->name('schooling.students.export');
    });

    // --- Route pour servir les assets (si non géré par Nginx/Apache directement) ---
    // En production, Nginx/Apache devrait servir les assets directement depuis public/
    // En développement, Vite gère les assets.
    // Cette route est plus pour les fichiers "protégés" dans public/uploads
    Route::get('/assets/{filePath}', [AssetController::class, 'serve'])->where('filePath', '.*')->name('assets.serve');
});
