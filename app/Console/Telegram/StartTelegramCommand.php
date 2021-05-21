<?php

declare(strict_types=1);

namespace App\Console\Telegram;

use App\Lib\Telegram\Update;
use App\Models\Common\Contact;
use Telegram\Bot\Keyboard\Keyboard;

class StartTelegramCommand extends AbstractTelegramCommand
{
    /**
     * @var string Command Name
     */
    protected $name = 'start';

    protected $pattern = '{utm}';

    /**
     * @var string Command Description
     */
    protected $description = "Start Command to get you started";

    public function run(Contact $contact, Update $update): void
    {
        $this->insertUtm($contact, $this->getArguments()['utm'] ?? null);
        $expired = $contact->expires_at <= now() || !$contact->enabled;
        $message = "";
        if ($expired) {
            $message .= "You have no subscription now.\r\n";
        } else {
            $message .= "You subscription will expired: " . $contact->expires_at->diffForHumans(now()) . "\r\n";
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

    protected function insertUtm(Contact $contact, $utm)
    {
        if (!$contact->wasRecentlyCreated || !is_scalar($utm) || empty($utm)) {
            return;
        }

        $contact->utm = $utm;
        try {
            $contact->save();
        } catch (\Throwable $e) {
            logger('Couldnt save utm `'.$utm.'` for contact#' . $contact->id, ['e' => $e,]);
        }
    }
}
