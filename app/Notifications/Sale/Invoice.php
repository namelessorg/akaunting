<?php

namespace App\Notifications\Sale;

use App\Abstracts\Notification;
use App\Models\Common\EmailTemplate;
use App\Models\Document\Document;
use App\Traits\Documents;
use Illuminate\Support\Facades\URL;

class Invoice extends Notification
{
    use Documents;

    /**
     * The invoice model.
     *
     * @var object|Document
     */
    public $invoice;

    /**
     * The email template.
     *
     * @var string
     */
    public $template;

    /**
     * Should attach pdf or not.
     *
     * @var bool
     */
    public $attach_pdf;

    /**
     * Create a notification instance.
     *
     * @param  object  $invoice
     * @param  object  $template_alias
     * @param  object  $attach_pdf
     */
    public function __construct($invoice = null, $template_alias = null, $attach_pdf = false)
    {
        parent::__construct();

        $this->invoice = $invoice;
        $this->template = EmailTemplate::alias($template_alias)->first();
        $this->attach_pdf = $attach_pdf;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $message = $this->initMessage();

        // Attach the PDF file
        if ($this->attach_pdf) {
            $message->attach($this->storeInvoicePdfAndGetPath($this->invoice), [
                'mime' => 'application/pdf',
            ]);
        }

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'invoice_id' => $this->invoice->id,
            'amount' => $this->invoice->amount,
        ];
    }

    public function getTags()
    {
        return [
            '{invoice_number}',
            '{invoice_total}',
            '{invoice_amount_due}',
            '{invoice_due_date}',
            '{invoice_guest_link}',
            '{invoice_admin_link}',
            '{invoice_portal_link}',
            '{customer_name}',
            '{company_name}',
            '{company_email}',
            '{company_tax_number}',
            '{company_phone}',
            '{company_address}',
        ];
    }

    public function getTagsReplacement()
    {
        return [
            $this->invoice->document_number,
            money($this->invoice->amount, $this->invoice->currency_code, true),
            money($this->invoice->amount_due, $this->invoice->currency_code, true),
            company_date($this->invoice->due_at),
            $this->getSignedUrl(),
            route('invoices.show', ['invoice' => $this->invoice->id, 'company_id' => $this->invoice->company->id]),
            route('portal.invoices.show', ['invoice' => $this->invoice->id, 'company_id' => $this->invoice->company->id]),
            $this->invoice->contact_name,
            $this->invoice->company->name,
            $this->invoice->company->email,
            $this->invoice->company->tax_number,
            $this->invoice->company->phone,
            nl2br(trim($this->invoice->company->address)),
        ];
    }

    protected function getSignedUrl()
    {
        $type = $this->invoice->type;
        $page = config('type.' . $type . '.route.prefix');
        $alias = config('type.' . $type . '.alias');

        $route = '';

        if (!empty($alias)) {
            $route .= $alias . '.';
        }

        $route .= 'signed.' . $page . '.show';

        try {
            route($route, ['invoice' => $this->invoice->id, 'company_id' => company_id()]);

            $signedUrl = URL::signedRoute(
                $route,
                ['invoice' => $this->invoice->id, 'company_id' => $this->invoice->company->id],
                $this->invoice->due_at
            );
        } catch (\Exception $e) {
            $signedUrl = URL::signedRoute(
                'signed.invoices.show',
                ['invoice' => $this->invoice->id, 'company_id' => $this->invoice->company->id],
                $this->invoice->due_at
            );
        }

        return $signedUrl;
    }
}
