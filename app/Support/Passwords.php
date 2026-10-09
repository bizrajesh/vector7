<?php

namespace App\Support;

/**
 * Password policy (spec 4.5): 8–32 characters; letters, numbers and symbols;
 * no spaces at the start or end.
 */
class Passwords
{
    public const MIN = 8;

    public const MAX = 32;

    public const HINT = '8–32 characters. Letters, numbers and symbols allowed; no spaces at the start or end.';

    public static function rules(bool $confirmed = true): array
    {
        $rules = ['required', 'string', 'min:'.self::MIN, 'max:'.self::MAX, 'regex:/^\S(.*\S)?$/s'];
        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }

    public static function messages(): array
    {
        return [
            'password.regex' => 'The password must not start or end with a space.',
            'password.min' => 'The password must be at least 8 characters.',
            'password.max' => 'The password must not be longer than 32 characters.',
        ];
    }

    /** 12 random characters with upper, lower, number and symbol; no 0/O, 1/l/I. */
    public static function generate(int $length = 12): string
    {
        $sets = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghijkmnopqrstuvwxyz',
            '23456789',
            '@#$%&*?!+=',
        ];
        $chars = [];
        foreach ($sets as $set) {
            $chars[] = $set[random_int(0, strlen($set) - 1)];
        }
        $all = implode('', $sets);
        while (count($chars) < $length) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
