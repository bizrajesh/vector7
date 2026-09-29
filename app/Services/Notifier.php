<?php

namespace App\Services;

use App\Jobs\SendNotification;
use App\Models\Customer;
use App\Models\Layout;
use App\Models\NotificationGroup;
use App\Support\TenantContext;

/**
 * Routes business events to Notification Groups (email / WhatsApp).
 * Delivery is queued; every attempt is logged in notification_logs.
 */
class Notifier
{
    public function __construct(private readonly TenantContext $context, private readonly PlanLimits $limits) {}

    public function event(string $eventCode, string $message, ?Layout $layout = null): void
    {
        $tenantId = $this->context->id();
        if (! $tenantId) {
            return;
        }

        $groups = NotificationGroup::query()
            ->whereHas('events', fn ($q) => $q->where('event_code', $eventCode))
            ->when($layout, fn ($q) => $q->where(function ($q) use ($layout) {
                // Groups tagged on this layout, or groups not tagged on any layout (tenant-wide).
                $q->whereIn('id', $layout->notificationGroups()->pluck('notification_groups.id'))
                    ->orWhereNotIn('id', fn ($sub) => $sub->select('notification_group_id')->from('layout_notification_groups'));
            }))
            ->with('members.user')
            ->get();

        $subject = config('vector7.notification_events.'.$eventCode, 'Vector7 alert');
        $whatsappAllowed = $this->limits->feature('whatsapp');

        foreach ($groups as $group) {
            foreach ($group->members as $member) {
                $email = $member->user?->email ?? $member->email;
                $phone = $member->user?->phone ?? $member->phone;

                if ($group->channel_email && $email) {
                    SendNotification::dispatch($tenantId, 'email', $email, $eventCode, $subject, $message);
                }
                if ($group->channel_whatsapp && $whatsappAllowed && $phone) {
                    SendNotification::dispatch($tenantId, 'whatsapp', $phone, $eventCode, $subject, $message);
                }
            }
        }
    }

    public function customer(Customer $customer, string $eventCode, string $message): void
    {
        $tenantId = $this->context->id();
        $subject = config('vector7.notification_events.'.$eventCode, 'Update on your plot');

        if ($customer->email) {
            SendNotification::dispatch($tenantId, 'email', $customer->email, $eventCode, $subject, $message);
        }
        if ($customer->phone && $this->limits->feature('whatsapp')) {
            SendNotification::dispatch($tenantId, 'whatsapp', $customer->phone, $eventCode, $subject, $message);
        }
    }
}
