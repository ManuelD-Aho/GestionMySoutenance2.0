<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailValidationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $validationLink;
    public $subject;

    public function __construct(string $validationLink, string $subject)
    {
        $this->validationLink = $validationLink;
        $this->subject = $subject;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    public function content(): Content
    {
        return Content::view('templates.email.generic_email_layout', [
            'main_content' => 'Veuillez cliquer sur le lien suivant pour valider votre adresse email : <a href="' . $this->validationLink . '">Valider mon email</a>',
            'subject_email' => $this->subject,
            'current_year' => now()->year,
            'app_url' => config('app.url'),
        ]);
    }

    public function attachments(): array
    {
        return [];
    }
}
