<?php

declare(strict_types=1);

namespace App\Services;

use App\Lib\Telegram\Update;
use App\Models\Common\Company;
use App\Models\Common\Contact;
use App\Models\Document\Document;
use App\Notifications\Sale\Invoice as Notification;
use Illuminate\Database\Query\Builder;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramResponseException;
use Telegram\Bot\Objects\Update as UpdateObject;

class TelegramService
{
    /**
     * @var Api
     */
    private $telegram;

    public function __construct(Api $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * @param string $companyBotToken
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException
     */
    public function setWebhook(string $companyBotToken): void
    {
        $this->telegram->setAccessToken($companyBotToken);
        if (!$this->telegram->setWebhook([
            'url' => route('webhook_url', ['token' => $companyBotToken,], true),
            'allowed_updates' => ['message'],
        ])) {
            $response = $this->telegram->getLastResponse();
            if ($response && $response->isError()) {
                $response->throwException();
            }

            throw new UnprocessableEntityHttpException(
                'Unable to setup a webhook'
            );
        }
    }

    public function extractContactFromMessage(Company $company, UpdateObject $update): ?Contact
    {
        $company->makeCurrent();
        switch ($update->detectType()) {
            case 'callback_query':
                $message = $update->callbackQuery;
                $id = $message->from->id;
                $username = $message->from->username;
                $firstName = $message->from->firstName;
                $lastName = $message->from->lastName;
                break;
            case 'message':
                $message = $update->getMessage();
                $id = $message->from->id;
                $username = $message->from->username;
                $firstName = $message->from->firstName;
                $lastName = $message->from->lastName;
                break;
            default:
                logger('Undefined update state `'.$update->detectType().'`', [
                    'update' => $update->toArray(),
                ]);
                return null;
        }

        return $this->refreshUserByUpdate($company, $id, $username, $firstName, $lastName);
    }

    public function afterUpdateProcessed(Update $update, Api $telegram): void
    {
        if ($update->isProcessed()) {
            return;
        }

        $contact = $update->getContact();
        if (isset($contact->last_command['name'])) {
            $telegram->triggerCommand($contact->last_command['name'], $update, $contact->last_command['entity'] ?? []);
        } elseif ($update->callbackQuery && is_scalar($update->callbackQuery->data)) {
            $telegram->triggerCommand(explode(' ', ltrim($update->callbackQuery->data ?? '', '/'))[0] ?? '', $update);
        }
    }

    public function sendInvoice(Document $document, Contact $user): void
    {
        $this->telegram->setAccessToken($document->company->telegram_observer_token);
        try {
            $this->telegram->sendMessage([
                'chat_id' => $user->telegram_chat_id,
                'text' => (new Notification($document, 'invoice_new_customer', false))->getTelegramBody(),
                'parse_mode' => 'HTML'
            ]);
        } finally {
            $this->telegram->setAccessToken('empty');
        }
    }

    public function addUser(Contact $user, Company $company, string $additionalText = ''): bool
    {
        $this->telegram->setAccessToken($company->telegram_observer_token);
        try {
            $result = $this->telegram->unbanChatMember([
                'chat_id' => $company->telegram_channel_id,
                'user_id' => $user->telegram_chat_id,
            ]);
            if ($result) {
                $this->telegram->sendMessage([
                    'chat_id' => $user->telegram_chat_id,
                    'text' => trim($additionalText . "\r\n\r\nInvite access link: " . $this->telegram->exportChatInviteLink(['chat_id' => $company->telegram_channel_id]))
                ]);
            }
            return $result;
        } catch (TelegramResponseException $e) {
            $body = $e->getResponse()->getDecodedBody();
            if (isset($body['description']) && 0 === strpos($body['description'], 'Forbidden: bot can\'t initiate conversation with a user')) {
                throw new UnprocessableEntityHttpException("User {$user->name} ({$user->telegram_id}) should write any message to our bot");
            }

            throw $e;
        } finally {
            $this->telegram->setAccessToken('empty');
        }
    }

    public function kick(Contact $user, Company $company): bool
    {
        $this->telegram->setAccessToken($company->telegram_observer_token);
        try {
            return $this->telegram->kickChatMember([
                'chat_id' => $company->telegram_channel_id,
                'user_id' => $user->telegram_chat_id,
            ]);
        } finally {
            $this->telegram->setAccessToken('empty');
        }
    }

    public function refreshUserByUpdate(Company $company, int $id, ?string $username, ?string $firstName, ?string $lastName): Contact
    {
        /** @var Contact $user */
        $user = $company->customers()->whereNested(function (Builder $builder) use ($id, $username) {
            if ($username && strlen($username) > 1) {
                $builder->orWhere('telegram_id', $username);
            }
            $builder->orWhere('telegram_chat_id', $id);
        })->first();

        if (null === $user) {
            $user = $company->customers()->newModelInstance([
                'enabled' => 0,
                'expired_at' => now(),
                'company_id' => $company->id,
                'type' => 'customer',
                'currency_code' => 'USD'
            ]);
        }

        if (empty($user->telegram_id)) {
            $user->telegram_id = $username;
        }
        if (empty($user->telegram_chat_id)) {
            $user->telegram_chat_id = $id;
        }
        if ($firstName || $lastName) {
            $user->name = trim($firstName . ' ' . $lastName);
        }

        $user->save();

        return $user;
    }
}
