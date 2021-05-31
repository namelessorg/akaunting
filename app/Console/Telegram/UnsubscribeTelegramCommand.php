<?php

declare(strict_types=1);

namespace App\Console\Telegram;

use App\Jobs\Document\CreateDocument;
use App\Lib\Telegram\Update;
use App\Models\Common\Company;
use App\Models\Common\Contact;
use App\Models\Common\Item;
use App\Models\Document\Document;
use App\Services\TelegramService;
use App\Traits\Documents;
use App\Traits\Jobs;
use Illuminate\Support\Carbon;
use Telegram\Bot\Exceptions\TelegramResponseException;

class UnsubscribeTelegramCommand extends AbstractTelegramCommand
{
    /**
     * @var string Command Name
     */
    protected $name = "unsubscribe";

    /**
     * @var string Command Description
     */
    protected $description = "Unsubscribe (disable user)";

    public function run(Contact $contact, Update $update): void
    {
        $contact->enabled = false;
        $this->replyWithMessage([
            'text' => 'Your account was unsubscribed.'
        ]);
    }

    public function __destruct()
    {
        Company::forgetCurrent();
    }
}
