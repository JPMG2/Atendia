<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What happened, for the bell's inbox. Only events the business loses or earns
 * money on: state the panel already shows (today's counters, the WhatsApp pill)
 * belongs to its own screen, not to a list that keeps a read mark.
 */
enum PanelNotificationType: string
{
    case CustomerWaiting = 'customer_waiting';
    case HandedToTeam = 'handed_to_team';
    case AppointmentBooked = 'appointment_booked';
    case WhatsAppDisconnected = 'whatsapp_disconnected';
    case TaughtByTeammate = 'taught_by_teammate';
    case SupportResolved = 'support_resolved';

    /** The row's glyph, from the central registry. */
    public function icon(): string
    {
        return match ($this) {
            self::CustomerWaiting => 'message-circle',
            self::HandedToTeam => 'users',
            self::AppointmentBooked => 'calendar-check',
            self::WhatsAppDisconnected => 'whatsapp',
            self::TaughtByTeammate => 'graduation-cap',
            self::SupportResolved => 'life-buoy',
        };
    }

    /**
     * The mark's tint as inline tokens, the way stat-card does it: a class
     * built from a variable (`bell-mark-{$tint}`) never appears literally in
     * the sources, and Tailwind purges the rule it would have matched.
     *
     * No danger tint on purpose: nothing in this inbox is irreversible, and a
     * list painted red stops being read.
     */
    public function tintStyle(): string
    {
        [$background, $glyph] = match ($this) {
            self::CustomerWaiting, self::WhatsAppDisconnected => ['var(--warning-soft)', 'var(--warning)'],
            self::HandedToTeam, self::TaughtByTeammate => ['var(--info-soft)', 'var(--info)'],
            self::AppointmentBooked, self::SupportResolved => ['var(--success-soft)', 'var(--success)'],
        };

        return "background:{$background};color:{$glyph};";
    }
}
