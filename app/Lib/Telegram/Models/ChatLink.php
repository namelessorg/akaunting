<?php

declare(strict_types=1);

namespace App\Lib\Telegram\Models;

use Telegram\Bot\Objects\BaseObject;
use Telegram\Bot\Objects\User;

/**
 * Class ChatLink
 *
 * @package App\Lib\Telegram\ChatLink
 * @property string $inviteLink
 * @property int|null $memberLimit
 * @property bool $isPrimary
 * @property bool $isRevoked
 */
class ChatLink extends BaseObject
{
    public function relations()
    {
        return [
            'creator' => User::class
        ];
    }
}
