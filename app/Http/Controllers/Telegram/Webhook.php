<?php

namespace App\Http\Controllers\Telegram;

use App\Abstracts\Http\Controller;
use App\Lib\Telegram\Update;
use App\Models\Common\Company;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Telegram\Bot\Api;

class Webhook extends Controller
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var TelegramService
     */
    private $telegramService;

    /**
     * @var Api
     */
    private $telegram;

    public function __construct(LoggerInterface $logger, TelegramService $telegramService, Api $telegram)
    {
        parent::__construct();
        $this->logger = $logger;
        $this->telegramService = $telegramService;
        $this->telegram = $telegram;
    }

    public function handle(Request $request, Company $companyId, string $token): void
    {
        $this->logger->debug('Incoming request', [
            'data' => $request->all(),
            'client_ip' => $request->ip(),
        ]);

        $companyId->makeCurrent(true);
        $companyBotToken = setting('company.telegram_observer_token');
        if (!$companyId->enabled) {
            $this->logger->debug('Received message on disabled company', [
                'input' => file_get_contents('php://input'),
            ]);
            return;
        }
        if (!hash_equals($token, $companyBotToken)) {
            $this->logger->warning('Unexpected observer token', [
                'expected' => $companyBotToken,
                'actual' => $token,
            ]);
            return;
        }

        $this->telegram->setAccessToken($companyBotToken);
        try {
            $update = new Update(json_decode(file_get_contents('php://input'), true));
            $contact = $this->telegramService->extractContactFromMessage(
                $companyId,
                $update
            );
            if (null === $contact) {
                logger('Exit from webhook because couldnt identify contact from update, see logs');
                return;
            }

            $update->setContact($contact);
            if ($update->isType('message') || $update->isType('callbackQuery')) {
                $this->telegram->processCommand($update);
                $this->telegramService->afterUpdateProcessed($update, $this->telegram);
            } else if ($update->isType('chat_member')) {
                $this->logger->debug('chat member action here');
            }
        } finally {
            $this->telegram->setAccessToken('empty');
        }
    }

    public function assignPermissionsToController(): void
    {
        // do nothing
    }

    public function __destruct()
    {
        Company::forgetCurrent();
    }
}
