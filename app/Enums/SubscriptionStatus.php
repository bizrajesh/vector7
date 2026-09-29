<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /** Tenants in these states may use the portal normally. */
    public function allowsAccess(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::PastDue], true);
    }

    /** Suspended tenants keep read-only access to their data. */
    public function isReadOnly(): bool
    {
        return $this === self::Suspended;
    }

    public function css(): string
    {
        return match ($this) {
            self::Trial => 'os',
            self::Active => 'av',
            self::PastDue => 'bk',
            self::Suspended, self::Cancelled => 'od',
        };
    }
}
