<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Services\SecurityService; // Votre service de sécurité

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
        // Exemple: \App\Models\Utilisateur::class => \App\Policies\UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Définition des Gates pour le système RBAC
        // Ces Gates seront utilisées par le middleware 'can' ou la façade Gate::allows()

        // Gate pour l'accès au tableau de bord administrateur
        Gate::define('access-admin-dashboard', function ($user) {
            // $user est une instance de App\Models\Utilisateur
            return $user->id_groupe_utilisateur === 'GRP_ADMIN_SYS';
        });

        // Gate pour l'accès au tableau de bord étudiant
        Gate::define('access-student-dashboard', function ($user) {
            return $user->id_groupe_utilisateur === 'GRP_ETUDIANT';
        });

        // Gate pour l'accès au tableau de bord commission
        Gate::define('access-commission-dashboard', function ($user) {
            return in_array($user->id_groupe_utilisateur, ['GRP_ENSEIGNANT', 'GRP_COMMISSION']);
        });

        // Gate pour l'accès au tableau de bord personnel administratif
        Gate::define('access-administrative-personnel-dashboard', function ($user) {
            return in_array($user->id_groupe_utilisateur, ['GRP_PERS_ADMIN', 'GRP_RS', 'GRP_AGENT_CONFORMITE']);
        });

        // Définition des Gates pour les permissions granulaires (TRAIT_...)
        // Ces Gates s'appuient sur votre SecurityService pour la logique réelle
        // d'autorisation, qui vérifie les permissions du groupe et les délégations.

        // Exemple: Permission de lister les utilisateurs
        Gate::define('TRAIT_ADMIN_GERER_UTILISATEURS_LISTER', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_ADMIN_GERER_UTILISATEURS_LISTER');
        });

        // Exemple: Permission de créer un utilisateur
        Gate::define('TRAIT_ADMIN_GERER_UTILISATEURS_CREER', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_ADMIN_GERER_UTILISATEURS_CREER');
        });

        // Exemple: Permission de modifier un utilisateur
        Gate::define('TRAIT_ADMIN_GERER_UTILISATEURS_MODIFIER', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_ADMIN_GERER_UTILISATEURS_MODIFIER');
        });

        // Exemple: Permission de supprimer un utilisateur
        Gate::define('TRAIT_ADMIN_GERER_UTILISATEURS_DELETE', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_ADMIN_GERER_UTILISATEURS_DELETE');
        });

        // Exemple: Permission d'accéder à la configuration admin
        Gate::define('TRAIT_ADMIN_CONFIG_ACCEDER', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_ADMIN_CONFIG_ACCEDER');
        });

        // Exemple: Permission de gérer les référentiels
        Gate::define('TRAIT_ADMIN_CONFIG_REFERENTIELS_GERER', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_ADMIN_CONFIG_REFERENTIELS_GERER');
        });

        // Exemple: Permission de gérer les modèles de documents
        Gate::define('TRAIT_ADMIN_CONFIG_MODELES_DOC_GERER', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_ADMIN_CONFIG_MODELES_DOC_GERER');
        });

        // Exemple: Permission de gérer les notifications
        Gate::define('TRAIT_ADMIN_CONFIG_NOTIFS_GERER', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_ADMIN_CONFIG_NOTIFS_GERER');
        });

        // Exemple: Permission de gérer l'ordre des menus
        Gate::define('TRAIT_ADMIN_CONFIG_MENUS_GERER', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_ADMIN_CONFIG_MENUS_GERER');
        });

        // Exemple: Permission de gérer les paramètres système
        Gate::define('TRAIT_ADMIN_CONFIG_PARAMETRES_GERER', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_ADMIN_CONFIG_PARAMETRES_GERER');
        });

        // Exemple: Permission de gérer les années académiques
        Gate::define('TRAIT_ADMIN_CONFIG_ANNEES_GERER', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_ADMIN_CONFIG_ANNEES_GERER');
        });

        // Exemple: Permission de superviser l'audit
        Gate::define('TRAIT_ADMIN_SUPERVISION_AUDIT_VIEW', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_ADMIN_SUPERVISION_AUDIT_VIEW');
        });

        // Exemple: Permission de purger les logs d'audit
        Gate::define('TRAIT_ADMIN_SUPERVISION_AUDIT_PURGE', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_ADMIN_SUPERVISION_AUDIT_PURGE');
        });

        // Exemple: Permission de gérer les tâches asynchrones
        Gate::define('TRAIT_ADMIN_SUPERVISION_TACHES_GERER', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_ADMIN_SUPERVISION_TACHES_GERER');
        });

        // Exemple: Permission d'impersonner un utilisateur
        Gate::define('TRAIT_ADMIN_IMPERSONATE_USER', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_ADMIN_IMPERSONATE_USER');
        });

        // Exemple: Permission de l'étudiant de gérer son profil
        Gate::define('TRAIT_ETUDIANT_PROFIL_GERER', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_ETUDIANT_PROFIL_GERER');
        });

        // Exemple: Permission de l'étudiant de soumettre un rapport
        Gate::define('TRAIT_ETUDIANT_RAPPORT_SOUMETTRE', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_ETUDIANT_RAPPORT_SOUMETTRE');
        });

        // Exemple: Permission de l'étudiant de suivre son rapport
        Gate::define('TRAIT_ETUDIANT_RAPPORT_SUIVRE', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_ETUDIANT_RAPPORT_SUIVRE');
        });

        // Exemple: Permission du personnel administratif de lister les rapports de conformité
        Gate::define('TRAIT_PERS_ADMIN_CONFORMITE_LISTER', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_PERS_ADMIN_CONFORMITE_LISTER');
        });

        // Exemple: Permission du personnel administratif de vérifier la conformité
        Gate::define('TRAIT_PERS_ADMIN_CONFORMITE_VERIFIER', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_PERS_ADMIN_CONFORMITE_VERIFIER');
        });

        // Exemple: Permission du personnel administratif d'accéder à la scolarité
        Gate::define('TRAIT_PERS_ADMIN_SCOLARITE_ACCEDER', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_PERS_ADMIN_SCOLARITE_ACCEDER');
        });

        // Exemple: Permission du personnel administratif de gérer les dossiers étudiants
        Gate::define('TRAIT_PERS_ADMIN_SCOLARITE_ETUDIANT_GERER', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_PERS_ADMIN_SCOLARITE_ETUDIANT_GERER');
        });

        // Exemple: Permission du personnel administratif de gérer les pénalités
        Gate::define('TRAIT_PERS_ADMIN_SCOLARITE_PENALITE_GERER', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_PERS_ADMIN_SCOLARITE_PENALITE_GERER');
        });

        // Exemple: Permission du personnel administratif de gérer les réclamations
        Gate::define('TRAIT_PERS_ADMIN_RECLAMATIONS_GERER', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_PERS_ADMIN_RECLAMATIONS_GERER');
        });

        // Exemple: Permission d'accéder aux fichiers protégés (AssetController)
        Gate::define('TRAIT_ADMIN_ACCES_FICHIERS_PROTEGES', function ($user) {
            return app(SecurityService::class)->userHasPermission('TRAIT_ADMIN_ACCES_FICHIERS_PROTEGES');
        });

        // Ajoutez toutes les autres Gates nécessaires ici en suivant le même modèle.
    }
}
