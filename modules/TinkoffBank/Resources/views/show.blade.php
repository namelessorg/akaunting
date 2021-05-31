<div>
    <div class="d-none">
        @if (!empty($setting['name']))
            <h2>{{ $setting['name'] }}</h2>
        @endif

        <div class="well well-sm">
            {{ trans('tinkoff-bank::general.description') }}
        </div>
    </div>
    <br>

    <div class="buttons">
        <div class="pull-right text-center">
            <form action="{{ $setting['action'] }}" method="post">
                <input type="submit" value="{{ trans('general.pay') }}" class="btn btn-success" />
            </form>
        </div>
    </div>
</div>
