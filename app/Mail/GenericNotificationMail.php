<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GenericNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $mailSubject;
    public $mailContent;
    public $attachmentsData;

    public function __construct(string $mailSubject, string $mailContent, array $attachmentsData = [])
    {
        $this->mailSubject = $mailSubject;
        $this->mailContent = $mailContent;
        $this->attachmentsData = $attachmentsData;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        return Content::view('templates.email.generic_email_layout', [
            'main_content' => $this->mailContent,
            'subject_email' => $this->mailSubject,
            'current_year' => now()->year,
            'app_url' => config('app.url'),
        ]);
    }

    public function attachments(): array
    {
        $attachments = [];
        foreach ($this->attachmentsData as $attachment) {
            $attachments[] = \Illuminate\Mail\Mailables\Attachment::fromPath($attachment['path'])
                ->as($attachment['name']);
        }
        return $attachments;
    }
}
