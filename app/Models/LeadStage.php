<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class LeadStage extends Model
{
    use BelongsToTenant;

    public const DEFAULTS = [
        ['new', 'New', 'blue', false, false],
        ['contacted', 'Contacted', 'teal', false, false],
        ['visit_scheduled', 'Site visit scheduled', 'amber', false, false],
        ['visited', 'Site visited', 'purple', false, false],
        ['negotiation', 'Negotiation', 'navy', false, false],
        ['won', 'Won (Booked / Sold)', 'teal', true, false],
        ['lost', 'Lost', 'gray', false, true],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_won' => 'boolean', 'is_lost' => 'boolean'];
    }

    public function leads(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function isClosed(): bool
    {
        return $this->is_won || $this->is_lost;
    }
}
