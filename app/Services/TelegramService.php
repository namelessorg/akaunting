<?php

declare(strict_types=1);

namespace App\Services;

use App\Lib\Telegram\Models\ChatLink;
use App\Lib\Telegram\Update;
use App\Models\Common\Company;
use App\Models\Common\Contact;
use App\Models\Document\Document;
use App\Notifications\Sale\Invoice as Notification;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramResponseException;
use Telegram\Bot\Objects\Update as UpdateObject;
use Telegram\Bot\Objects\User;

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
            'allowed_updates' => json_encode(['message', 'callback_query', 'chat_member']),
        ])) {
            $response = $this->telegram->getLastResponse();
            if ($response && $response->isError()) {
                $response->throwException();
            }

            throw new UnprocessableEntityHttpException(
                'Unable to setup a webhook'
            );
        }

        logger('Reinstall webhook for token ' . substr($companyBotToken, 0, 15) . '<...>', [
            'webhook_info' => $this->telegram->getWebhookInfo()
        ]);
    }

    public function extractContactFromMessage(Company $company, Update $update): ?Contact
    {
        $company->makeCurrent();
        $website = null;

        if ($update->isType('callback_query')) {
            $message = $update->callbackQuery;
            $id = $message->from->id;
            $username = $message->from->username;
            $firstName = $message->from->firstName;
            $lastName = $message->from->lastName;
            $chat = $update->getChat();
        } else if ($update->isType('message')) {
            $message = $update->getMessage();
            $chat = $update->getChat();
            // случай группового сообщения о том, что чел вступил в группу
            if (isset($message->newChatMembers[0]) && $newMember = $message->newChatMembers[0]) {
                $newMember = new User($newMember);
                $id = $newMember->id;
                $username = $newMember->username;
                $firstName = $newMember->firstName;
                $lastName = $newMember->lastName;
            } else {
                $id = $message->from->id;
                $username = $message->from->username;
                $firstName = $message->from->firstName;
                $lastName = $message->from->lastName;
            }
        } else if ($update->isType('chat_member')) {
            $chat = $update->chatMember->chat;
            $from = $update->chatMember->newChatMember->user;
            $id = $from->id;
            $username = $from->username;
            $firstName = $from->firstName;
            $lastName = $from->lastName;
            $website = $update->chatMember->inviteLink instanceof ChatLink ? $update->chatMember->inviteLink->inviteLink : null;
        } else {
            logger('Undefined update state `' . $update->detectType() . '`', [
                'update' => $update->toArray(),
            ]);
            return null;
        }

        if ('private' !== $chat->type && !in_array($chat->id, $company->getAvailableChannels(), true)) {
            $e = null;
            try {
                $this->telegram->sendMessage([
                    'chat_id' => $chat->id,
                    'text' => 'Sorry, but this chat_id "' . $chat->id . '" is unexpected. Add him in admin panel',
                ]);
            } catch (\Throwable $e) {}
            logger('Got event chat_member from unobserved chat_id', [
                'update' => $update->toArray(),
                'expected_chat_id' => $company->getAvailableChannels(),
                'actual_chat_id' => $chat->id,
                'e' => $e ? $e->getMessage() . $e->getTrace() : null,
            ]);
            return null;
        }

        return $this->refreshUserByUpdate($company, $id, $username, $firstName, $lastName, $website);
    }


    public function afterMemberUpdateProcessed(Update $update, Api $telegram): void
    {
        $contact = $update->getContact();
        $chat = $update->chatMember;
        //$this->updateContactEnableByStatus($contact, $chat->newChatMember->status ?? null);
    }

    public function afterUpdateProcessed(Update $update, Api $telegram): void
    {
        if ($update->isProcessed()) {
            return;
        }

        $contact = $update->getContact();
        if (isset($contact->last_command['name'])) {
            $telegram->triggerCommand($contact->last_command['name'], $update, $contact->last_command['entity'] ?? []);
        }
        if ($update->isProcessed()) {
            return;
        }
        if ($update->callbackQuery && is_scalar($update->callbackQuery->data)) {
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

    /**
     * @param string $companyBotToken
     * @param int     $chatId
     * @return bool
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException
     */
    public function isAccessedChannel(string $companyBotToken, int $chatId): bool
    {
        $this->telegram->setAccessToken($companyBotToken);
        try {
            return null !== $this->telegram->getChatMember([
                    'chat_id' => $chatId,
                    'user_id' => $this->telegram->getMe()->id
                ]) && $this->telegram->getChat([
                    'chat_id' => $chatId,
                ])->type !== 'private';
        } catch (TelegramResponseException $e) {
            throw new BadRequestHttpException('Wrong chat_id=' . $chatId . '. Are you sure than you add the bot to this chat?');
        } finally {
            $this->telegram->setAccessToken('empty');
        }
    }

    public function addUser(Contact $user, Company $company, string $additionalText = ''): bool
    {
        $this->telegram->setAccessToken($company->telegram_observer_token);
        try {
            try {
                $this->telegram->unbanChatMember([
                    'chat_id' => $company->telegram_channel_id,
                    'user_id' => $user->telegram_chat_id,
                ]);
            } catch (TelegramResponseException $e) {
                logger('Error on add user ' . $e->getMessage(), ['customer' => $user->id, 'e' => $e,]);
            }

            /** @var ChatLink $accessLink */
            $accessLink = $this->telegram->createChatInviteLink(
                $company->telegram_channel_id,
                1,
                (new \DateTime('now +1 week'))->getTimestamp()
            );
            $this->telegram->sendMessage([
                'chat_id' => $user->telegram_chat_id,
                'text' => trim($additionalText . "\r\n\r\nInvite access link: " . $accessLink->inviteLink)
            ]);

            return true;
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

    public function refreshUserByUpdate(Company $company, int $id, ?string $username, ?string $firstName, ?string $lastName, ?string $website = null): Contact
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
                'currency_code' => 'USD',
                'website' => $website,
            ]);
            logger('Created new contact', ['contact' => $user,]);
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

    protected function updateContactEnableByStatus(Contact $contact, ?string $status): void
    {
        if (null === $status || !is_scalar($status)) {
            return;
        }
        switch ($status) {
            case 'kicked':
            case 'left':
                $contact->enabled = 0;
                break;
            case 'member':
                $contact->enabled = 1;
                break;
        }

        $contact->save();
        logger('Update contact state by telegram public channel status', [
            'contact' => $contact->id,
            'status' => $status,
            'enable' => (int)$contact->enabled,
        ]);
    }
}
