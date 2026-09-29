<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends TenantModel
{
    use SoftDeletes;

    protected $fillable = ['name', 'phone', 'email', 'address', 'aadhaar', 'pan'];

    protected $hidden = ['aadhaar', 'pan'];

    protected function casts(): array
    {
        return ['aadhaar' => 'encrypted', 'pan' => 'encrypted'];
    }

    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            if ($customer->isDirty('aadhaar')) {
                $digits = preg_replace('/\D/', '', (string) $customer->aadhaar);
                $customer->aadhaar_last4 = $digits !== '' ? substr($digits, -4) : null;
            }
        });
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function maskedAadhaar(): string
    {
        return $this->aadhaar_last4 ? 'XXXX XXXX '.$this->aadhaar_last4 : '—';
    }

    public function maskedPan(): string
    {
        $pan = (string) $this->pan;

        return $pan !== '' ? substr($pan, 0, 2).'XXXXX'.substr($pan, -3) : '—';
    }
}
