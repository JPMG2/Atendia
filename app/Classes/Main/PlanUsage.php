<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\Business;
use App\Models\Company;

/**
 * "Mi plan" as data: the plan card, this month's meters and the owner's
 * "Ask" quota. The web screen, the home strip and the mobile API all read
 * from here, so a figure cannot print one way on the panel and another on
 * the phone. The figures come from `Business` and the caps from `Plan`.
 */
class PlanUsage
{
    /** `$tier` is for the web's "Mi plan" mock-up of an upgrade, which shows the caps of a plan not yet held. */
    public function __construct(private Business $business, private ?Plan $tier = null) {}

    public Plan $plan {
        get => $this->tier ?? $this->business->plan();
    }

    /**
     * The plan card: its name and what it costs a month, as printed.
     *
     * @var array{code: string, name: string, price: string, per_month: string}
     */
    public array $card {
        get => [
            'code' => $this->plan->code,
            'name' => __('plan.names.'.$this->plan->code),
            'price' => config('atendia.billing.currency_symbol').$this->plan->price,
            'per_month' => __('plan.per_month'),
        ];
    }

    /**
     * The conversations meter alone: the one the home keeps always in sight.
     *
     * @var array{key: string, label: string, text: string, used: int, cap: int, percent: int, state: string}
     */
    public array $conversations {
        get => $this->meter(
            'conversations',
            __('plan.meters.conversations'),
            $this->business->conversationsThisMonth(),
            $this->plan->conversationsPerMonth,
            'conversations_of',
        );
    }

    /**
     * Every meter, in the order the screen lists them. A meter with a bar has
     * `used`, `cap`, `percent` and `state`; one without (people, pace) carries
     * only its text, as the web draws it.
     *
     * @var list<array{key: string, label: string, text: string, used: int|null, cap: int|null, percent: int|null, state: string|null}>
     */
    public array $meters {
        get {
            $plan = $this->plan;

            return array_values(array_filter([
                $this->conversations,
                $this->meter('photos', __('plan.meters.photos'), $this->business->catalogPhotosCount(), $plan->catalogPhotos, 'photos_of'),
                $plan->allowsAudio
                    ? $this->meter(
                        'audio',
                        __('plan.meters.audio'),
                        (int) ceil($this->business->audioSecondsThisMonth() / 60),
                        $plan->audioMinutesPerMonth,
                        'audio_of',
                        false,
                    )
                    : null,
                $this->plain('seats', __('plan.meters.seats'), __('plan.meters.seats_of', ['used' => $this->business->teamSeatsUsed(), 'cap' => $plan->teamSeats])),
                $this->plain('pace', __('plan.meters.pace'), __('plan.meters.pace_value', ['cap' => $plan->messagesPerHour])),
            ]));
        }
    }

    /**
     * The owner's "Ask" quota, or null on a plan that does not include it.
     *
     * @var array{key: string, title: string, left_label: string, left: int, of: string, renews: string, used: int, cap: int, percent: int, state: string}|null
     */
    public ?array $ask {
        get {
            $plan = $this->plan;

            if (! $plan->allowsAsk) {
                return null;
            }

            $used = $this->business->askQuestionsThisMonth();
            $renewsOn = $this->business->quotaRenewsOn()->format('d/m/Y');

            return [
                'key' => 'ask',
                'title' => __('ask.title', ['brand' => Company::brand()]),
                'left_label' => __('ask.quota.left'),
                'left' => max(0, $plan->askPerMonth - $used),
                'of' => __('ask.quota.of', ['cap' => $plan->askPerMonth]),
                'renews' => __('ask.quota.renews', ['date' => $renewsOn]),
                'used' => $used,
                'cap' => $plan->askPerMonth,
                'percent' => $this->percent($used, $plan->askPerMonth),
                'state' => Plan::usageState($used, $plan->askPerMonth),
            ];
        }
    }

    /**
     * A meter with a bar. Numbers print the web's way (thousands with a dot),
     * except where the web prints them bare (audio, minutes).
     *
     * @return array{key: string, label: string, text: string, used: int, cap: int, percent: int, state: string}
     */
    private function meter(string $key, string $label, int $used, int $cap, string $textKey, bool $thousands = true): array
    {
        $format = fn (int $number): string|int => $thousands ? number_format($number, 0, ',', '.') : $number;

        return [
            'key' => $key,
            'label' => $label,
            'text' => __('plan.meters.'.$textKey, ['used' => $format($used), 'cap' => $format($cap)]),
            'used' => $used,
            'cap' => $cap,
            'percent' => $this->percent($used, $cap),
            'state' => Plan::usageState($used, $cap),
        ];
    }

    /**
     * A row with no bar: the people in the panel and the pace.
     *
     * @return array{key: string, label: string, text: string, used: null, cap: null, percent: null, state: null}
     */
    private function plain(string $key, string $label, string $text): array
    {
        return ['key' => $key, 'label' => $label, 'text' => $text, 'used' => null, 'cap' => null, 'percent' => null, 'state' => null];
    }

    /** What the bar fills to: the share used, never past the track. */
    private function percent(int $used, int $cap): int
    {
        return min(100, (int) round($used / max(1, $cap) * 100));
    }
}
