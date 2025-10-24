<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Mapa de eventos → listeners.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // \App\Events\AlgoOcurrio::class => [
        //     \App\Listeners\ProcesarAlgo::class,
        // ],
    ];

    /**
     * Si quieres que Laravel busque listeners automáticamente en app/Listeners.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
