<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TemplateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $mailSubject,
        public string $bodyText,
        public ?array $footer = null,
        public array $files = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        // Body text is escaped first; **bold** and URLs are then turned into safe HTML.
        $html = e($this->bodyText);
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
        $html = preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:#0F8F84">$1</a>', $html);
        $html = nl2br($html);

        return new Content(view: 'emails.template', with: ['html' => $html, 'footer' => $this->footer]);
    }

    public function attachments(): array
    {
        return array_map(fn ($f) => Attachment::fromPath($f['path'])->as($f['name']), array_filter($this->files, fn ($f) => is_file($f['path'])));
    }
}
