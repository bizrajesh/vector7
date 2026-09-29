<?php

namespace App\Support;

/**
 * Indian number formatting helpers (₹1,23,456.00, lakh / crore short forms).
 */
final class Money
{
    public static function inr(float|string|null $amount, bool $paise = false): string
    {
        $amount = (float) $amount;
        $negative = $amount < 0;
        $amount = abs($amount);
        $whole = (string) floor($amount);
        $fraction = $paise ? '.'.str_pad((string) round(($amount - floor($amount)) * 100), 2, '0', STR_PAD_LEFT) : '';

        $last3 = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        if ($rest !== '' && $rest !== false) {
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $whole = $rest.','.$last3;
        }

        return ($negative ? '−' : '').'₹'.$whole.$fraction;
    }

    public static function short(float|string|null $amount): string
    {
        $amount = (float) $amount;
        $abs = abs($amount);

        return match (true) {
            $abs >= 1_00_00_000 => '₹'.rtrim(rtrim(number_format($amount / 1_00_00_000, 2), '0'), '.').' Cr',
            $abs >= 1_00_000 => '₹'.rtrim(rtrim(number_format($amount / 1_00_000, 2), '0'), '.').' L',
            default => self::inr($amount),
        };
    }
}
