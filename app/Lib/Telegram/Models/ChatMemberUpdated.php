<?php

declare(strict_types=1);

namespace App\Lib\Telegram\Models;

use Telegram\Bot\Objects\BaseObject;
use Telegram\Bot\Objects\Chat;
use Telegram\Bot\Objects\ChatMember;
use Telegram\Bot\Objects\User;

/**
 * Class ChatMemberUpdated
 *
 * @package App\Lib\Telegram\Models
 * @property Chat $chat
 * @property User $from
 * @property ChatLink $inviteLink
 * @property ChatMember $newChatMember
 * @property ChatMember $oldChatMember
 */
class ChatMemberUpdated extends BaseObject
{
    public function relations()
    {
        return [
            'chat' => Chat::class,
            'from' => User::class,
            'inviteLink' => ChatLink::class,
            'new_chat_member' => ChatMember::class,
            'old_chat_member' => ChatMember::class,
        ];
    }
}
