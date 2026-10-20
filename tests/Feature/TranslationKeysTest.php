<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A one-word translation key equal to a language group file name (auth, pagination, passwords, validation)
 * makes __() return that whole file as an array on Windows (case-insensitive file system) — a 500 on Laragon.
 */
class TranslationKeysTest extends TestCase
{
    public function test_no_translation_key_collides_with_a_language_file(): void
    {
        $groups = collect(array_merge(
            File::glob(base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/*.php')),
            File::glob(lang_path('*/*.php')),
        ))->map(fn ($f) => strtolower(pathinfo($f, PATHINFO_FILENAME)))->unique()->all();

        $bad = [];
        foreach (array_merge(File::allFiles(resource_path('views')), File::allFiles(app_path())) as $file) {
            preg_match_all("/(?:__|trans|trans_choice|@lang)\\(\\s*(['\"])([A-Za-z_-]+)\\1/", $file->getContents(), $m);
            foreach ($m[2] as $key) {
                if (in_array(strtolower($key), $groups, true)) {
                    $bad[] = $file->getRelativePathname().': '.$key;
                }
            }
        }
        foreach (array_keys(json_decode(File::get(lang_path('ta.json')), true)) as $key) {
            if (in_array(strtolower($key), $groups, true)) {
                $bad[] = 'lang/ta.json: '.$key;
            }
        }
        $this->assertSame([], $bad);
    }
}
