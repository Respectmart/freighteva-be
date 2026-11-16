<?php

namespace App\Mail;

use App\Models\BookingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbandonedBookingReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public BookingSession $bookingSession;
    public string $generatedPassword;
    public string $loginUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(BookingSession $bookingSession, string $generatedPassword, ?string $loginUrl = null)
    {
        $this->bookingSession = $bookingSession;
        $this->generatedPassword = $generatedPassword;
        $this->loginUrl = $loginUrl ?: 'https://app.freightmata.com/login';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Complete Your Freighteva Booking - Preserved Rate Lock',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.abandoned_booking_reminder',
            with: [
                'customerName' => $this->bookingSession->customer_name ?: 'Valued Shipper',
                'customerEmail' => $this->bookingSession->customer_email,
                'routeFrom' => $this->bookingSession->route_from ?: 'Origin',
                'routeTo' => $this->bookingSession->route_to ?: 'Destination',
                'totalPrice' => (float) $this->bookingSession->total_price,
                'currency' => $this->bookingSession->currency ?: 'USD',
                'password' => $this->generatedPassword,
                'loginUrl' => $this->loginUrl,
            ],
        );
    }
}
