<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\CompanyDetail;

class CustomEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $emailContent;
    public $emailSubject;
    public $company;

    /**
     * Create a new message instance.
     */
    public function __construct($content, $subject, CompanyDetail $company)
    {
        $this->emailContent = $content;
        $this->emailSubject = $subject;
        $this->company = $company;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // Use company's business email if available, otherwise use default
        $fromEmail = $this->company->business_email ?? config('mail.from.address');
        $fromName = $this->company->name ?? config('mail.from.name');

        return new Envelope(
            from: $fromEmail,
            subject: $this->emailSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->emailContent,
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
