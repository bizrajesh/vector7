<?php

namespace App\Support;

use App\Services\AppSettings;

/**
 * Who may create an account from the marketplace. Sign-in always works; sign-up for each kind of account
 * is switched on by the App Admin (App → Settings → Sign-ups). All three are closed until switched on.
 */
class Signups
{
    public const KINDS = [
        'buyer' => 'Buyers',
        'lister' => 'Property listers',
        'promoter' => 'Layout promoters',
    ];

    public static function open(string $kind): bool
    {
        return array_key_exists($kind, self::KINDS) && (string) AppSettings::get('signup.'.$kind, '0') === '1';
    }

    public static function anyOpen(): bool
    {
        foreach (array_keys(self::KINDS) as $k) {
            if (self::open($k)) {
                return true;
            }
        }

        return false;
    }
}
