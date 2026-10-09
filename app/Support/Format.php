<?php

namespace App\Support;

use Carbon\CarbonInterface;

class Format
{
    /** Indian-style grouping: 1234567.5 → 12,34,567.50 */
    public static function indian(float|int|string|null $amount, int $decimals = 0): string
    {
        $amount = (float) $amount;
        $neg = $amount < 0;
        $amount = abs($amount);
        $parts = explode('.', number_format($amount, $decimals, '.', ''));
        $int = $parts[0];
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        if ($rest !== '') {
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $int = $rest.','.$last3;
        }
        $out = $int.(isset($parts[1]) ? '.'.$parts[1] : '');

        return ($neg ? '-' : '').$out;
    }

    /** ₹12,34,567 (paise shown only when present). */
    public static function inr(float|int|string|null $amount, ?int $decimals = null): string
    {
        $amount = (float) $amount;
        $decimals ??= (fmod($amount, 1.0) == 0.0) ? 0 : 2;

        return '₹'.self::indian($amount, $decimals);
    }

    /** Short form for cards: ₹12.5 L, ₹1.2 Cr */
    public static function inrShort(float|int|string|null $amount): string
    {
        $a = (float) $amount;
        if ($a >= 10000000) {
            return '₹'.rtrim(rtrim(number_format($a / 10000000, 2), '0'), '.').' Cr';
        }
        if ($a >= 100000) {
            return '₹'.rtrim(rtrim(number_format($a / 100000, 2), '0'), '.').' L';
        }

        return self::inr($a);
    }

    public static function bytes(int|float|null $bytes): string
    {
        $bytes = (float) $bytes;
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
            if ($bytes < 1024 || $unit === 'TB') {
                return ($unit === 'B' ? (int) $bytes : round($bytes, 1)).' '.$unit;
            }
            $bytes /= 1024;
        }

        return '0 B';
    }

    public static function date(?CarbonInterface $date): string
    {
        return $date ? $date->format('d-m-Y') : '—';
    }

    public static function datetime(?CarbonInterface $date): string
    {
        return $date ? $date->timezone(config('app.timezone'))->format('d-m-Y h:i A') : '—';
    }

    public static function num(float|int|string|null $n, int $decimals = 0): string
    {
        return self::indian((float) $n, $decimals);
    }

    /** Amount in words (Indian system) for receipts. */
    public static function words(float $amount): string
    {
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);
        $w = self::intWords($rupees).' Rupees';
        if ($paise > 0) {
            $w .= ' and '.self::intWords($paise).' Paise';
        }

        return $w.' Only';
    }

    private static function intWords(int $n): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        if ($n === 0) {
            return 'Zero';
        }
        $two = function (int $x) use ($ones, $tens) {
            return $x < 20 ? $ones[$x] : trim($tens[intdiv($x, 10)].' '.$ones[$x % 10]);
        };
        $parts = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand'], [100, 'Hundred']] as [$div, $label]) {
            if ($n >= $div) {
                $q = intdiv($n, $div);
                $parts[] = ($q >= 100 ? self::intWords($q) : $two($q)).' '.$label;
                $n %= $div;
            }
        }
        if ($n > 0) {
            $parts[] = $two($n);
        }

        return implode(' ', $parts);
    }
}
