<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Log;
use App\Events\ConversationPausedEvent;
use App\Listeners\SendPausedNotificationListener;
use App\Models\Conversation;
use Illuminate\Support\Facades\Event;
use App\Services\PythonApiService;


class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        //Log::info('AppServiceProvider: Registering UserObserver');
        User::observe(UserObserver::class);
        //Log::info('AppServiceProvider: UserObserver registered');

        Event::listen(
            ConversationPausedEvent::class,
            SendPausedNotificationListener::class
        );
    }


public function register()
{
    $this->app->singleton(PythonApiService::class, function ($app) {
        return new PythonApiService();
    });
    
}


}