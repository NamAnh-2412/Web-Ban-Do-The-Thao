<?php

namespace App\Domain\Notification\Mail;

use App\Domain\Notification\Models\Notification;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OutboundMail extends Mailable
{
    /**
     * @param  array<string, mixed>  $templateData
     */
    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public ?string $viewName = null,
        public array $templateData = [],
    ) {}

    public static function fromNotification(Notification $row): self
    {
        $payload = is_array($row->payload) ? $row->payload : [];
        $template = $payload['template'] ?? null;
        $templateData = $payload['template_data'] ?? [];

        return new self(
            $row->subject,
            $row->body,
            is_string($template) && $template !== '' ? $template : null,
            is_array($templateData) ? $templateData : [],
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        if ($this->viewName !== null) {
            return new Content(
                view: $this->viewName,
                with: array_merge($this->templateData, [
                    'subjectLine' => $this->subjectLine,
                    'bodyText' => $this->bodyText,
                ]),
            );
        }

        return new Content(
            htmlString: '<p>'.nl2br(e($this->bodyText)).'</p>',
        );
    }
}
