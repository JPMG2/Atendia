<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;
use Illuminate\Support\Arr;

/**
 * The connection slice: how the business is reached — the two WhatsApp
 * numbers, the contact email (the address the welcome lands on) and the
 * public site. Each caller sends its own subset; `Arr::only` keeps the
 * rest untouched. It never creates the business: the identity slice does.
 */
class SaveBusinessConnection
{
    /** @var list<string> */
    private const array COLUMNS = ['whatsapp_number', 'fallback_whatsapp_number', 'email', 'web'];

    /**
     * @param  array<string, mixed>  $data  Already validated by the calling form.
     */
    public function handle(Business $business, array $data): Business
    {
        $business->fill(Arr::only($data, self::COLUMNS))->save();

        return $business;
    }
}
