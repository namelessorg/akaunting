<?php

namespace Modules\TinkoffBank\Listeners;

use App\Events\Auth\LandingPageShowing as Event;

class AddLandingPage
{
    /**
     * Handle the event.
     *
     * @param Event $event
     * @return void
     */
    public function handle(Event $event)
    {
        $event->user->landing_pages['tinkoff-bank.settings.edit'] = trans('tinkoff-bank::general.name');
    }
}
