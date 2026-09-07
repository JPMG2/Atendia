<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;
use Illuminate\Support\Arr;

/**
 * The connection slice: the two WhatsApp numbers and the contact email —
 * the address the welcome lands on. It never creates the business: the
 * identity slice does.
 */
class SaveBusinessConnection
{
    /** @var list<string> */
    private const COLUMNS = ['whatsapp_number', 'fallback_whatsapp_number', 'email'];

    /**
     * @param  array<string, mixed>  $data  Already validated by the calling form.
     */
    public function handle(Business $business, array $data): Business
    {
        $business->fill(Arr::only($data, self::COLUMNS))->save();

        return $business;
    }
}
