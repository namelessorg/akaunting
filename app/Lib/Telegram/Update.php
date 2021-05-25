<?php

declare(strict_types=1);

namespace App\Lib\Telegram;

use App\Lib\Telegram\Models\ChatMemberUpdated;
use App\Models\Common\Contact;
use Telegram\Bot\Objects\Update as BaseUpdate;

/**
 * Class Update
 *
 * @package App\Lib\Telegram
 * @property ChatMemberUpdated $chatMember
 */
class Update extends BaseUpdate
{
    /**
     * @var Contact
     */
    protected $contact;

    protected $isProcessed = false;

    public function relations()
    {
        return array_replace(
            parent::relations(),
            [
                'chat_member' => ChatMemberUpdated::class,
            ],
        );
    }

    /**
     * @param Contact $contact
     */
    public function setContact(Contact $contact): void
    {
        $this->contact = $contact;
    }

    /**
     * @return Contact
     */
    public function getContact(): Contact
    {
        return $this->contact;
    }

    /**
     * @param bool $isProcessed
     */
    public function setIsProcessed(bool $isProcessed): void
    {
        $this->isProcessed = $isProcessed;
    }

    /**
     * @return bool
     */
    public function isProcessed(): bool
    {
        return $this->isProcessed;
    }
}
