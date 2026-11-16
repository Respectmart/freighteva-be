<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Freighteva Booking</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 600px;
            margin: 30px auto;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header {
            background: #1e293b;
            padding: 28px 32px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            font-size: 24px;
            margin: 0;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .header h1 span {
            color: #84cc16;
        }
        .content {
            padding: 32px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .message {
            font-size: 15px;
            line-height: 1.6;
            color: #475569;
            margin-bottom: 24px;
        }
        .summary-box {
            background-color: #f1f5f9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .summary-row:last-child {
            margin-bottom: 0;
        }
        .summary-label {
            color: #64748b;
        }
        .summary-value {
            font-weight: 600;
            color: #0f172a;
        }
        .credentials-card {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 28px;
        }
        .credentials-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            margin-bottom: 12px;
        }
        .cred-item {
            font-size: 14px;
            margin-bottom: 8px;
            color: #334155;
        }
        .cred-item strong {
            color: #0f172a;
            display: inline-block;
            width: 110px;
        }
        .cred-code {
            font-family: monospace;
            background: #e2e8f0;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
            color: #0f172a;
        }
        .btn-wrapper {
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            display: inline-block;
            background-color: #7a8f36;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 32px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(122, 143, 54, 0.2);
        }
        .footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 20px 32px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Freight<span>eva</span></h1>
        </div>
        <div class="content">
            <div class="greeting">Hello {{ $customerName }},</div>
            <p class="message">
                We noticed that you started a freight booking on <strong>Freighteva</strong>, but didn't get a chance to complete your verification and checkout.
            </p>

            <div class="summary-box">
                <div class="summary-row">
                    <span class="summary-label">Corridor Route:</span>
                    <span class="summary-value">{{ $routeFrom }} &rarr; {{ $routeTo }}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Estimated Total:</span>
                    <span class="summary-value">{{ $currency }} ${{ number_format($totalPrice, 2) }}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Cargo Protection:</span>
                    <span class="summary-value">Handling + All-Risk Protection (10%)</span>
                </div>
            </div>

            <p class="message">
                Your rate quote has been preserved! We've prepared access to your customer portal where you can resume your shipment, upload required customs documents, and complete verification:
            </p>

            <div class="credentials-card">
                <div class="credentials-title">Your Portal Credentials</div>
                <div class="cred-item">
                    <strong>Portal URL:</strong> <a href="{{ $loginUrl }}" target="_blank" style="color: #7a8f36;">{{ $loginUrl }}</a>
                </div>
                <div class="cred-item">
                    <strong>Login Email:</strong> <span class="cred-code">{{ $customerEmail }}</span>
                </div>
                <div class="cred-item">
                    <strong>Password:</strong> <span class="cred-code">{{ $password }}</span>
                </div>
            </div>

            <div class="btn-wrapper">
                <a href="{{ $loginUrl }}" class="btn">Log In &amp; Complete Booking &rarr;</a>
            </div>

            <p class="message" style="font-size: 13px; color: #64748b; margin-top: 24px;">
                If you did not initiate this booking or need assistance with your freight, please reply directly to this email or contact support.
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Freighteva Inc. All rights reserved.
        </div>
    </div>
</body>
</html>
