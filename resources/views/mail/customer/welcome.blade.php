<x-mail::message>
# Welcome, {{ $customer->name }}!

Your email is verified and your account is ready.

<x-mail::button :url="route('portal.dashboard')">
Go to Dashboard
</x-mail::button>

Thanks,
{{ config('app.name') }}
</x-mail::message>
