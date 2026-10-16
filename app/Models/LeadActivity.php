<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadActivity extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    public const TYPES = [
        'created' => 'Lead created', 'interest' => 'New interest', 'call_click' => 'Clicked Call', 'whatsapp_click' => 'Clicked WhatsApp',
        'show_number' => 'Revealed the number', 'enquiry' => 'Sent an enquiry', 'chatbot' => 'Chatbot hand-off', 'ai_search' => 'Contacted from AI search',
        'booking' => 'Booking', 'call' => 'Call logged', 'whatsapp' => 'WhatsApp sent', 'email' => 'Email sent', 'note' => 'Note',
        'stage' => 'Stage changed', 'followup' => 'Follow-up scheduled', 'followup_done' => 'Follow-up done', 'site_visit' => 'Site visit scheduled',
        'assigned' => 'Assigned', 'merged' => 'Merged duplicate', 'ai' => 'AI help',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }

    public function label(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }
}
