<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Dto\BusinessDto;
use App\Models\Business;
use App\Models\SocialLink;

/**
 * "Mi negocio" as data: one row per section of the profile, in the web
 * menu's order, each with a line of what it holds today. The mobile API reads
 * from here; the meter it sits under is the one the web prints, from
 * `Client::profileStrength`.
 */
class BusinessProfile
{
    /** Piece of the web's strength meter → the section that completes it. */
    private const array PIECE_SECTION = [
        'personal_data' => 'contact',
        'tax_details' => 'billing',
        'schedule' => 'hours',
        'social_media' => 'social',
    ];

    public function __construct(private Business $business) {}

    /**
     * @var list<array{key: string, title: string, icon: string, screen: string, summary: string|null}>
     */
    public array $sections {
        get {
            $data = BusinessDto::fromArray($this->business->toArray());

            return [
                $this->row('identity', 'sparkles', 'my-business.identidad', __('client.business.identity.title'), $data->name !== '' ? $data->name : null),
                $this->row('location', 'map-pin', 'my-business.ubicacion', __('client.business.location.title'), $this->location($data)),
                $this->row('hours', 'clock', 'my-business.horarios', __('client.business.hours.title'), $this->hoursToday()),
                $this->row('appointments', 'calendar-check', 'my-business.turnos', __('client.business.appointments.title'), $this->appointments($data)),
                $this->row('contact', 'at-sign', 'my-business.contacto', __('client.business.contact.title'), $this->contact($data)),
                $this->row('social', 'share-2', 'my-business.redes', __('client.business.social.title'), $this->networks()),
                $this->row('billing', 'receipt', 'my-business.facturacion', __('client.business.billing.title'), $this->money($data)),
                $this->row('team', 'users-round', 'team', __('menu.team'), __('client.business.summary.people', [
                    'used' => $this->business->teamSeatsUsed(),
                    'cap' => $this->business->plan()->teamSeats,
                ])),
            ];
        }
    }

    /**
     * The web's meter, said as the screen prints it. `$strength` is what
     * `Client::profileStrength` answers: how many pieces are done and which are not.
     *
     * @param  array{done: int, total: int, missing: list<string>}  $strength
     * @return array{percent: int, complete: bool, title: string, count: string, complete_title: string, complete_sub: string, pieces: list<array{key: string, label: string, done: bool, section: string}>}
     */
    public function meter(array $strength): array
    {
        $percent = (int) round($strength['done'] / max(1, $strength['total']) * 100);

        return [
            'percent' => $percent,
            'complete' => $percent === 100,
            'title' => __('client.business.meter.title', ['percent' => $percent]),
            'count' => __('client.business.meter.count', ['done' => $strength['done'], 'total' => $strength['total']]),
            'complete_title' => __('client.business.meter.complete_title'),
            'complete_sub' => __('client.business.meter.complete_sub'),
            'pieces' => array_map(fn (string $piece, string $section): array => [
                'key' => $piece,
                'label' => __('client.business.meter.'.$piece),
                'done' => ! in_array($piece, $strength['missing'], true),
                'section' => $section,
            ], array_keys(self::PIECE_SECTION), array_values(self::PIECE_SECTION)),
        ];
    }

    /**
     * @return array{key: string, title: string, icon: string, screen: string, summary: string|null}
     */
    private function row(string $key, string $icon, string $screen, string $title, ?string $summary): array
    {
        return ['key' => $key, 'title' => $title, 'icon' => $icon, 'screen' => $screen, 'summary' => $summary];
    }

    private function location(BusinessDto $data): ?string
    {
        if ($data->has_premises === false) {
            return __('client.business.location.no');
        }

        $line = collect([$data->address, $data->city])->map(fn (?string $part): string => trim((string) $part))->filter()->implode(', ');

        return $line === '' ? null : $line;
    }

    /** Today's shifts on the business's own clock: what a customer asking right now would be told. */
    private function hoursToday(): ?string
    {
        $week = (new Schedule($this->business))->week;

        if (collect($week)->every(fn (array $shifts): bool => $shifts === [])) {
            return null;
        }

        $shifts = $week[now($this->business->localTimezone())->dayOfWeek];

        if ($shifts === []) {
            return __('client.business.summary.closed_today');
        }

        return __('client.business.summary.today', [
            'hours' => collect($shifts)->map(fn ($shift): string => substr($shift->opens_at, 0, 5).' – '.substr($shift->closes_at, 0, 5))->implode(' · '),
        ]);
    }

    private function appointments(BusinessDto $data): string
    {
        return $data->appointments_enabled
            ? __('client.business.summary.slots', ['minutes' => $data->appointment_slot_minutes])
            : __('client.business.summary.no_agenda');
    }

    private function contact(BusinessDto $data): ?string
    {
        $line = collect([$data->whatsapp_number, $data->email])->map(fn (?string $part): string => trim((string) $part))->filter()->implode(' · ');

        return $line === '' ? null : $line;
    }

    private function networks(): ?string
    {
        $names = $this->business->socialLinks()->with('socialNetwork')->get()
            ->map(fn (SocialLink $link): string => (string) $link->socialNetwork?->name)
            ->filter()
            ->implode(', ');

        return $names === '' ? null : $names;
    }

    private function money(BusinessDto $data): ?string
    {
        $currency = $this->business->currency?->code;

        if ($currency === null) {
            return null;
        }

        return collect([$currency, $data->tax_id])->map(fn (?string $part): string => trim((string) $part))->filter()->implode(' · ');
    }
}
