<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * One way to write an amount, for the panel and for the assistant.
 *
 * They used to disagree: the screen said "8.000" and the same service over
 * WhatsApp said "8000". To the person comparing them that reads as two
 * different prices, and the catalog is exactly where that cannot happen.
 */
trait FormatsPrice
{
    /** This row's price as everyone has to say it. */
    public function priceAmount(): string
    {
        return self::formatPrice($this->price);
    }

    /** Cents only when there are cents: "8.000", and "1.500,50". */
    public static function formatPrice(float|int|string|null $price): string
    {
        $amount = (float) $price;

        return number_format($amount, fmod($amount, 1.0) === 0.0 ? 0 : 2, ',', '.');
    }
}
