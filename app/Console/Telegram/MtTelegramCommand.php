<?php

declare(strict_types=1);

namespace App\Console\Telegram;

use App\Lib\Telegram\Update;
use App\Models\Common\Contact;

class MtTelegramCommand extends AbstractTelegramCommand
{
    protected $name = 'mt';

    public function run(Contact $contact, Update $update): void
    {
        if (!$contact->enabled) {
            $message = "Only active users can change Meta Trader id, move to command /start for purchase subscription";
            $this->replyWithMessage([
                'text' => $message,
            ]);
            return;
        }

        if (!$update->message || !$update->message->text) {
            return;
        }
        if ($update->message->hasCommand()) {
            $this->isItEndOfDialog = false;
            $message = '';
            if (!empty($contact->mt)) {
                $message .= "Your metatrader id is <b>" . implode('</b>, <b>', $contact->mt) . "</b>\r\n\r\n";
            }

            if ($contact->enabled) {
                $message .= "For change your access id, send me number identity of your Meta Trader application in next message";
            } else {
                $message .= "Only subscribed users can change Meta Trader id, move to command /start for purchase";
            }

            $this->replyWithMessage([
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);

            return;
        }

        if (is_numeric($update->message->text) && $update->message->text > 0 && $update->message->text < PHP_INT_MAX) {
            logger('Contact#' . $contact->id . ' changed mt id', ['from' => $contact->mt, 'to' => $update->message->text,]);
            $contact->mt = (int)$update->message->text;
            $contact->save();
            $message = "Success! Now your Meta Trader id is <b>" . implode('</b>, <b>', (array)$contact->mt) . "</b>\r\n";
        } else {
            $message = "Wrong meta trader id! Now your Meta Trader id is <b>" . implode('</b>, <b>', (array)$contact->mt) . "</b>\r\n";
        }

        $this->replyWithMessage([
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);
    }
}
