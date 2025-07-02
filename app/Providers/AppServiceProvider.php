<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AcademicJourneyService;
use App\Services\CommunicationService;
use App\Services\DefenseWorkflowService;
use App\Services\DocumentService;
use App\Services\SecurityService;
use App\Services\SupervisionService;
use App\Services\SystemService;
use App\Services\UserService;
use App\Utils\IdGenerator; // Votre utilitaire de génération d'IDs

class AppServiceProvider extends ServiceProvider
{
    /**
     * Enregistre les services de l'application dans le conteneur de services.
     *
     * @return void
     */
    public function register(): void
    {
        // Enregistrement des services métier comme singletons.
        // Cela signifie qu'une seule instance de chaque service sera créée et réutilisée.
        $this->app->singleton(AcademicJourneyService::class, function ($app) {
            return new AcademicJourneyService(
                $app->make(IdGenerator::class),
                $app->make(SupervisionService::class),
                $app->make(SystemService::class)
            );
        });

        $this->app->singleton(CommunicationService::class, function ($app) {
            return new CommunicationService(
                $app->make(IdGenerator::class),
                $app->make(SupervisionService::class),
                $app->make(SystemService::class)
            );
        });

        $this->app->singleton(DefenseWorkflowService::class, function ($app) {
            return new DefenseWorkflowService(
                $app->make(IdGenerator::class),
                $app->make(CommunicationService::class),
                $app->make(DocumentService::class),
                $app->make(SupervisionService::class),
                $app->make(SystemService::class),
                $app->make(AcademicJourneyService::class)
            );
        });

        $this->app->singleton(DocumentService::class, function ($app) {
            return new DocumentService(
                $app->make(IdGenerator::class),
                $app->make(SupervisionService::class),
                $app->make(SystemService::class)
            );
        });

        $this->app->singleton(SecurityService::class, function ($app) {
            return new SecurityService(
                $app->make(IdGenerator::class),
                $app->make(SupervisionService::class),
                $app->make(CommunicationService::class),
                $app->make(SystemService::class)
            );
        });

        $this->app->singleton(SupervisionService::class, function ($app) {
            return new SupervisionService(
                $app->make(IdGenerator::class)
            );
        });

        $this->app->singleton(SystemService::class, function ($app) {
            return new SystemService(
                $app->make(IdGenerator::class), // IdGenerator a besoin de SystemService, donc attention à la dépendance circulaire si non gérée par Laravel
                $app->make(SupervisionService::class)
            );
        });

        $this->app->singleton(UserService::class, function ($app) {
            return new UserService(
                $app->make(IdGenerator::class),
                $app->make(SupervisionService::class),
                $app->make(CommunicationService::class),
                $app->make(DocumentService::class),
                $app->make(SystemService::class)
            );
        });

        // Liaison pour IdGenerator (il a besoin de SystemService et SupervisionService)
        // Laravel gère les dépendances circulaires simples, mais il est bon de les expliciter
        $this->app->singleton(IdGenerator::class, function ($app) {
            return new IdGenerator(
                $app->make(SupervisionService::class),
                $app->make(SystemService::class)
            );
        });
    }

    /**
     * Démarre les services de l'application.
     *
     * @return void
     */
    public function boot(): void
    {
        // Ici, vous pouvez enregistrer des observateurs, des vues, etc.
        // Les Gates et Policies sont généralement définies dans AuthServiceProvider.
    }
}
