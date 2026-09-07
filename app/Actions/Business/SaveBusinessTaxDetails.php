<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;
use Illuminate\Support\Arr;

/**
 * The fiscal slice: currencies, tax condition and tax id. Everything
 * nullable on purpose — clearing a field invoices a natural person.
 */
class SaveBusinessTaxDetails
{
    /** @var list<string> */
    private const COLUMNS = ['currency_id', 'reference_currency_id', 'tax_condition_id', 'tax_id'];

    /**
     * @param  array<string, mixed>  $data  Already validated by the calling form.
     */
    public function handle(Business $business, array $data): Business
    {
        $business->fill(Arr::only($data, self::COLUMNS))->save();

        return $business;
    }
}
