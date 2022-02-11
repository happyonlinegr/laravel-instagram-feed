@component('mail::message')
# Unable to fetch the feed

@if(!$has_auth)
authentication failed.
@else
general error.
@endif

@isset($error_message)
{{ $error_message }}
@endisset

Thanks,<br>
{{ config('app.name') }}
@endcomponent