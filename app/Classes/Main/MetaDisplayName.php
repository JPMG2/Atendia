<?php

declare(strict_types=1);

namespace App\Classes\Main;

/**
 * Meta's rules for the name a business shows on WhatsApp, checked BEFORE the
 * day it is sent for approval and not after a rejection.
 *
 * It only advises: the name is the owner's, and a rule Meta changes next
 * quarter must not lock anybody out of connecting. Matching the real brand
 * cannot be checked from here, so the screen says it in words instead.
 */
final class MetaDisplayName
{
    private const int MIN_LENGTH = 3;

    /** All capitals pass only as an acronym: short and a single word. */
    private const int ACRONYM_MAX = 5;

    public function __construct(private readonly string $name) {}

    /**
     * Which rules the name breaks, as lang keys under `whatsapp.name_rules`.
     *
     * @var list<string>
     */
    public array $problems {
        get {
            $name = trim($this->name);

            if ($name === '') {
                return [];
            }

            return array_values(array_filter([
                mb_strlen($name) < self::MIN_LENGTH ? 'too_short' : null,
                $this->isShouting($name) ? 'all_caps' : null,
                preg_match('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u', $name) === 1 ? 'emoji' : null,
                preg_match('~(https?://|www\.|\b[\p{L}\d-]+\.(com|net|org|io|app|shop|store|ar|ve|co)\b)~iu', $name) === 1 ? 'url' : null,
                preg_match('/whats\s*app/i', $name) === 1 ? 'whatsapp' : null,
            ]));
        }
    }

    public bool $isAcceptable {
        get => $this->problems === [];
    }

    private function isShouting(string $name): bool
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $name) ?? '';

        if ($letters === '' || $letters !== mb_strtoupper($letters)) {
            return false;
        }

        $isAcronym = ! str_contains($name, ' ') && mb_strlen($letters) <= self::ACRONYM_MAX;

        return ! $isAcronym;
    }
}
