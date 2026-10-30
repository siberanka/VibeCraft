<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class OrderContractCopy extends Mailable
{
    public function __construct(public string $orderNumber, public string $copyText, public string $messageKey, public string $documentLocale)
    {
        $this->locale($documentLocale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Order confirmation', [], $this->documentLocale).' '.$this->orderNumber);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.order-contract-copy');
    }

    public function headers(): Headers
    {
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        if (! preg_match('/^[A-Za-z0-9.-]{1,253}$/D', $host)) {
            $host = 'vibecraft.invalid';
        }

        return new Headers(messageId: 'contract-'.$this->messageKey.'@'.$host);
    }

    public function attachments(): array
    {
        $number = preg_replace('/[^A-Za-z0-9]/', '', $this->orderNumber);

        return [Attachment::fromData(fn () => $this->copyText, 'order-'.$number.'.txt')->withMime('text/plain')];
    }
}
