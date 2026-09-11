<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Business\SaveBusinessTaxDetails;
use App\Models\Business;

/**
 * The fiscal piece. Every field optional on purpose: no tax data means the
 * invoice goes out to a natural person.
 */
class TaxDetails
{
    public function __construct(private Business $business) {}

    /**
     * @var array{currency_id: ?int, reference_currency_id: ?int, tax_condition_id: ?int, tax_id: ?string}
     */
    public array $data {
        get => [
            'currency_id' => $this->business->currency_id,
            'reference_currency_id' => $this->business->reference_currency_id,
            'tax_condition_id' => $this->business->tax_condition_id,
            'tax_id' => $this->business->tax_id,
        ];
    }

    /**
     * A chosen currency is the one thing an invoice cannot go out without;
     * the rest stays optional (natural person).
     */
    public bool $isComplete {
        get => $this->business->currency_id !== null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function save(array $validated): Business
    {
        return app(SaveBusinessTaxDetails::class)->handle($this->business, $validated);
    }
}
