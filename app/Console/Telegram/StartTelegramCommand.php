<?php

declare(strict_types=1);

namespace App\Console\Telegram;

use Telegram\Bot\Commands\Command;

class StartTelegramCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "start";

    /**
     * @var string Command Description
     */
    protected $description = "Start Command to get you started";

    public function handle()
    {
        $this->replyWithMessage([
            'text' => 'Send me /pay if you want to subscribe'
        ]);
    }
}
