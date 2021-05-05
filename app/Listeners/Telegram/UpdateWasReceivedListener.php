<?php

namespace App\Listeners\Telegram;

use Telegram\Bot\Events\UpdateWasReceived;

class UpdateWasReceivedListener
{
    public function handle(UpdateWasReceived $event)
    {
        logger('Handle new telegram message, but its not expected', [
            $event->getUpdate()->toArray(),
        ]);
    }
}
