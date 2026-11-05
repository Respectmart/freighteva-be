<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Freighteva Order Alert</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f3f4f6; margin: 0; padding: 0; color: #1f2937; }
        .wrapper { max-width: 620px; margin: 24px auto; background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.06); border: 1px solid #e5e7eb; }
        .header { background: linear-gradient(135deg, #0b1f3a 0%, #17325c 100%); padding: 32px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 800; letter-spacing: 0.5px; }
        .header p { color: #10b981; margin: 8px 0 0 0; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px; }
        .content { padding: 32px; }
        .alert-pill { display: inline-block; background-color: #ecfdf5; color: #047857; font-weight: 700; font-size: 12px; padding: 6px 14px; border-radius: 9999px; border: 1px solid #a7f3d0; margin-bottom: 20px; }
        .section-title { font-size: 15px; font-weight: 700; color: #111827; margin: 24px 0 12px 0; border-bottom: 2px solid #f3f4f6; padding-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .details-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 14px; }
        .details-table td { padding: 8px 0; vertical-align: top; }
        .details-table td.label { color: #6b7280; width: 38%; }
        .details-table td.value { font-weight: 600; color: #111827; width: 62%; }
        .highlight-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; margin: 20px 0; }
        .btn-container { text-align: center; margin: 32px 0 20px 0; }
        .btn { display: inline-block; padding: 14px 32px; background-color: #00d084; color: #0b1f3a; text-decoration: none; font-weight: 700; border-radius: 6px; font-size: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .footer { background-color: #f9fafb; padding: 24px 32px; text-align: center; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>FREIGHTEVA MARKETPLACE</h1>
            <p>New Inbound Order Received</p>
        </div>
        <div class="content">
            <span class="alert-pill">Action Required: Review & Accept Booking</span>
            
            <h2 style="margin: 0 0 12px 0; color: #0b1f3a; font-size: 20px;">
                Hello {{ $merchant->company_name ?? ($merchant->name ?? 'Merchant Partner') }},
            </h2>
            <p style="font-size: 14px; line-height: 1.6; color: #4b5563; margin-bottom: 20px;">
                You have received a new shipment order booked through the <strong>Freighteva Marketplace</strong>. This order has been matched with your shipping routes and assigned to your fulfillment queue.
            </p>

            <div class="section-title">Order Information</div>
            <table class="details-table">
                <tr>
                    <td class="label">Tracking / AWB #:</td>
                    <td class="value" style="color: #0b1f3a; font-family: monospace; font-size: 15px;">{{ $trackingNumber }}</td>
                </tr>
                <tr>
                    <td class="label">Invoice Reference:</td>
                    <td class="value">{{ $shipment->invoice_prefix }}-{{ $shipment->invoice_no }}</td>
                </tr>
                <tr>
                    <td class="label">Shipping Mode:</td>
                    <td class="value">{{ ucfirst($shipment->mode ?? 'air') }} Freight</td>
                </tr>
                <tr>
                    <td class="label">Cargo Weight:</td>
                    <td class="value">{{ $shipment->weight }} kg</td>
                </tr>
                @if(!empty($cargo['item_description']))
                <tr>
                    <td class="label">Item Description:</td>
                    <td class="value">{{ $cargo['item_description'] }} (Qty: {{ $cargo['quantity'] ?? 1 }})</td>
                </tr>
                @endif
                @if(!empty($cargo['package_type']))
                <tr>
                    <td class="label">Package Type:</td>
                    <td class="value">{{ $cargo['package_type'] }}</td>
                </tr>
                @endif
                <tr>
                    <td class="label">Scheduled Pickup:</td>
                    <td class="value">{{ $shipment->pickup_date ?? 'Standard' }} ({{ $shipment->pickup_time_slot ?? '09:00 AM - 01:00 PM' }})</td>
                </tr>
                <tr>
                    <td class="label">Payout / Value:</td>
                    <td class="value" style="color: #059669; font-size: 16px;">{{ $shipment->currency ?? 'USD' }} ${{ number_format($shipment->amount ?? $shipment->total_amount, 2) }}</td>
                </tr>
            </table>

            <div class="highlight-box">
                <div style="font-weight: 700; color: #0b1f3a; margin-bottom: 10px; font-size: 14px;">Routing & Addresses:</div>
                <div style="font-size: 13px; margin-bottom: 8px;">
                    <strong>Origin:</strong> {{ $shipment->origin }}
                    @if(!empty($sender['name']))
                        <br><span style="color: #6b7280;">Sender: {{ $sender['name'] }} ({{ $sender['phone'] ?? 'N/A' }})</span>
                    @endif
                </div>
                <div style="font-size: 13px;">
                    <strong>Destination:</strong> {{ $shipment->destination }}
                    @if(!empty($receiver['name']))
                        <br><span style="color: #6b7280;">Receiver: {{ $receiver['name'] }} ({{ $receiver['phone'] ?? 'N/A' }})</span>
                    @endif
                </div>
            </div>

            <div class="btn-container">
                <a href="{{ $portalUrl }}" class="btn">View & Accept Booking in Portal &rarr;</a>
            </div>

            <p style="font-size: 12px; color: #6b7280; text-align: center; margin: 16px 0 0 0;">
                Direct link: <a href="{{ $portalUrl }}" style="color: #0284c7;">{{ $portalUrl }}</a>
            </p>
        </div>
        <div class="footer">
            <p style="margin: 0 0 6px 0;">This is an automated notification from Freighteva Marketplace.</p>
            <p style="margin: 0;">Please log into your merchant admin panel to generate shipping labels and update transit milestones.</p>
        </div>
    </div>
</body>
</html>
