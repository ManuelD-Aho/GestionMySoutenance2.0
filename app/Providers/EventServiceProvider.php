<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        // Exemple d'écouteur pour les événements Eloquent (si vous en avez besoin)
        // 'Illuminate\Database\Events\QueryExecuted' => [
        //     'App\Listeners\QueryLogger',
        // ],
        // 'App\Events\UserAction' => [
        //     'App\Listeners\RecordUserAction',
        // ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents(): bool
    {
        return false; // Mettez à true si vous voulez la découverte automatique des événements
    }
}
