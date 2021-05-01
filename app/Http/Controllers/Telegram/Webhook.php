<?php

namespace App\Http\Controllers\Telegram;

use App\Abstracts\Http\Controller;
use App\Models\Common\Company;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Telegram\Bot\Api;

class Webhook extends Controller
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct();
        $this->logger = $logger;
    }

    public function handle(Request $request, Company $companyId, string $token): void
    {
        $this->logger->debug('Incoming request', [
            'data' => $request->all(),
            'client_ip' => $request->ip(),
        ]);

        $companyId->makeCurrent(true);
        $companyBotToken = setting('company.telegram_observer_token');
        $channelId = setting('company.telegram_channel_id');
        if (!hash_equals($token, $companyBotToken)) {
            $this->logger->warning('Unexpected observer token', [
                'expected' => $companyBotToken,
                'actual' => $token,
            ]);
            return;
        }

        $telegram = new Api($companyBotToken);
        $telegram->getWebhookUpdate(false);
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
