<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Sales = 'sales';
    case Shareholder = 'shareholder';
    case Customer = 'customer';

    public function label(): string
    {
        return config('vector7.roles.'.$this->value, $this->value);
    }
}
