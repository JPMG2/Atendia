<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The path a business walks from signing up to being answered by its own
 * assistant — the same one the journey test drives. The ladder lives here so
 * the screen, and anything that later alerts on it, read the same order.
 */
enum AdoptionStep: string
{
    case Registered = 'registered';
    case BusinessCreated = 'business';
    case CatalogLoaded = 'catalog';
    case WhatsAppConnected = 'whatsapp';
    case FirstConversation = 'conversation';
    case AssistantAnswered = 'answered';

    public function label(): string
    {
        return __('adoption.steps.'.$this->value);
    }

    /** 1-based place in the ladder: the screen prints "paso 3 de 6". */
    public function position(): int
    {
        return (int) array_search($this, self::cases(), true) + 1;
    }

    public static function total(): int
    {
        return count(self::cases());
    }

    /**
     * The furthest step the evidence supports, read from the end: a business
     * whose assistant answered also loaded a catalog, whatever the order the
     * counters arrive in.
     */
    public static function reached(bool $hasBusiness, int $catalogItems, bool $connected, int $conversations, int $answers): self
    {
        return match (true) {
            $answers > 0 => self::AssistantAnswered,
            $conversations > 0 => self::FirstConversation,
            $connected => self::WhatsAppConnected,
            $catalogItems > 0 => self::CatalogLoaded,
            $hasBusiness => self::BusinessCreated,
            default => self::Registered,
        };
    }
}
