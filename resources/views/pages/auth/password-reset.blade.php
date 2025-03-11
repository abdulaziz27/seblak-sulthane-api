@component('mail::message')
# Notifikasi Reset Password

Anda menerima email ini karena kami menerima permintaan reset password untuk akun Anda.

@component('mail::button', ['url' => $url])
Reset Password
@endcomponent

Tautan reset password ini akan kedaluwarsa dalam {{ config('auth.passwords.users.expire') }} menit.

Jika Anda tidak meminta reset password, tidak ada tindakan lebih lanjut yang diperlukan.

Terima kasih,<br>
{{ config('app.name') }}
@endcomponent
