<?php

declare(strict_types=1);

namespace App\Lib\Telegram;

use App\Models\Common\Contact;
use Telegram\Bot\Objects\Update as BaseUpdate;

class Update extends BaseUpdate
{
    /**
     * @var Contact
     */
    protected $contact;

    protected $isProcessed = false;

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
