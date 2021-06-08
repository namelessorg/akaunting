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
                <input type="hidden" name="upload" value="1">
                <input type="hidden" name="business" value="{{ $setting['email'] }}" />
                <?php $i = 1; ?>
                @foreach ($invoice->items as $item)
                    <input type="hidden" name="item_number_{{ $i }}" value="{{ $item->id }}" />
                    <input type="hidden" name="item_name_{{ $i }}" value="{{ $item->name }}" />
                    <input type="hidden" name="amount_{{ $i }}" value="{{ $item->price }}" />
                    <input type="hidden" name="quantity_{{ $i }}" value="{{ $item->quantity }}" />
                    <?php $i++; ?>
                @endforeach
                <input type="hidden" name="currency_code" value="{{ $invoice->currency_code}}" />
                <input type="hidden" name="no_shipping" value="1" />
                <input type="hidden" name="lc" value="{{ $setting['language'] ?? 'EN' }}" />
                <input type="hidden" name="return" value="{{ route('portal.paypal-standard.invoices.return', $invoice->id) }}" />
                <input type="hidden" name="notify_url" value="{{ route('portal.paypal-standard.invoices.complete', $invoice->id) }}" />
                <input type="hidden" name="cancel_return" value="{{ $invoice_url }}" />
                <input type="hidden" name="paymentaction" value="{{ $setting['transaction'] }}" />

                <input type="submit" value="{{ trans('general.pay') }}" class="btn btn-success" />
            </form>
        </div>
    </div>
</div>
