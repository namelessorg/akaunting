<?php

declare(strict_types=1);

namespace App\Console\Telegram;

use App\Lib\Telegram\Update;
use Psr\Log\LoggerInterface;
use Telegram\Bot\Commands\Command;

abstract class AbstractTelegramCommand extends Command
{

    final public function handle()
    {
        $this->getUpdate()->setIsProcessed(true);
        $this->getUpdate()->getContact()->last_command = [
            'name' => $this->name,
            'entity' => $this->entity,
        ];

        if (!$this->getUpdate()->getContact()->company->enabled) {
            $this->replyWithMessage([
                'text' => "Sorry, we're down for maintenance\r\nWe'll be back up shortly"
            ]);
            return;
        }

        try {
            $this->run();
        } catch (\Throwable $e) {
            app(LoggerInterface::class)->error($e->getMessage(), ['e' => $e,]);
            throw $e;
        }
    }

    abstract public function run(): void;

    /**
     * @return Update
     */
    public function getUpdate(): \Telegram\Bot\Objects\Update
    {
        return $this->update;
    }
}
