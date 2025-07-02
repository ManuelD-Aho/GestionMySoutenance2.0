<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public $login;
    public $newPassword;
    public $subject;

    public function __construct(string $login, string $newPassword, string $subject)
    {
        $this->login = $login;
        $this->newPassword = $newPassword;
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
            'main_content' => 'Votre mot de passe a été réinitialisé par un administrateur. Votre nouveau mot de passe est : <strong>' . $this->newPassword . '</strong>. Veuillez le changer dès votre première connexion.',
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
