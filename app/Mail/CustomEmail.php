<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;
use App\Models\CompanyDetail;

class CustomEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $emailContent;
    public $emailSubject;
    public $company;
    public $attachments;

    /**
     * Create a new message instance.
     */
    public function __construct($content, $subject, CompanyDetail $company, $attachments = [])
    {
        $this->emailContent = $content;
        $this->emailSubject = $subject;
        $this->company = $company;
        $this->attachments = $attachments;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // Resolve an authorized "from" address; set reply-to to the company's email
        $mailerFrom = config('mail.from.address');
        $companyEmail = $this->company->business_email ?? null;
        $fromName = $this->company->name ?? config('mail.from.name');

        // Default to the mailer from address
        $fromEmail = $mailerFrom;

        // If the company's email domain matches the mailer domain, allow using it as the sender
        if (!empty($companyEmail)) {
            $companyDomain = substr(strrchr($companyEmail, '@'), 1) ?: null;
            $mailerDomain = substr(strrchr($mailerFrom, '@'), 1) ?: null;
            if ($companyDomain && $mailerDomain && strcasecmp($companyDomain, $mailerDomain) === 0) {
                $fromEmail = $companyEmail;
            }
        }

        // Use reply-to for the company email when it's different from the authorized sender
        $replyTo = [];
        if (!empty($companyEmail) && strcasecmp($companyEmail, $fromEmail) !== 0) {
            $replyTo[] = new Address($companyEmail, $fromName);
        }

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            replyTo: $replyTo,
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
        $attachments = [];
        
        if (!empty($this->attachments)) {
            foreach ($this->attachments as $attachment) {
                if (is_string($attachment) && file_exists(public_path($attachment))) {
                    $attachments[] = Attachment::fromPath(public_path($attachment));
                } elseif (is_array($attachment) && isset($attachment['path']) && file_exists(public_path($attachment['path']))) {
                    $attachments[] = Attachment::fromPath(public_path($attachment['path']))
                        ->as($attachment['name'] ?? basename($attachment['path']));
                }
            }
        }
        
        return $attachments;
    }
}
