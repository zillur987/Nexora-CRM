<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lead lifecycle. Pure state machine (no framework coupling).
 *
 * `Converted` is deliberately NOT reachable through allowedTransitions():
 * conversion creates a contact (and optionally a deal), so it only happens
 * through LeadConversionService.
 */
enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Unqualified = 'unqualified';
    case Converted = 'converted';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Contacted, self::Unqualified],
            self::Contacted => [self::Qualified, self::Unqualified],
            self::Qualified => [self::Contacted, self::Unqualified],
            self::Unqualified => [self::New], // re-open
            self::Converted => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function canConvert(): bool
    {
        return $this === self::Qualified;
    }

    public function isConverted(): bool
    {
        return $this === self::Converted;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Bootstrap contextual colour. */
    public function badge(): string
    {
        return match ($this) {
            self::New => 'secondary',
            self::Contacted => 'info',
            self::Qualified => 'primary',
            self::Unqualified => 'danger',
            self::Converted => 'success',
        };
    }

    /** Statuses that still need sales attention. @return list<string> */
    public static function openValues(): array
    {
        return [self::New->value, self::Contacted->value, self::Qualified->value];
    }
}
