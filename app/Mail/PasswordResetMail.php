<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public $resetLink;
    public $subject;

    /**
     * Create a new message instance.
     */
    public function __construct(string $resetLink, string $subject)
    {
        $this->resetLink = $resetLink;
        $this->subject = $subject;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return Content::view('templates.email.generic_email_layout', [
            'main_content' => 'Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur ce lien : <a href="' . $this->resetLink . '">Réinitialiser mon mot de passe</a>',
            'subject_email' => $this->subject,
            'current_year' => now()->year,
            'app_url' => config('app.url'),
        ]);
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
