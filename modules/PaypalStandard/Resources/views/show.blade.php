<div>
    <div class="d-none">
        @if (!empty($setting['name']))
            <h2>{{ $setting['name'] }}</h2>
        @endif

        @if ($setting['mode'] == 'sandbox')
            <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> {{ trans('paypal-standard::general.test_mode') }}</div>
        @endif

        <div class="well well-sm">
            {{ trans('paypal-standard::general.description') }}
        </div>
    </div>
    <br>

    <div class="buttons">
        <div class="pull-right text-center">
            <form action="{{ $setting['action'] }}" method="post">
                <input type="hidden" name="cmd" value="_cart" />
                <input type="hidden" name="upload" value="1" />
                <input type="hidden" name="business" value="{{ $setting['email'] }}" />
                <?php $i = 1; ?>
                @foreach ($invoice->items as $item)
                    <input type="hidden" name="item_name_{{ $i }}" value="{{ $item->name }}" />
                    <input type="hidden" name="amount_{{ $i }}" value="{{ $item->price }}" />
                    <input type="hidden" name="quantity_{{ $i }}" value="{{ $item->quantity }}" />
                    <?php $i++; ?>
                @endforeach
                <input type="hidden" name="currency_code" value="{{ $invoice->currency_code}}" />
                <input type="hidden" name="first_name" value="{{ $invoice->first_name }}" />
                <input type="hidden" name="last_name" value="{{ $invoice->last_name }}" />
                <input type="hidden" name="address1" value="{{ $invoice->contact_address ?? $invoice->company->address }}" />
                <input type="hidden" name="address_override" value="0" />
                <input type="hidden" name="invoice" value="{{ $invoice->id }}" />
                <input type="hidden" name="lc" value="{{ $setting['language'] }}" />
                <input type="hidden" name="rm" value="2" />
                <input type="hidden" name="no_note" value="1" />
                <input type="hidden" name="no_shipping" value="1" />
                <input type="hidden" name="charset" value="utf-8" />
                <input type="hidden" name="return" value="{{ route('portal.paypal-standard.invoices.return', $invoice->id) }}" />
                <input type="hidden" name="notify_url" value="{{ route('portal.paypal-standard.invoices.complete', $invoice->id) }}" />
                <input type="hidden" name="cancel_return" value="{{ $invoice_url }}" />
                <input type="hidden" name="paymentaction" value="{{ $setting['transaction'] }}" />
                <input type="hidden" name="custom" value="{{ $invoice->id }}" />
                <input type="hidden" name="hosted_button_id" value="{{preg_replace('/([^[a-z0-9])/i', '-', $invoice->company->name??'smthn_spcl')}}" />

                <input type="submit" value="{{ trans('general.pay') }}" class="btn btn-success" />
            </form>
        </div>
    </div>
</div>
