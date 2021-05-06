<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Common\Company;
use App\Models\Common\Contact;
use Illuminate\Database\Query\Builder;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramResponseException;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Message;
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

    public function handleUpdate(Company $company, Api $telegram, UpdateObject $update): void
    {
        $company->makeCurrent();
        switch ($update->detectType()) {
            case 'message':
                $message = $update->getMessage();
                break;
            default:
                logger('Undefined update state `'.$update->detectType().'`', [
                    'update' => $update->toArray(),
                ]);
                return;
        }

        $contact = $this->refreshUserByUpdate($message, $company);
    }

    public function addUser(Contact $user, Company $company): bool
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
                    'text' => $this->telegram->exportChatInviteLink(['chat_id' => $company->telegram_channel_id])
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

    protected function refreshUserByUpdate(Message $message, Company $company): Contact
    {
        /** @var Contact $user */
        $username = $message->from->username;
        $user = $company->customers()->whereNested(function (Builder $builder) use ($message) {
            if (strlen($message->from->username) > 1) {
                $builder->orWhere('telegram_id', $message->from->username);
            }
            $builder->orWhere('telegram_chat_id', $message->from->id);
        })->get();

        if (null === $user) {
            $user = $company->customers()->newModelInstance([
                'enabled' => 0,
                'expired_at' => now(),
            ]);
        }

        if (empty($user->telegram_id)) {
            $user->telegram_id = $username;
        }
        if (empty($user->telegram_chat_id)) {
            $user->telegram_chat_id = $message->from->id;
        }
        if (!empty($message->from->firstName) || !empty($message->from->lastName)) {
            $user->name = trim($message->from->firstName . ' ' . $message->from->lastName);
        }

        $user->save();

        return $user;
    }
}
