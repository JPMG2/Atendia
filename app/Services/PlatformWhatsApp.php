<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The bridge instance that carries everything the platform itself says:
 * device codes, alerts to the admin, summaries. It belongs to Aliadito and to
 * no business — a business must never hold it, free it or delete it, because
 * the day that business leaves the platform would lose its own voice.
 */
final class PlatformWhatsApp
{
    /** The instance's name, as the environment names it (empty when none is configured). */
    public static function instance(): string
    {
        return (string) config('services.evolution.instance');
    }

    /** Whether this name is the platform's own instance. An empty name is nobody's. */
    public static function owns(?string $instance): bool
    {
        $platform = self::instance();

        return $platform !== '' && $instance === $platform;
    }
}
