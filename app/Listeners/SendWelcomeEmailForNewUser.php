<?php

namespace App\Listeners;

use App\Events\UserCreatedEvent;
use App\Notifications\NewUserWelcomeEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendWelcomeEmailForNewUser implements ShouldQueue
{

    public function handle(UserCreatedEvent $event): void
    {
        $event->user->notify(new NewUserWelcomeEmail());
    }
}
