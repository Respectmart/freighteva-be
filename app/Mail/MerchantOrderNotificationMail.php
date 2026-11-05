<?php

namespace App\Mail;

use App\Models\Shipment;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MerchantOrderNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public Shipment $shipment;
    public ?Tenant $merchant;
    public array $quote;
    public array $sender;
    public array $receiver;
    public array $cargo;
    public string $trackingNumber;
    public string $portalUrl;

    public function __construct(
        Shipment $shipment,
        ?Tenant $merchant,
        array $quote = [],
        array $sender = [],
        array $receiver = [],
        array $cargo = [],
        string $trackingNumber = ''
    ) {
        $this->shipment = $shipment;
        $this->merchant = $merchant;
        $this->quote = $quote;
        $this->sender = $sender;
        $this->receiver = $receiver;
        $this->cargo = $cargo;
        $this->trackingNumber = $trackingNumber ?: ($shipment->invoice_prefix . '-' . $shipment->invoice_no);

        $portalBase = 'https://app.respectmart.com';
        if ($merchant) {
            if (!empty($merchant->custom_domain)) {
                $portalBase = 'https://' . $merchant->custom_domain;
            } elseif (!empty($merchant->subdomain)) {
                $portalBase = 'https://' . $merchant->subdomain . '.respectmart.com';
            }
        }
        $this->portalUrl = rtrim($portalBase, '/') . '/freighteva-bookings';
    }

    public function build(): static
    {
        $ref = $this->trackingNumber ?: ($this->shipment->invoice_prefix . '-' . $this->shipment->invoice_no);
        $fromAddress = config('mail.from.address', 'noreply@freighteva.com');
        $fromName = config('mail.from.name', 'Freighteva Marketplace');

        return $this->subject('New Freighteva Marketplace Order Received - #' . $ref)
            ->from($fromAddress, $fromName)
            ->view('emails.merchant_order_notification')
            ->with([
                'shipment' => $this->shipment,
                'merchant' => $this->merchant,
                'quote' => $this->quote,
                'sender' => $this->sender,
                'receiver' => $this->receiver,
                'cargo' => $this->cargo,
                'trackingNumber' => $this->trackingNumber,
                'portalUrl' => $this->portalUrl,
            ]);
    }
}
