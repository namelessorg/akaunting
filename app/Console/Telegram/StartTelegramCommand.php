<?php

declare(strict_types=1);

namespace App\Console\Telegram;

use App\Lib\Telegram\Update;
use App\Models\Common\Contact;
use Carbon\CarbonInterface;
use Telegram\Bot\Keyboard\Keyboard;

class StartTelegramCommand extends AbstractTelegramCommand
{
    public const DAYS_FOR_UTM_ACCESS = 7;

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
            $message .= "You subscription will expire in " . $contact->expires_at->diffForHumans(now()) . "\r\n";
            if (!empty($contact->mt)) {
                $message .= "\r\nYour metatrader id is <b>" . implode('</b>, <b>', $contact->mt) . "</b>\r\nFor change it use command /mt\r\n";
            }
        }

        $message .= "\r\n";

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
        } else {
            $message .= "Now we have " . count($keyboard) . " subscription " . trans_choice('general.option', count($keyboard)) . ", choose one from list";
        }

        $this->replyWithMessage([
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => [$keyboard],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ]),
        ]);
    }

    protected function insertUtm(Contact $contact, $utm)
    {
        if (!empty($contact->utm) || !is_scalar($utm) || empty($utm)) {
            return;
        }
        if ($contact->created_at instanceof CarbonInterface && $contact->created_at->diffInDays(now()) >= self::DAYS_FOR_UTM_ACCESS) {
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
