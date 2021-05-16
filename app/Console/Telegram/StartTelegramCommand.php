<?php

declare(strict_types=1);

namespace App\Console\Telegram;

use Telegram\Bot\Keyboard\Keyboard;

class StartTelegramCommand extends AbstractTelegramCommand
{
    /**
     * @var string Command Name
     */
    protected $name = "start";

    /**
     * @var string Command Description
     */
    protected $description = "Start Command to get you started";

    public function run(): void
    {
        $contact = $this->getUpdate()->getContact();
        $expired = $contact->expires_at <= now() || !$contact->enabled;
        $message = "";
        if ($expired) {
            $message .= "You have no subscription now.\r\n";
        } else {
            $message .= "You subscription will expired: " . now()->diffForHumans($contact->expires_at) . "\r\n";
        }

        $keyboard = [];
        foreach ($this->getUpdate()->getContact()->company->items as $item) {
            if (!$item->enabled) {
                continue;
            }
            if (!$item->category->id) {
                continue;
            }

            $keyboard[] = Keyboard::inlineButton([
                'callback_data' => '/subscribe ' . $item->id,
                'text' => $item->name,
            ]);
        }

        if (empty($keyboard)) {
            $message .= "For buy new subscription, came back later. Temporary we haven't offer for you";
        }

        $this->replyWithMessage([
            'text' => $message,
            'reply_markup' => json_encode([
                'inline_keyboard' => [$keyboard],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ]),
        ]);
    }
}
