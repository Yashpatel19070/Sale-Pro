<x-mail::message>
# Reset Your Password

Hi {{ $customer->name }},

You are receiving this email because we received a password reset request for your account.

<x-mail::button :url="$resetUrl">
Reset Password
</x-mail::button>

This link expires in **60 minutes**.

If you did not request a password reset, no action is required.

Thanks,
{{ config('app.name') }}
</x-mail::message>
