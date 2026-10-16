<?php

namespace App\Services;

use App\Mail\TemplateMail;
use App\Models\EmailTemplate;
use App\Models\NotificationGroup;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Email notifications (email only in this release). Templates are editable by the App Admin;
 * tenant emails carry the tenant logo and footer. Sent through the database queue.
 */
class Notify
{
    public const TEMPLATES = [
        'workspace_created' => ['Workspace created', 'Welcome to vector7 — your workspace {tenant_code} is ready', "Hello {name},\n\nYour promoter workspace **{tenant_name}** (Tenant ID {tenant_code}) is ready. Your trial plan runs until {trial_end}.\n\nSign in: {link}", 'name, tenant_name, tenant_code, trial_end, link'],
        'user_invited' => ['User invited', 'You have been added to {tenant_name} on vector7', "Hello {name},\n\n{inviter} added you to **{tenant_name}** as {role}.\n\nSet your password here (link valid 60 minutes): {link}", 'name, tenant_name, inviter, role, link'],
        'password_generated' => ['Password generated', 'Your new vector7 password', "Hello {name},\n\nA new password was generated for your account by {admin}.\n\nEmail: {email}\nPassword: {password}\n\n{change_note}\n\nSign in: {link}", 'name, admin, email, password, change_note, link'],
        'password_reset' => ['Password reset link', 'Reset your vector7 password', "Hello {name},\n\nWe received a request to reset your password. This link is valid for 60 minutes and can be used once:\n\n{link}\n\nIf you did not ask for this, you can ignore this email.", 'name, link'],
        'customer_welcome' => ['Customer account created by sales', 'Your vector7 account — set your password', "Hello {name},\n\n{tenant_name} created a vector7 account for you to manage your plot booking.\n\nSet your password (valid 60 minutes): {link}", 'name, tenant_name, link'],
        'booking_created' => ['Booking created', 'Booking {booking_no} confirmed — Plot {plot_no}, {project}', "Hello {name},\n\nYour booking **{booking_no}** for Plot {plot_no} in {project} is confirmed.\n\nPrice: {price}\nFirst instalment due: {first_amount}\nBooking valid till: {valid_till}\n\nIf the first instalment is not paid by {valid_till}, the plot is released automatically.", 'name, booking_no, plot_no, project, price, first_amount, valid_till'],
        'booking_expiring' => ['Booking expiring', 'Reminder: booking {booking_no} expires on {valid_till}', "Hello {name},\n\nYour booking {booking_no} for Plot {plot_no} in {project} expires on {valid_till}. Please pay the first instalment of {first_amount} to keep the plot.", 'name, booking_no, plot_no, project, valid_till, first_amount'],
        'booking_released' => ['Booking released', 'Booking {booking_no} has expired', "Hello {name},\n\nBooking {booking_no} for Plot {plot_no} in {project} expired on {valid_till} because the first instalment was not received. The plot has been released.", 'name, booking_no, plot_no, project, valid_till'],
        'instalment_due' => ['Instalment due', 'Instalment due on {due_date} — {sale_no}', "Hello {name},\n\n{instalment} of {amount} for Plot {plot_no} ({project}) is due on {due_date}.", 'name, instalment, amount, plot_no, project, due_date, sale_no'],
        'instalment_overdue' => ['Instalment overdue', 'Overdue: {instalment} for Plot {plot_no}', "Hello {name},\n\n{instalment} of {amount} for Plot {plot_no} ({project}) was due on {due_date} and is overdue. Balance: {balance}.", 'name, instalment, amount, plot_no, project, due_date, balance'],
        'payment_receipt' => ['Payment receipt', 'Receipt {receipt_no} — {amount} received', "Hello {name},\n\nWe received {amount} on {paid_on} for Plot {plot_no} ({project}). Receipt {receipt_no} is attached.\n\nPaid so far: {paid}\nBalance: {due}", 'name, amount, paid_on, plot_no, project, receipt_no, paid, due'],
        'sale_status' => ['Sale status changed', 'Plot {plot_no}: {status}', "Hello {name},\n\nThe status of your purchase of Plot {plot_no} in {project} is now **{status}**.", 'name, plot_no, project, status'],
        'registration_status' => ['Registration status changed', 'Registration update — Plot {plot_no}', "Hello {name},\n\nRegistration of Plot {plot_no} in {project} is now **{status}**. {details}", 'name, plot_no, project, status, details'],
        'budget_alert' => ['Budget alert', 'Budget alert: {scope} at {pct}% — {project}', "Spend on **{scope}** in project {project} has reached {pct}% of budget.\n\nBudget: {budget}\nSpent: {spent}", 'scope, project, pct, budget, spent'],
        'project_status' => ['Project status changed', 'Project {project}: {status}', "Project **{project}** ({code}) moved to **{status}**. {details}", 'project, code, status, details'],
        'refund_decision' => ['Refund decision', 'Refund {status} — Plot {plot_no}', "Hello {name},\n\nYour refund request for Plot {plot_no} in {project} was **{status}**.\n\nPaid: {paid}\nPenalty: {penalty}\nRefund: {refund}", 'name, plot_no, project, status, paid, penalty, refund'],
        'ticket_update' => ['Ticket update', '[{ticket_no}] {subject}', "Hello {name},\n\nTicket {ticket_no} — {subject}\nStatus: {status}\n\n{message}\n\nView: {link}", 'name, ticket_no, subject, status, message, link'],
        'subscription_alert' => ['Subscription alert', 'Your vector7 subscription: {message}', "Hello {name},\n\n{message}\n\nPlan: {plan}\nEnds on: {ends_on}\n\nManage your plan: {link}", 'name, message, plan, ends_on, link'],
        'usage_alert' => ['Plan usage alert', 'You have used {pct}% of your {limit}', "Hello {name},\n\nYour workspace has used {pct}% of its **{limit}** limit ({used} of {max}). Upgrade your plan to avoid interruptions: {link}", 'name, pct, limit, used, max, link'],
        'requirement_match' => ['New plot matches your requirement', 'New plot matching "{query}"', "Hello {name},\n\nA new plot matches your saved requirement:\n\nPlot {plot_no} — {project}, {location}\n{size} sq ft, {facing} facing, {price}\n\nView: {link}", 'name, query, plot_no, project, location, size, facing, price, link'],
        'enquiry_received' => ['New enquiry', 'New enquiry for {project}', "New enquiry from {name} ({email}, {mobile}):\n\n{message}\n\nOpen: {link}", 'name, email, mobile, project, message, link'],
        'lead_new' => ['New lead', 'New lead: {name} — {interest}', "A new lead came in.\n\nName: {name}\nMobile: {mobile}\nEmail: {email}\nSource: {source}\nInterested in: {interest}\n\n{message}\n\nOpen the lead: {link}", 'name, mobile, email, source, interest, message, link'],
        'lead_assigned' => ['Lead assigned to you', 'Lead assigned: {name}', "Hello {user},\n\nThe lead **{name}** ({mobile}) is now assigned to you.\nInterested in: {interest}\n\nOpen the lead: {link}", 'user, name, mobile, interest, link'],
        'lead_followup_due' => ['Lead follow-ups due', '{count} lead follow-up(s) due', "Hello {user},\n\nThese follow-ups are due today or overdue:\n\n{list}\n\nOpen your leads: {link}", 'user, count, list, link'],
        'lead_followup_soon' => ['Follow-up in 15 minutes', 'In 15 minutes: {kind} with {name}', "Hello {user},\n\n{kind} with **{name}** ({mobile}) is due at {time}.\n{notes}\n\nOpen the lead: {link}", 'user, kind, name, mobile, time, notes, link'],
        'lead_daily_summary' => ['Leads daily summary', 'Leads today: {new} new, {due} follow-ups due', "Leads summary for {date}:\n\nNew leads (last 24 h): {new}\nFollow-ups due today / overdue: {due}\nWon (last 24 h): {won}\nLost (last 24 h): {lost}\nUnassigned open leads: {unassigned}\n\nOpen the leads board: {link}", 'date, new, due, won, lost, unassigned, link'],
        'listing_hidden' => ['Listing hidden by vector7', 'Your listing {code} was hidden', "Hello,\n\nYour listing **{title}** ({code}) has been hidden from the vector7 marketplace by our team.\n\nReason: {reason}\n\nPlease update the listing and contact support if you have questions: {link}", 'title, code, reason, link'],
        'saved_search_match' => ['Saved search match', 'New property for "{query}"', "Hello {name},\n\nNew properties match your saved search \"{query}\":\n\n{list}\n\nSee all results: {link}", 'name, query, list, link'],
        'test_email' => ['Test email', 'vector7 test email', "This is a test email from vector7. Your SMTP settings work.", ''],
    ];

    /**
     * @param  array<string>  $emails
     * @param  array<string, string|int|float|null>  $data
     * @param  array<int, array{path:string,name:string}>  $attachments
     */
    public static function send(string $key, array $emails, array $data, ?int $tenantId = null, array $attachments = [], bool $now = false): void
    {
        $emails = array_values(array_unique(array_filter($emails, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
        if (! $emails) {
            return;
        }
        [$subject, $body] = self::render($key, $data);
        $tenant = $tenantId ? Tenant::find($tenantId) : null;
        $footer = $tenant ? [
            'name' => $tenant->name,
            'address' => $tenant->addressLine(),
            'contact' => $tenant->contact,
            'email' => $tenant->support_email ?: $tenant->email,
            'logo' => $tenant->logo_path ? storage_path('app/private/'.$tenant->logo_path) : null,
        ] : null;

        foreach ($emails as $to) {
            $mail = new TemplateMail($subject, $body, $footer, $attachments);
            try {
                $now ? Mail::to($to)->sendNow($mail) : Mail::to($to)->queue($mail);
            } catch (\Throwable $e) {
                Log::error('Email failed: '.$e->getMessage(), ['key' => $key, 'to' => $to]);
            }
        }
    }

    /** Send to every active member of the tenant's notification group(s). */
    public static function groups(int $tenantId, array $groupNames, string $key, array $data, array $attachments = []): void
    {
        $emails = NotificationGroup::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('is_active', true)
            ->whereIn('name', $groupNames)->with(['users' => fn ($q) => $q->withoutGlobalScopes()->where('is_active', true)])
            ->get()->flatMap(fn ($g) => $g->users->pluck('email'))->all();
        self::send($key, $emails, $data, $tenantId, $attachments);
    }

    public static function render(string $key, array $data): array
    {
        $tpl = EmailTemplate::where('key', $key)->first();
        $subject = $tpl->subject ?? self::TEMPLATES[$key][1] ?? $key;
        $body = $tpl->body ?? self::TEMPLATES[$key][2] ?? '';
        $replace = [];
        foreach ($data as $k => $v) {
            $replace['{'.$k.'}'] = (string) $v;
        }

        return [strtr($subject, $replace), strtr($body, $replace)];
    }

    public static function seedTemplates(): void
    {
        foreach (self::TEMPLATES as $key => [$name, $subject, $body, $placeholders]) {
            EmailTemplate::firstOrCreate(['key' => $key], compact('name', 'subject', 'body', 'placeholders'));
        }
    }
}
