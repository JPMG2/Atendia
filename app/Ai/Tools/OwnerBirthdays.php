<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AskAtendia;
use App\Interfaces\Main\OwnerSkillTool;
use App\Models\Business;
use App\Models\Customer;
use App\Traits\ReadsDateRange;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Whose birthday falls in a period and whether they accepted messages —
 * the owner decides the greeting, the assistant only reads the list.
 */
class OwnerBirthdays implements OwnerSkillTool
{
    use ReadsDateRange;

    public function __construct(private readonly Business $business) {}

    public static function forOwner(AskAtendia $assistant): ?static
    {
        return new static($assistant->business);
    }

    public function description(): Stringable|string
    {
        return 'Clientes del negocio que cumplen años en un período, con la fecha del cumpleaños '
            .'y si aceptaron recibir mensajes del negocio.';
    }

    public function handle(Request $request): Stringable|string
    {
        $range = $this->dateRange($request, $this->business);

        if (is_string($range)) {
            return $range;
        }

        [$from, $to] = $range;

        $birthdays = Customer::query()
            ->where('business_id', $this->business->id)
            ->whereNotNull('birthday')
            ->get(['id', 'name', 'profile_name', 'birthday', 'marketing_opt_in_at'])
            ->map(fn (Customer $customer): ?array => ($day = $this->nextBirthday($customer, $from, $to)) === null
                ? null
                : ['customer' => $customer, 'day' => $day])
            ->filter()
            ->sortBy(fn (array $row): string => $row['day']->toDateString())
            ->values();

        if ($birthdays->isEmpty()) {
            return $this->periodLabel($from, $to).' no cumple años ningún cliente con cumpleaños cargado.';
        }

        return $this->periodLabel($from, $to).' cumplen años '.$birthdays->count()." clientes:\n"
            .$birthdays->map(fn (array $row): string => '- '.($row['customer']->displayName() ?? 'Sin nombre')
                .' · '.$row['day']->locale('es')->translatedFormat('l d/m/Y')
                .' · '.($row['customer']->marketing_opt_in_at !== null ? 'aceptó recibir mensajes' : 'no aceptó recibir mensajes'))
                ->implode("\n")
            ."\nLista completa de clientes: ".route('customers');
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->dateRangeSchema($schema);
    }

    /** The birthday's date inside the window, if any; a 29 February lands on the 28th in common years. */
    private function nextBirthday(Customer $customer, CarbonImmutable $from, CarbonImmutable $to): ?CarbonImmutable
    {
        $birthday = CarbonImmutable::parse($customer->birthday);

        for ($year = $from->year; $year <= $to->year; $year++) {
            $day = min($birthday->day, CarbonImmutable::create($year, $birthday->month, 1, 0, 0, 0, $from->timezone)->daysInMonth);
            $candidate = CarbonImmutable::create($year, $birthday->month, $day, 12, 0, 0, $from->timezone);

            if ($candidate->between($from, $to)) {
                return $candidate;
            }
        }

        return null;
    }
}
