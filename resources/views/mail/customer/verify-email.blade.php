<x-mail::message>
# Verify Your Email Address

Hi {{ $customer->name }},

Thanks for signing up. Please click the button below to verify your email address and activate your account.

<x-mail::button :url="$verificationUrl">
Verify Email Address
</x-mail::button>

This link expires in **60 minutes**.

If you did not create an account, no action is required.

Thanks,
{{ config('app.name') }}
</x-mail::message>
