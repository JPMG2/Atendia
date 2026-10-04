<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use Illuminate\Translation\Translator;

/**
 * Every translated string may write `:brand` and get the live brand name.
 *
 * The name was spelled out 140 times across lang files and views, and it is
 * about to change — the current one is taken. Passing it at each of the 140
 * call sites would be the same literal with extra steps, so it is injected
 * once, here, straight from the Company row.
 */
class BrandAwareTranslator extends Translator
{
    /**
     * @param  array<string, mixed>  $replace
     */
    #[\Override]
    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        // The caller wins: a string that means to say another name still can.
        if (! array_key_exists('brand', $replace)) {
            $replace['brand'] = Company::brand();
        }

        return parent::get($key, $replace, $locale, $fallback);
    }
}
