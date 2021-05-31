<?php

declare(strict_types=1);

namespace App\Console\Telegram;

use App\Jobs\Document\CreateDocument;
use App\Lib\Telegram\Update;
use App\Models\Common\Company;
use App\Models\Common\Contact;
use App\Models\Common\Item;
use App\Models\Document\Document;
use App\Services\TelegramService;
use App\Traits\Documents;
use App\Traits\Jobs;
use Illuminate\Support\Carbon;
use Telegram\Bot\Exceptions\TelegramResponseException;

class SubscribeTelegramCommand extends AbstractTelegramCommand
{
    use Jobs, Documents;

    protected const INVOICES_PER_DAY_LIMIT = 10;

    /**
     * @var string Command Name
     */
    protected $name = "subscribe";

    /**
     * @var string Command Description
     */
    protected $description = "Subscribe Command to get bill";

    public function run(Contact $contact, Update $update): void
    {
        $id = explode(' ', ltrim($this->getUpdate()->callbackQuery->data, '/'))[1] ?? null;

        try {
            $this->telegram->answerCallbackQuery([
                'callback_query_id' => $this->getUpdate()->callbackQuery->id,
            ]);
        } catch (TelegramResponseException $e) {
            // do nothing by default
        }

        /** @var Item $item */
        if (null === $id || !$item = Item::find($id)) {
            $this->triggerCommand('start');
            return;
        }

        $contact->company->makeCurrent();
        $invoicesToday = $contact->invoices()->where('created_at', '>', Carbon::yesterday())->where('created_at', '<', Carbon::now())->count();
        if ($invoicesToday > self::INVOICES_PER_DAY_LIMIT) {
            logger('Too many invoices');
            return;
        }

        /** @var Document $invoice */
        $invoice = $this->dispatch(new CreateDocument([
            'type' => 'invoice',
            'status' => 'sent',
            'due_at' => Carbon::tomorrow()->format('Y-m-d H:i:s'),
            'issued_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'currency_code' => 'USD',
            'contact_id' => $contact->id,
            'company_id' => $contact->company->id,
            'contact_name' => sprintf('%s (@%s)', $contact->name, $this->getUpdate()->callbackQuery->from->username),
            'category_id' => $item->category->id,
            'currency_rate' => 1,
            'items' => [[
                'item_id' => $item->id,
                'name' => $item->name,
                'price' => $item->sale_price,
                'quantity' => 1,
            ]],
            'item_backup' => [$item->attributesToArray(),],
            'document_number' => $this->getNextDocumentNumber(Document::INVOICE_TYPE),
        ]));

        app(TelegramService::class)->sendInvoice($invoice, $contact);
    }

    public function __destruct()
    {
        Company::forgetCurrent();
    }
}
