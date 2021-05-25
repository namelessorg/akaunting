<?php

declare(strict_types=1);

namespace App\Lib\Telegram;

use App\Lib\Telegram\Models\ChatLink;
use Telegram\Bot\Api;

class TelegramApi extends Api
{
    /**
     * @param          $chatId
     * @param int|null $memberLimit
     * @param int|null $expireDate
     * @return ChatLink
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException
     */
    public function createChatInviteLink($chatId, ?int $memberLimit = null, ?int $expireDate = null): ChatLink
    {
        $params = [
            'chat_id' => $chatId,
        ];
        if (null !== $memberLimit) {
            $params['member_limit'] = $memberLimit;
        }
        if (null !== $expireDate) {
            $params['expire_date'] = $expireDate;
        }

        $response = $this->post('createChatInviteLink', $params);

        return new ChatLink($response->getDecodedBody());
    }
}
