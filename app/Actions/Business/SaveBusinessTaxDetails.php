<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;
use Illuminate\Support\Arr;

/**
 * The billing slice: currencies, tax condition, tax id and where the charge
 * notices go. The fiscal fields are nullable on purpose — clearing one invoices
 * a natural person — but the notices need an address, so that one never empties.
 */
class SaveBusinessTaxDetails
{
    /** @var list<string> */
    private const array COLUMNS = ['currency_id', 'reference_currency_id', 'tax_condition_id', 'tax_id', 'billing_email'];

    /**
     * @param  array<string, mixed>  $data  Already validated by the calling form.
     */
    public function handle(Business $business, array $data): Business
    {
        $business->fill(Arr::only($data, self::COLUMNS))->save();

        return $business;
    }
}
