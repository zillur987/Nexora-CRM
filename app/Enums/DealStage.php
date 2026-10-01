<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Pipeline state machine lives on the enum: pure, testable, no framework coupling.
 */
enum DealStage: string
{
    case New = 'new';
    case Qualified = 'qualified';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Qualified, self::Lost],
            self::Qualified => [self::Proposal, self::Lost],
            self::Proposal => [self::Negotiation, self::Lost],
            self::Negotiation => [self::Won, self::Lost],
            self::Won, self::Lost => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isClosed(): bool
    {
        return $this === self::Won || $this === self::Lost;
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
            self::Qualified => 'info',
            self::Proposal => 'primary',
            self::Negotiation => 'warning',
            self::Won => 'success',
            self::Lost => 'danger',
        };
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return [self::New->value, self::Qualified->value, self::Proposal->value, self::Negotiation->value];
    }
}
