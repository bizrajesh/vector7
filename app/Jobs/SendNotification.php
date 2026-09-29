<?php

namespace App\Jobs;

use App\Mail\PlainNotification;
use App\Models\NotificationLog;
use App\Services\Notifications\WhatsAppClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(
        public int $tenantId,
        public string $channel,
        public string $recipient,
        public string $eventCode,
        public string $subject,
        public string $message,
    ) {}

    public function handle(WhatsAppClient $whatsapp): void
    {
        $log = NotificationLog::create([
            'tenant_id' => $this->tenantId, 'channel' => $this->channel, 'recipient' => $this->recipient,
            'event_code' => $this->eventCode, 'subject' => $this->subject, 'status' => 'queued',
        ]);

        try {
            if ($this->channel === 'email') {
                Mail::to($this->recipient)->send(new PlainNotification($this->subject, $this->message));
            } elseif ($whatsapp->isConfigured()) {
                $whatsapp->send($this->recipient, $this->message);
            } else {
                $log->update(['status' => 'failed', 'error' => 'WhatsApp not configured']);

                return;
            }
            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $e) {
            $log->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
            throw $e;
        }
    }
}
