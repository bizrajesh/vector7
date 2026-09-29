<?php

namespace App\Services;

use App\Mail\PlainNotification;
use App\Models\EmailOtp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Email one-time codes (online booking, booking tracking).
 * 6 digits, HMAC-hashed at rest, 10-minute expiry, 5 attempts, single use (OWASP A07).
 */
class OtpService
{
    private const TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    public function send(string $email, string $purpose): void
    {
        $email = strtolower(trim($email));
        $code = (string) random_int(100000, 999999);

        // Invalidate earlier unused codes for the same purpose.
        EmailOtp::query()->where('email', $email)->where('purpose', $purpose)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        EmailOtp::create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => $this->hash($email, $purpose, $code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'ip_address' => request()->ip(),
        ]);

        try {
            Mail::to($email)->send(new PlainNotification(
                'Your Vector7 code: '.$code,
                "Your one-time code is {$code}. It expires in ".self::TTL_MINUTES." minutes. If you did not request it, ignore this email — never share the code with anyone."
            ));
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['email' => 'We could not send the code right now. Please try again in a few minutes.']);
        }
    }

    public function verify(string $email, string $purpose, string $code): void
    {
        $email = strtolower(trim($email));
        $otp = EmailOtp::query()->where('email', $email)->where('purpose', $purpose)
            ->whereNull('consumed_at')->latest('id')->first();

        $fail = fn () => throw ValidationException::withMessages(['code' => 'That code is incorrect or has expired. Request a new one.']);

        if (! $otp || $otp->expires_at->isPast() || $otp->attempts >= self::MAX_ATTEMPTS) {
            $fail();
        }

        if (! hash_equals($otp->code_hash, $this->hash($email, $purpose, trim($code)))) {
            $otp->increment('attempts');
            $fail();
        }

        $otp->forceFill(['consumed_at' => now()])->save();
    }

    private function hash(string $email, string $purpose, string $code): string
    {
        return hash_hmac('sha256', $email.'|'.$purpose.'|'.$code, (string) config('app.key'));
    }
}
