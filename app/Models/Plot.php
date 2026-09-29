<?php

namespace App\Models;

use App\Enums\PlotStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Plot extends TenantModel
{
    protected $fillable = [
        'plot_no', 'survey_no', 'size_sqft', 'rate_sqft', 'cost', 'facing', 'dimensions',
        'boundary_north', 'boundary_south', 'boundary_east', 'boundary_west',
    ];

    protected function casts(): array
    {
        return ['status' => PlotStatus::class];
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(Layout::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function activeBooking(): HasOne
    {
        return $this->hasOne(Booking::class)->whereIn('status', ['pending', 'active'])->latestOfMany();
    }

    public function activeSale(): HasOne
    {
        return $this->hasOne(Sale::class)->whereIn('status', ['ongoing', 'paid', 'registered'])->latestOfMany();
    }

    public function history(): HasMany
    {
        return $this->hasMany(PlotStatusHistory::class)->latest('id');
    }
}
