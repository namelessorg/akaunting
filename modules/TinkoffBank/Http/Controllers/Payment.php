<?php

namespace Modules\TinkoffBank\Http\Controllers;

use App\Abstracts\Http\PaymentController;
use App\Events\Document\PaymentReceived;
use App\Http\Requests\Portal\InvoicePayment as PaymentRequest;
use App\Models\Common\Company;
use App\Models\Common\Item;
use App\Models\Document\Document;
use App\Models\Document\DocumentItem;
use App\Services\CurrenciesService;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Modules\TinkoffBank\Lib\TinkoffSDK;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class Payment extends PaymentController
{
    public $alias = 'tinkoff-bank';

    public $type = 'redirect';

    private $sdk;

    private $currenciesService;

    public function __construct(CurrenciesService $currenciesService)
    {
        parent::__construct();
        $this->currenciesService = $currenciesService;
    }

    public function show(Document $invoice, PaymentRequest $request)
    {
        $setting = $this->setting;

        if ($invoice->status === 'paid' || $invoice->status === 'cancelled') {
            return response()->json([
                'html' => '<div class="alert alert-info"><i class="fa fa-exclamation-circle"></i> Already paid.</div>',
            ]);
        }

        $this->setContactFirstLastName($invoice);

        $initParams = [
            'OrderId' => $invoice->id,
            'SuccessURL' => route('portal.tinkoff-bank.invoices.success', $invoice->id),
            'FailURL' => route('portal.tinkoff-bank.invoices.fail', $invoice->id),
            'NotificationURL' => route('portal.tinkoff-bank.invoices.fail', $invoice->id),
            'DATA' => [
                'telegram_channel_buy' => $invoice->company->telegram_channel_id,
                'telegram_buyer_nickname' => $invoice->contact->telegram_id,
                'telegram_buyer_id' => $invoice->contact->telegram_chat_id,
            ],
            'Language' => 'en',
            'IP' => $request->ip(),
        ];

        $items = [];
        foreach($invoice->items()->cursor() as $item) {
            /** @var DocumentItem $item */
            $items = [
                "Name" => mb_substr($item->name, 0, 64),
                "Price" => (int) bcmul($this->currenciesService->convert($item->price, $invoice->currency_code, 'RUB'), '100'),
                "Quantity" => $item->quantity,
                "Amount" => (int) bcmul($this->currenciesService->convert($item->total, $invoice->currency_code, 'RUB'), '100'),
                "PaymentObject" => 'service',
                "Tax" => 'none',
            ];
        }

        $initParams['Receipt'] = [
            'Email' => $invoice->contact->id . '-contact@' . parse_url(config('app.url'), PHP_URL_HOST),
            'Taxation' => $setting['taxation'] ?? 'patent',
            'Items' => $items,
        ];

        $tinkoff = $this->getSdk($invoice->company);
        $responseJson = $tinkoff->buildQuery('Init', $initParams);
        $responseJson = json_decode($responseJson, true, 512, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        logger('Got response from tinkoff', [
            'company' => $invoice->company->id,
            'invoice' => $invoice->id,
            'response' => $responseJson,
        ]);
        if (!isset($responseJson['PaymentURL'], $responseJson['PaymentId'])) {
            return response()->json([
                'html' => '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> Service temporary unavailable: terminal does not configure.</div>'
            ]);
        }

        $invoice->order_number = $responseJson['PaymentId'];
        $invoice->save();

        $setting['action'] = $responseJson['PaymentURL'];

        $invoice_url = $this->getInvoiceUrl($invoice);

        $html = view('tinkoff-bank::show', compact('setting', 'invoice', 'invoice_url'))->render();

        return response()->json([
            'code' => $setting['code'],
            'name' => $setting['name'] ?? 'Credit Card',
            'description' => trans('tinkoff-bank::general.description'),
            'redirect' => false,
            'html' => $html,
        ]);
    }

    // fail url
    public function fail(Document $invoice, Request $request)
    {
        flash(trans('messages.error.added', ['type' => trans_choice('general.payments', 1)]))->warning();
        $invoice_url = $this->getInvoiceUrl($invoice);

        return redirect($invoice_url);
    }

    // success  url
    public function success(Document $invoice, Request $request)
    {
        flash(trans('messages.success.added', ['type' => trans_choice('general.payments', 1)]))->success();

        $invoice_url = $this->getInvoiceUrl($invoice);

        return redirect($invoice_url);
    }

    public function complete(Document $invoice, Request $request)
    {
        $tksLogger = new Logger('Tinkoff');

        $tksLogger->pushHandler(new StreamHandler(storage_path('logs/tinkoff.log')), Logger::DEBUG);
        $tksLogger->info('Incoming request from tinkoff', [
            'ip' => $request->ips(),
            'request' => $request->all(),
            'method' => $request->method(),
            'invoice' => $invoice->id ?? null
        ]);

        if (!$invoice) {
            return;
        }
        if (!isset($request['OrderId'])) {
            $tksLogger->warning('OrderId is undefined from tinkoff');
            return;
        }
        if (!$invoice->order_number) {
            $tksLogger->warning('OrderNumber is undefined from us');
            return;
        }

        $tinkoff = $this->getSdk($invoice->company);
        $state = $tinkoff->getState([
            'PaymentId' => $invoice->order_number,
        ]);

        $responseJson = json_decode($state, true, 512, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $tksLogger->info('Got response from tinkoff about payment state', [
            'company' => $invoice->company->id,
            'invoice' => $invoice->id,
            'response' => $responseJson,
        ]);

        if (true === (bool)$responseJson['Success']) {
            $tksLogger->info('Payment received', ['invoice' => $invoice->id,]);
            event(new PaymentReceived($invoice, $request->merge(['type' => 'income'])));
        } else {
            $tksLogger->info('Payment did not received, status not eq true', ['invoice' => $invoice->id,]);
        }
    }

    public function getSdk(Company $company): TinkoffSDK
    {
        return $this->sdk[$company->id] ?? $this->sdk[$company->id] = new TinkoffSDK(
                $company->settings['tinkoff-bank.terminal_key'] ?? null,
                $company->settings['tinkoff-bank.secret_key'] ?? null
            );
    }
}
