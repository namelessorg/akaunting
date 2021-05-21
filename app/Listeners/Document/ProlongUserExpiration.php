<?php

declare(strict_types=1);

namespace App\Listeners\Document;

use App\Events\Document\PaymentReceived as Event;
use App\Models\Common\Contact;
use App\Models\Common\Item;
use App\Models\Document\Document;
use App\Models\Document\DocumentItem;
use App\Models\Setting\Category;
use App\Services\TelegramService;

class ProlongUserExpiration
{
    /**
     * Handle the event.
     *
     * @param  $event
     * @return void
     */
    public function handle(Event $event)
    {
        if ($event->request['type'] !== 'income') {
            return;
        }

        /** @var Document $document */
        $document = $event->document;
        /** @var Contact $contact */
        $contact = $document->contact;
        $message = "Thank you for purchase! Use /start for check subscription's status.";

        foreach ($document->items()->cursor() as $documentItem) {
            if (!$documentItem instanceof DocumentItem) {
                continue;
            }

            $item = $documentItem->item;
            if (!$item instanceof Item) {
                continue;
            }

            if (!empty($item->description)) {
                $message .= "\r\n\r\n" . $item->description;
            }

            $category = $item->category;
            if (!$category instanceof Category) {
                continue;
            }

            $buff = Category::getTypeAndArgumentByCategoryName((string) $category->name);
            if (empty($buff)) {
                logger('Empty buff on paid event', [
                    'payment_document' => $document->id,
                    'category_name' => $category->name,
                ]);
                continue;
            }

            [$command, $args] = $buff;
            switch ($command) {
                case 'user':
                    $contact->expires_at = $args;
                    $contact->save();
                    break;
                default:
                    logger('Undefined command by category paid', [
                        'command' => $command,
                        'args' => $args,
                        'payment_document' => $document->id,
                        'category_name' => $category->name,
                    ]);
            }
        }

        app(TelegramService::class)->addUser($contact, $contact->company, $message);

    }
}
