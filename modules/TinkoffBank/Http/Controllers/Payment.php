<?php

namespace Modules\TinkoffBank\Http\Controllers;

use App\Abstracts\Http\PaymentController;
use App\Events\Document\DocumentCancelled;
use App\Events\Document\PaymentReceived;
use App\Http\Requests\Portal\InvoicePayment as PaymentRequest;
use App\Models\Common\Company;
use App\Models\Document\Document;
use App\Models\Document\DocumentItem;
use App\Scopes\Company as CompanyScope;
use App\Services\CurrenciesService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use Modules\TinkoffBank\Lib\TinkoffSDK;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        $initParams = [
            'OrderId' => $invoice->id,
            'SuccessURL' => URL::signedRoute('signed.tinkoff-bank.invoices.success', ['invoice' => $invoice->id, 'company_id' => $invoice->company_id,]),
            'FailURL' => URL::signedRoute('signed.tinkoff-bank.invoices.fail', ['invoice' => $invoice->id, 'company_id' => $invoice->company_id,]),
            'NotificationURL' => route('tinkoff-bank.invoices.notification'),
            "Amount" => bcmul($this->currenciesService->convert($invoice->amount, $invoice->currency_code, 'RUB'), '100'),
            'Language' => 'en',
            'IP' => $request->ip(),
            'RedirectDueDate' => now()->modify('+10 minute')->format(DATE_ATOM),
        ];

        $items = [];
        foreach($invoice->items()->cursor() as $item) {
            /** @var DocumentItem $item */
            $items[] = [
                "Name" => mb_substr($item->name, 0, 64),
                "Price" => (int) bcmul($this->currenciesService->convert($item->price, $invoice->currency_code, 'RUB'), '100'),
                "Quantity" => (float) $item->quantity,
                "Amount" => (int) bcmul($this->currenciesService->convert($item->total, $invoice->currency_code, 'RUB') * $item->quantity, '100'),
                "PaymentObject" => 'service',
                "PaymentMethod" => 'full_payment',
                "Tax" => 'none',
            ];
        }

        $initParams['Receipt'] = [
            'Email' => $invoice->contact->id . '-contact@' . parse_url(config('app.url'), PHP_URL_HOST),
            'Taxation' => $setting['taxation'] ?? 'patent',
            'Items' => $items,
        ];
        logger('Sending request to tinkoff', [
            'company' => $invoice->company->id,
            'invoice' => $invoice->id,
            'Init' => $initParams,
        ]);
        $tinkoff = $this->getSdk($invoice->company, $setting);
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

        $this->setContactFirstLastName($invoice);

        return response()->json([
            'code' => $setting['code'],
            'name' => $setting['name'] ?? 'Credit Card',
            'description' => trans('tinkoff-bank::general.description'),
            'redirect' => false,
            'html' => $html,
        ]);
    }

    // fail url
    public function fail(Document $invoice)
    {
        flash(trans('messages.error.added', ['type' => strtolower(trans_choice('general.payments', 1))]))->warning();
        $invoice_url = $this->getInvoiceUrl($invoice);

        return redirect($invoice_url);
    }

    // success  url
    public function success(Document $invoice, Request $request)
    {
        if ($invoice->status !== 'paid') {
            try {
                if ($this->makeTinkoffCheck($invoice, $request)) {
                    flash(trans('messages.success.success', ['type' => trans_choice('general.payments', 1)]))->success();
                } else {
                    flash(trans('messages.error.payment_cancelled', ['type' => strtolower(trans_choice('general.payments', 1))]))->warning();
                }
                $this->getSdk($invoice->company, $this->setting)->buildQuery('Resend', []);
            } catch (\Throwable $e) {
                info('Error on success payment: ' . $e->getMessage(), ['e' => $e,]);
            }
        }

        $invoice_url = $this->getInvoiceUrl($invoice);

        return redirect($invoice_url);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     * @throws \JsonException
     */
    public function notification(Request $request)
    {
        \Validator::make($request->all(), [
            'OrderId' => 'required|numeric|exists:App\Models\Document\Document,id',
        ])->validate();

        $this->complete((new Document)->newQueryWithoutScope(CompanyScope::class)->findOrFail($request->get('OrderId')), $request);

        return 'OK';
    }

    public function complete(Document $invoice, Request $request)
    {
        info('Incoming request from tinkoff', [
            'ip' => $request->ips(),
            'request' => $request->all(),
            'method' => $request->method(),
            'invoice' => $invoice->id ?? null
        ]);
        if (!$invoice->id) {
            throw new NotFoundHttpException('Invoice not found');
        }

        if (!$invoice->order_number) {
            throw new NotFoundHttpException('Order number not found');
        }


        $this->makeTinkoffCheck($invoice, $request);

        return 'OK';
    }

    public function getSdk(Company $company, array $setting): TinkoffSDK
    {
        return $this->sdk[$company->id] ?? $this->sdk[$company->id] = new TinkoffSDK(
                $setting['terminal_key'] ?? null,
                $setting['secret_key'] ?? null
            );
    }

    private function makeTinkoffCheck(Document $invoice, Request $request): bool
    {
        $invoice->company->makeCurrent(true);

        $tinkoff = $this->getSdk($invoice->company, setting($this->alias));
        $state = $tinkoff->getState([
            'PaymentId' => $invoice->order_number,
        ]);

        $responseJson = json_decode($state, true, 512, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        info('Got response from tinkoff about payment state', [
            'company' => $invoice->company->id,
            'invoice' => $invoice->id,
            'response' => $responseJson,
        ]);

        $isOk = $responseJson['Status'] === 'CONFIRMED' && true === (bool)$responseJson['Success'];
        if ($isOk) {
            info('Payment received', ['invoice' => $invoice->id,]);
            if ($invoice->status !== 'paid') {
                try {
                    event(new PaymentReceived($invoice, $request->merge(['type' => 'income'])));
                } catch (\Throwable $e) {
                    info('Error on payment received: ' . $e->getMessage());
                }
            }
        } else {
            if ($invoice->status !== 'cancelled') {
                try {
                    event(new DocumentCancelled($invoice));
                } catch (\Throwable $e) {
                    info('Error on document cancelled: ' . $e->getMessage());
                }
            }
            info('Payment did not received, status not eq true', ['invoice' => $invoice->id,]);
        }

        return $isOk;
    }
}
