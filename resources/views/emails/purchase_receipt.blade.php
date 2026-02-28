<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RLBmods Receipt - {{ $purchase->order_id }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #0a0a0a;
            color: #f9fafb;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .wrapper {
            width: 100%;
            background-color: #0a0a0a;
            padding: 24px 0;
        }
        .container {
            width: 100%;
            max-width: 560px;
            margin: 0 auto;
            background: radial-gradient(circle at top left, rgba(178,0,3,0.18), transparent 55%) #050505;
            border-radius: 24px;
            border: 1px solid rgba(148,163,184,0.25);
            overflow: hidden;
        }
        .header {
            padding: 24px 28px 12px;
            border-bottom: 1px solid rgba(148,163,184,0.22);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .logo-mark {
            width: 40px;
            height: 40px;
            border-radius: 999px;
            background: radial-gradient(circle at 30% 0, #ff9f9f, #b20003);
            box-shadow: 0 0 0 1px rgba(248,113,113,0.4), 0 18px 35px rgba(0,0,0,0.75);
        }
        .logo-text {
            font-weight: 800;
            letter-spacing: .16em;
            font-size: 11px;
            text-transform: uppercase;
            color: #f9fafb;
        }
        .badge {
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid rgba(148,163,184,0.4);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #9ca3af;
        }
        .content {
            padding: 24px 28px 28px;
        }
        h1 {
            font-size: 22px;
            margin: 0 0 6px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .subtitle {
            margin: 0 0 18px;
            font-size: 13px;
            color: #9ca3af;
        }
        .pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(22, 163, 74, 0.12);
            color: #bbf7d0;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .12em;
            margin-bottom: 18px;
        }
        .summary-card {
            border-radius: 18px;
            border: 1px solid rgba(148,163,184,0.4);
            background: linear-gradient(135deg, rgba(15,23,42,0.9), rgba(15,23,42,0.6));
            padding: 16px 16px 12px;
            margin-bottom: 18px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        .summary-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .14em;
            color: #9ca3af;
        }
        .summary-value {
            font-size: 13px;
            font-weight: 600;
        }
        .summary-value-strong {
            font-size: 18px;
            font-weight: 800;
            color: #fbbf24;
        }
        .divider {
            border-top: 1px dashed rgba(148,163,184,0.35);
            margin: 10px 0;
        }
        .info {
            font-size: 12px;
            color: #9ca3af;
            line-height: 1.6;
        }
        .info strong {
            color: #e5e7eb;
            font-weight: 600;
        }
        .footer {
            padding: 18px 28px 8px;
            font-size: 11px;
            color: #6b7280;
            border-top: 1px solid rgba(31,41,55,0.9);
            background: radial-gradient(circle at bottom right, rgba(178,0,3,0.35), transparent 55%);
        }
        .footer a {
            color: #9ca3af;
            text-decoration: underline;
        }
        .logo-image {
            display: block;
            max-height: 40px;
        }
        @media (max-width: 600px) {
            .container {
                border-radius: 0;
            }
            .header,
            .content,
            .footer {
                padding-left: 18px;
                padding-right: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <div class="logo">
                    <div class="logo-mark">
                        @php
                            $logoUrl = config('app.url') . '/logo.png';
                        @endphp
                        <img src="{{ $logoUrl }}" alt="RLBmods" class="logo-image" onerror="this.style.display='none'">
                    </div>
                    <div class="logo-text">RLBmods</div>
                </div>
                <div class="badge">
                    Purchase Receipt
                </div>
            </div>

            <div class="content">
                <h1>Thank you for your purchase, {{ $user->name }}.</h1>
                <p class="subtitle">
                    Your order has been processed successfully. Below is a summary of your receipt.
                </p>

                <div class="pill">
                    Order&nbsp;ID:&nbsp;{{ $purchase->order_id }}
                </div>

                <div class="summary-card">
                    <div class="summary-row">
                        <div>
                            <div class="summary-label">Product</div>
                            <div class="summary-value">
                                {{ optional($product)->name ?? 'N/A' }}
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div class="summary-label">Total Paid</div>
                            <div class="summary-value-strong">
                                ${{ number_format($purchase->amount_paid, 2) }}
                            </div>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="summary-row">
                        <div>
                            <div class="summary-label">Payment Method</div>
                            <div class="summary-value">
                                {{ ucfirst(str_replace('_', ' ', $purchase->payment_method)) }}
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div class="summary-label">Date</div>
                            <div class="summary-value">
                                {{ optional($purchase->created_at)->format('M d, Y H:i') ?? now()->format('M d, Y H:i') }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="info">
                    <p>
                        <strong>What happens next?</strong><br>
                        You can manage your licenses, downloads, and updates anytime from your RLBmods dashboard.
                    </p>
                    <p>
                        If you have any questions about this receipt or believe there is an error, please reply to this
                        email and our team will be happy to help.
                    </p>
                </div>
            </div>

            <div class="footer">
                <p style="margin: 0 0 4px;">
                    This email serves as your official receipt for Order <strong>{{ $purchase->order_id }}</strong>.
                </p>
                <p style="margin: 0;">
                    &copy; {{ date('Y') }} RLBmods. All rights reserved.
                    @if(config('app.url'))
                        &mdash; <a href="{{ config('app.url') }}">{{ parse_url(config('app.url'), PHP_URL_HOST) }}</a>
                    @endif
                </p>
            </div>
        </div>
    </div>
</body>
</html>

