@component('mail::message')

    @component('mail::panel')
        {!! $emailBody !!}
    @endcomponent

    Thanks,
    {{ config('app.name') }}
@endcomponent