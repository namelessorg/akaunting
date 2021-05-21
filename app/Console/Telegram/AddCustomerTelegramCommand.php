<?php

declare(strict_types=1);

namespace App\Console\Telegram;

use App\Services\TelegramService;

class AddCustomerTelegramCommand extends AbstractTelegramCommand
{
    /**
     * @var string Command Name
     */
    protected $name = 'add_user';

    public function run(): void
    {
        $user = $this->getContact()->user;
        $message = $this->getUpdate()->message;
        if (!$user || !$user->enabled || !$user->can('create-sales-customers')) {
            return;
        }

        if (!$message->forwardFromChat && !$message->contact) {
            $this->replyWithMessage([
                'chat_id' => $message->from->id,
                'text' => 'Forward one message from your dialog with new user. For example, select one message which was sent by customer who should be add',
            ]);

            return;
        }

        $telegramChatId = $message->forwardFrom->id ?? null;
        $telegramUserName = $message->forwardFrom->username ?? null;
        $lastName = $message->forwardFrom->lastName ?? null;
        $firstName = $message->forwardFrom->firstName ?? null;
        if (!$telegramChatId) {
            $this->replyWithMessage([
                'chat_id' => $message->from->id,
                'text' => "Invalid user's chat id, please, forward one message from him",
            ]);
            return;
        }
        if ($telegramChatId === $this->getContact()->telegram_chat_id) {
            $this->replyWithMessage([
                'chat_id' => $message->from->id,
                'text' => "It's your message, asshole. Forward me HIS message",
            ]);
            return;
        }

        $newCustomer = app(TelegramService::class)->refreshUserByUpdate(
            $this->getContact()->company,
            $telegramChatId,
            $telegramUserName,
            $firstName,
            $lastName
        );

        $this->isItEndOfDialog = true;

        $this->replyWithMessage([
            'chat_id' => $message->from->id,
            'text' => "New customer#{$newCustomer->id} successfully ended: " . route('customers.show', $newCustomer->id),
        ]);
    }
}
