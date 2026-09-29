<?php

namespace App\Enums;

enum LayoutStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InProgress = 'in_progress';
    case ReadyToLaunch = 'ready_to_launch';
    case Launched = 'launched';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::InProgress => 'In progress',
            self::ReadyToLaunch => 'Ready to launch',
            self::Launched => 'Launched',
            self::Closed => 'Closed',
        };
    }

    public function css(): string
    {
        return match ($this) {
            self::Draft, self::Submitted => 'rs',
            self::InProgress => 'os',
            self::ReadyToLaunch => 'ror',
            self::Launched => 'av',
            self::Closed => 'sold',
        };
    }
}
