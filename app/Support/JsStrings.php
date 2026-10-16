<?php

namespace App\Support;

/** Messages used by public/js/app.js on marketplace pages, in the visitor's language (read from #v7-i18n). */
class JsStrings
{
    public const KEYS = [
        'Too many requests — please try again in a minute.',
        'Something went wrong. Please try again.',
        'Could not reach the server. Please try again.',
        'You are sending messages too fast — please wait a minute.',
        'The assistant is not available right now.',
        'Could not reach the server.',
        'Share your details with the owner of :title?',
        'Your name',
        'Mobile',
        'I agree to share my name and mobile with the owner so they can contact me.',
        'Share and ask to call me',
        'Copy',
        'Copied',
        'Confirm',
        'Cancel',
        'Cancelled — nothing was changed.',
        'Download Excel',
        'Open',
        'Done.',
        'Writing…',
        'Click the text box on the page you want to fill, then press Apply again.',
        'Apply to field',
    ];

    public static function all(): array
    {
        $out = [];
        foreach (self::KEYS as $k) {
            $out[$k] = __($k);
        }

        return $out;
    }
}
