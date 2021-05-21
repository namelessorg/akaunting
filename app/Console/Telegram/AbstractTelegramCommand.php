<?php

declare(strict_types=1);

namespace App\Console\Telegram;

use App\Lib\Telegram\Update;
use App\Models\Common\Contact;
use Psr\Log\LoggerInterface;
use Telegram\Bot\Commands\Command;

abstract class AbstractTelegramCommand extends Command
{

    protected $isItEndOfDialog = true;

    final public function handle()
    {
        $update = $this->getUpdate();
        $contact = $update->getContact();
        $update->setIsProcessed(true);
        $update->getContact()->last_command = [
            'name' => $this->name,
            'entity' => $this->entity,
        ];

        if (!$contact->company->enabled) {
            $this->replyWithMessage([
                'text' => "Sorry, we're down for maintenance\r\nWe'll be back up shortly"
            ]);
            return;
        }

        try {
            $this->run($contact, $update);
        } catch (\Throwable $e) {
            app(LoggerInterface::class)->error($e->getMessage(), ['e' => $e,]);
            throw $e;
        } finally {
            if ($this->isItEndOfDialog()) {
                $contact->last_command = [];
            }

            $contact->save();
        }
    }

    abstract public function run(Contact $contact, Update $update): void;

    /**
     * @return Update
     */
    public function getUpdate(): \Telegram\Bot\Objects\Update
    {
        return $this->update;
    }

    public function getContact(): Contact
    {
        return $this->getUpdate()->getContact();
    }

    /**
     * Mark interactive messaging as ended
     * @return bool
     */
    public function isItEndOfDialog(): bool
    {
        return $this->isItEndOfDialog;
    }
}
