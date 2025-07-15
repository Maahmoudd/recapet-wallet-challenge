<?php

namespace App\Listeners;

use App\Events\UserCreatedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateWalletForNewUser
{

    public function handle(UserCreatedEvent $event): void
    {
        $event->user->wallet()->create();
    }
}
