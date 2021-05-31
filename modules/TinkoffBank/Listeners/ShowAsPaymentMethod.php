<?php

namespace Modules\TinkoffBank\Listeners;

use App\Events\Module\PaymentMethodShowing as Event;

class ShowAsPaymentMethod
{
    /**
     * Handle the event.
     *
     * @param  Event $event
     * @return void
     */
    public function handle(Event $event)
    {
        $method = setting('tinkoff-bank');

        $method['code'] = 'tinkoff-bank';
        $method['icon'] = 'credit-card';
        if (!isset($method['name'])) {
            $method['name'] = 'Tinkoff Bank';
        }

        $event->modules->payment_methods[] = $method;
    }
}
