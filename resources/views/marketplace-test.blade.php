<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Freighteva Marketplace Engine — Interactive Test Console</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #5d8f33;
            --primary-dark: #3b5f1f;
            --primary-light: #eaf3db;
            --dark: #1b2612;
            --gray-bg: #f8faf5;
            --card-bg: #ffffff;
            --border: #e3ebd8;
            --text-main: #232b1e;
            --text-muted: #6b7762;
            --danger: #d94334;
            --warning: #f59e0b;
            --success: #10b981;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            background-color: var(--gray-bg);
            color: var(--text-main);
            padding: 30px 20px;
            line-height: 1.5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, #1f2e14 0%, #30471f 100%);
            color: #ffffff;
            padding: 28px 32px;
            border-radius: 18px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 25px rgba(27, 38, 18, 0.15);
        }

        .header h1 {
            font-size: 24px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header .badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .tabs-nav {
            display: flex;
            gap: 8px;
            background: #e9f0e1;
            padding: 6px;
            border-radius: 14px;
            margin-bottom: 24px;
            overflow-x: auto;
        }

        .tab-btn {
            background: transparent;
            border: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13.5px;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .tab-btn.active {
            background: #ffffff;
            color: var(--primary-dark);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
        }

        .card h2 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 14px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid #cedbc4;
            font-size: 14px;
            color: var(--text-main);
            background: #ffffff;
            outline: none;
            transition: border 0.2s;
        }

        .form-group input:focus, .form-group select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .btn {
            background: var(--primary);
            color: #ffffff;
            border: none;
            padding: 11px 22px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary-dark);
            border: 1.5px solid var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary-light);
        }

        .btn-danger {
            background: var(--danger);
        }

        .btn-danger:hover {
            background: #b93224;
        }

        .quotes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 18px;
            margin-top: 18px;
        }

        .quote-card {
            border: 2px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            background: #ffffff;
            position: relative;
            transition: all 0.2s;
        }

        .quote-card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 20px rgba(93, 143, 51, 0.12);
        }

        .quote-badge {
            background: var(--primary-light);
            color: var(--primary-dark);
            font-size: 11px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .quote-title {
            font-size: 17px;
            font-weight: 800;
            margin-top: 8px;
        }

        .quote-price {
            font-size: 26px;
            font-weight: 800;
            color: var(--primary-dark);
            margin: 12px 0 6px;
        }

        .quote-meta {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 14px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }

        th {
            background: #f1f6eb;
            color: var(--text-muted);
            text-align: left;
            padding: 12px 14px;
            font-weight: 700;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .status-tag {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-booked { background: #e0f2fe; color: #0369a1; }
        .status-received { background: #fef3c7; color: #92400e; }
        .status-accepted { background: #dcfce7; color: #166534; }
        .status-in_transit { background: #ede9fe; color: #6b21a8; }
        .status-delivered { background: #d1fae5; color: #065f46; }
        .status-reassigned { background: #fee2e2; color: #991b1b; }

        .timeline-step {
            display: flex;
            gap: 16px;
            position: relative;
            padding-bottom: 22px;
        }

        .timeline-step::before {
            content: '';
            position: absolute;
            left: 11px;
            top: 24px;
            bottom: 0;
            width: 2px;
            background: #d5e2c9;
        }

        .timeline-step:last-child::before {
            display: none;
        }

        .timeline-dot {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            z-index: 1;
        }

        .timeline-content h4 {
            font-size: 14.5px;
            font-weight: 700;
            color: var(--dark);
        }

        .timeline-content p {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .timeline-content time {
            font-size: 11.5px;
            color: #94a38e;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .kpi-box {
            background: #fbfdf9;
            border: 1px solid var(--border);
            padding: 16px 20px;
            border-radius: 12px;
        }

        .kpi-box span {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .kpi-box strong {
            display: block;
            font-size: 24px;
            font-weight: 800;
            color: var(--primary-dark);
            margin-top: 4px;
        }

        .output-box {
            background: #1e2518;
            color: #8be9fd;
            padding: 16px;
            border-radius: 12px;
            font-family: monospace;
            font-size: 12.5px;
            max-height: 240px;
            overflow-y: auto;
            white-space: pre-wrap;
            margin-top: 14px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h1>📦 Freighteva 2.0 Marketplace Engine</h1>
            <p style="opacity: 0.85; font-size: 13.5px; margin-top: 4px;">Interactive End-to-End Real Testing Console (Modules 1 – 6)</p>
        </div>
        <div>
            <span class="badge">Branch: abid-01</span>
            <span class="badge" style="background: var(--success); color: white;">30/30 Tests Passed</span>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="tabs-nav">
        <button class="tab-btn active" onclick="switchTab('tab-quotes')">1. 🔍 Search Quotes (M1-3)</button>
        <button class="tab-btn" onclick="switchTab('tab-booking')">2. 🛒 In-Platform Booking (M4)</button>
        <button class="tab-btn" onclick="switchTab('tab-crm')">3. 🏢 Merchant CRM & Exception Engine (M5)</button>
        <button class="tab-btn" onclick="switchTab('tab-tracking')">4. 📍 Public Tracking (M5)</button>
        <button class="tab-btn" onclick="switchTab('tab-analytics')">5. 📊 Analytics & Audit Trail (M6)</button>
    </div>

    <!-- TAB 1: Search Quotes & Partner Matching -->
    <div id="tab-quotes" class="tab-panel active">
        <div class="card">
            <h2>🔍 Step 1: Real-Time Partner Recommendation & Locked Quotes</h2>
            <p style="font-size: 13.5px; color: var(--text-muted); margin-bottom: 16px;">
                Calculates live multi-factor scoring (speed, price, trust, flexibility) across verified endorsed merchants and issues cryptographic HMAC rate-lock tokens.
            </p>

            <form id="quotes-form" onsubmit="handleFetchQuotes(event)">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Origin Country</label>
                        <select id="q-origin">
                            <option value="233" selected>United States (US)</option>
                            <option value="232">United Kingdom (UK)</option>
                            <option value="38">Canada (CA)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Destination Country</label>
                        <select id="q-dest">
                            <option value="161" selected>Nigeria (NG)</option>
                            <option value="82">Ghana (GH)</option>
                            <option value="113">Kenya (KE)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Freight Mode</label>
                        <select id="q-mode">
                            <option value="air" selected>Air Freight</option>
                            <option value="ocean">Ocean Freight</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Weight (kg)</label>
                        <input type="number" id="q-weight" value="10.0" step="0.5" min="0.1" required>
                    </div>
                </div>

                <button type="submit" class="btn" id="btn-fetch-quotes">
                    <span>⚡ Calculate Live Recommended Rates</span>
                </button>
            </form>

            <div id="quotes-results-wrapper" style="display: none; margin-top: 20px;">
                <h3 style="font-size: 15px; font-weight: 700; color: var(--dark);">Freighteva Branded Delivery Tiers:</h3>
                <div id="quotes-grid" class="quotes-grid"></div>
            </div>
        </div>
    </div>

    <!-- TAB 2: In-Platform Booking -->
    <div id="tab-booking" class="tab-panel">
        <div class="card">
            <h2>🛒 Step 2: Unified In-Platform Booking & Checkout</h2>
            <p style="font-size: 13.5px; color: var(--text-muted); margin-bottom: 16px;">
                Submits complete booking payload using the locked cryptographic token. Automatically captures customer sender/receiver profiles and generates AWB.
            </p>

            <form id="booking-form" onsubmit="handleCreateBooking(event)">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label>Cryptographic Booking Token</label>
                    <input type="text" id="b-token" placeholder="Paste or click 'Select & Book' on Tab 1" required style="font-family: monospace; font-size: 12px;">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Cargo Protection Plan</label>
                        <select id="b-protection">
                            <option value="PREMIUM" selected>Premium Protection ($20.00)</option>
                            <option value="STANDARD">Standard Protection ($10.00)</option>
                            <option value="BASIC">Basic Protection ($0.00)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sender Full Name</label>
                        <input type="text" id="b-sender-name" value="Adeola Adeleke" required>
                    </div>
                    <div class="form-group">
                        <label>Sender City & Address</label>
                        <input type="text" id="b-sender-address" value="Washington, DC" required>
                    </div>
                    <div class="form-group">
                        <label>Receiver Full Name</label>
                        <input type="text" id="b-receiver-name" value="Chinedu Okeke" required>
                    </div>
                    <div class="form-group">
                        <label>Receiver City & Address</label>
                        <input type="text" id="b-receiver-address" value="Lagos, Victoria Island" required>
                    </div>
                </div>

                <button type="submit" class="btn" id="btn-create-booking">
                    <span>💳 Confirm Booking & Capture Payment</span>
                </button>
            </form>

            <div id="booking-success-box" style="display: none; margin-top: 20px; background: #eef7e8; border: 1.5px solid var(--primary); padding: 20px; border-radius: 12px;">
                <h3 style="color: var(--primary-dark); font-size: 17px; font-weight: 800;">🎉 Booking Confirmed Successfully!</h3>
                <div style="margin-top: 10px; font-size: 14px; line-height: 1.8;">
                    <strong>Air Waybill (AWB):</strong> <span id="res-awb" style="font-family: monospace; background: white; padding: 2px 8px; border-radius: 4px; font-weight: 700;"></span><br>
                    <strong>Invoice Number:</strong> <span id="res-invoice"></span><br>
                    <strong>Carrier Assigned:</strong> <span id="res-carrier"></span><br>
                    <strong>Total Paid:</strong> <span id="res-total" style="font-weight: 800; color: var(--primary-dark);"></span>
                </div>
                <div style="margin-top: 14px; display: flex; gap: 10px;">
                    <button class="btn btn-outline" onclick="jumpToTracking()">📍 Track this AWB</button>
                    <button class="btn btn-outline" onclick="jumpToCrm()">🏢 View in Merchant CRM</button>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 3: Merchant CRM & Exception Engine -->
    <div id="tab-crm" class="tab-panel">
        <div class="card">
            <h2>🏢 Step 3: Merchant CRM Booking Fulfillment & Exception Engine</h2>
            <p style="font-size: 13.5px; color: var(--text-muted); margin-bottom: 16px;">
                Merchants manage incoming bookings. If a merchant rejects, the <strong>Exception Engine</strong> penalizes their reliability and automatically reassigns the shipment to the next endorsed partner.
            </p>

            <div style="display: flex; gap: 14px; align-items: center; margin-bottom: 18px;">
                <label style="font-weight: 700; font-size: 13px;">Select Merchant Context:</label>
                <select id="crm-merchant-id" onchange="loadMerchantBookings()" style="padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border);">
                    <option value="2" selected>Merchant #2 — Global Horizon Logistics</option>
                    <option value="1">Merchant #1 — Atlantic Direct Freight</option>
                </select>
                <button class="btn btn-outline" onclick="loadMerchantBookings()" style="padding: 8px 14px; font-size: 13px;">🔄 Refresh Queue</button>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>AWB</th>
                            <th>Route</th>
                            <th>Cargo</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>CRM Actions</th>
                        </tr>
                    </thead>
                    <tbody id="crm-table-body">
                        <tr><td colspan="7" style="text-align: center; color: var(--text-muted);">Loading bookings...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 4: Public Tracking -->
    <div id="tab-tracking" class="tab-panel">
        <div class="card">
            <h2>📍 Step 4: Real-Time Shipment Tracking Timeline</h2>
            <p style="font-size: 13.5px; color: var(--text-muted); margin-bottom: 16px;">
                Customer tracking endpoint rendering live milestone timeline, current carrier, and dynamic progress bar.
            </p>

            <div style="display: flex; gap: 10px; max-width: 500px; margin-bottom: 20px;">
                <input type="text" id="track-awb-input" placeholder="Enter AWB Number (e.g. EVA-...)" style="flex: 1; padding: 10px 14px; border-radius: 10px; border: 1px solid #cedbc4;">
                <button class="btn" onclick="handleTrackShipment()">🔎 Track</button>
            </div>

            <div id="tracking-card-box" style="display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 14px; margin-bottom: 18px;">
                    <div>
                        <span style="font-size: 12px; font-weight: 700; color: var(--text-muted);">CURRENT STATUS</span>
                        <h3 id="track-status-title" style="font-size: 20px; font-weight: 800; color: var(--primary-dark);"></h3>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 12px; font-weight: 700; color: var(--text-muted);">FULFILLMENT CARRIER</span>
                        <h4 id="track-carrier-title" style="font-size: 16px; font-weight: 700;"></h4>
                    </div>
                </div>

                <div id="tracking-timeline-box" style="margin-top: 14px;"></div>
            </div>
        </div>
    </div>

    <!-- TAB 5: Analytics & Audit Trail -->
    <div id="tab-analytics" class="tab-panel">
        <div class="card">
            <h2>📊 Executive Marketplace Analytics & Audit Logs</h2>
            <div class="kpi-grid" id="admin-kpi-grid">
                <div class="kpi-box"><span>Total Bookings</span><strong id="kpi-bookings">-</strong></div>
                <div class="kpi-box"><span>Gross GMV ($)</span><strong id="kpi-gmv">-</strong></div>
                <div class="kpi-box"><span>Conversion Rate</span><strong id="kpi-conversion">-</strong></div>
                <div class="kpi-box"><span>Reassignment Success</span><strong id="kpi-reassignment">-</strong></div>
            </div>

            <h3 style="font-size: 15px; font-weight: 700; margin: 20px 0 10px;">📜 Live Marketplace Audit Trail (Module 6)</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Actor</th>
                            <th>Event Type</th>
                            <th>Entity</th>
                            <th>Merchant ID</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody id="audit-table-body">
                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted);">Loading audit logs...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    let activeToken = '';
    let currentAwb = '';

    function switchTab(tabId) {
        document.querySelectorAll('.tab-panel').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        event.target.classList.add('active');

        if (tabId === 'tab-crm') loadMerchantBookings();
        if (tabId === 'tab-analytics') loadAnalytics();
    }

    async function handleFetchQuotes(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-fetch-quotes');
        btn.innerHTML = '⏳ Calculating...';
        btn.disabled = true;

        try {
            const res = await fetch('/api/v1/recommendations/quotes', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    origin_country_id: parseInt(document.getElementById('q-origin').value),
                    destination_country_id: parseInt(document.getElementById('q-dest').value),
                    mode: document.getElementById('q-mode').value,
                    weight_kg: parseFloat(document.getElementById('q-weight').value)
                })
            });
            const data = await res.json();
            if (data.success && data.data.recommendations.length > 0) {
                renderQuotes(data.data.recommendations);
            } else {
                alert('No routes found for this corridor.');
            }
        } catch (err) {
            alert('Error fetching rates: ' + err.message);
        } finally {
            btn.innerHTML = '⚡ Calculate Live Recommended Rates';
            btn.disabled = false;
        }
    }

    function renderQuotes(quotes) {
        const grid = document.getElementById('quotes-grid');
        grid.innerHTML = '';
        quotes.forEach(q => {
            const card = document.createElement('div');
            card.className = 'quote-card';
            card.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span class="quote-badge">${q.service_code}</span>
                    <span style="font-size: 12px; font-weight: 700; color: #10b981;">🛡️ Rate Locked (15m)</span>
                </div>
                <div class="quote-title">${q.service_name}</div>
                <div class="quote-price">$${q.calculated_price.toFixed(2)} <span style="font-size: 13px; font-weight: 500; color: var(--text-muted);">${q.currency}</span></div>
                <div class="quote-meta">
                    ⏱️ Transit: <strong>${q.transit_days}</strong><br>
                    🤝 Partner: <strong>${q.partner_attribution.company_name}</strong> (${q.partner_attribution.tier_name})<br>
                    ⭐ Score: <strong>${q.scores.composite.toFixed(1)} / 100</strong>
                </div>
                <button class="btn" style="width: 100%; justify-content: center;" onclick="selectQuoteForBooking('${q.booking_token}')">
                    🛒 Book with this Service &rarr;
                </button>
            `;
            grid.appendChild(card);
        });
        document.getElementById('quotes-results-wrapper').style.display = 'block';
    }

    function selectQuoteForBooking(token) {
        activeToken = token;
        document.getElementById('b-token').value = token;
        document.querySelectorAll('.tab-btn')[1].click();
    }

    async function handleCreateBooking(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-create-booking');
        btn.innerHTML = '⏳ Confirming Booking...';
        btn.disabled = true;

        try {
            const res = await fetch('/api/v1/bookings/create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    booking_token: document.getElementById('b-token').value,
                    protection_plan: document.getElementById('b-protection').value,
                    sender: {
                        name: document.getElementById('b-sender-name').value,
                        email: 'sender@example.com',
                        phone: '+1-202-555-0199',
                        address: document.getElementById('b-sender-address').value,
                        city: 'Washington',
                        country_code: 'US'
                    },
                    receiver: {
                        name: document.getElementById('b-receiver-name').value,
                        email: 'receiver@example.com',
                        phone: '+234-803-123-4567',
                        address: document.getElementById('b-receiver-address').value,
                        city: 'Lagos',
                        country_code: 'NG'
                    }
                })
            });
            const data = await res.json();
            if (data.success) {
                currentAwb = data.data.awb_number;
                document.getElementById('res-awb').innerText = data.data.awb_number;
                document.getElementById('res-invoice').innerText = data.data.invoice_number;
                document.getElementById('res-carrier').innerText = data.data.fulfillment_carrier;
                document.getElementById('res-total').innerText = '$' + data.data.total_paid.toFixed(2) + ' ' + data.data.currency;
                document.getElementById('booking-success-box').style.display = 'block';
            } else {
                alert('Booking failed: ' + (data.message || 'Token expired'));
            }
        } catch (err) {
            alert('Booking Error: ' + err.message);
        } finally {
            btn.innerHTML = '💳 Confirm Booking & Capture Payment';
            btn.disabled = false;
        }
    }

    function jumpToTracking() {
        document.getElementById('track-awb-input').value = currentAwb;
        document.querySelectorAll('.tab-btn')[3].click();
        handleTrackShipment();
    }

    function jumpToCrm() {
        document.querySelectorAll('.tab-btn')[2].click();
    }

    async function loadMerchantBookings() {
        const merchantId = document.getElementById('crm-merchant-id').value;
        const tbody = document.getElementById('crm-table-body');
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Loading queue...</td></tr>';

        try {
            const res = await fetch('/api/v1/merchant/bookings', {
                headers: { 'Accept': 'application/json', 'X-Merchant-ID': merchantId }
            });
            const data = await res.json();
            if (data.success && data.data.length > 0) {
                tbody.innerHTML = '';
                data.data.forEach(b => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>#${b.id}</td>
                        <td><strong>${b.awb_number}</strong></td>
                        <td>${b.origin} &rarr; ${b.destination}</td>
                        <td>${b.weight_kg} kg (${b.mode})</td>
                        <td><strong>$${b.total_amount.toFixed(2)}</strong></td>
                        <td><span class="status-tag status-${b.status}">${b.status}</span></td>
                        <td>
                            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                <button class="btn btn-outline" style="padding:4px 8px; font-size:11px;" onclick="crmAction(${b.id}, 'acknowledge')">Acknowledge</button>
                                <button class="btn btn-outline" style="padding:4px 8px; font-size:11px;" onclick="crmAction(${b.id}, 'accept')">Accept</button>
                                <button class="btn btn-outline" style="padding:4px 8px; font-size:11px;" onclick="crmStatus(${b.id}, 'in_transit')">In Transit</button>
                                <button class="btn btn-danger" style="padding:4px 8px; font-size:11px;" onclick="crmReject(${b.id})">❌ Reject</button>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No bookings found for this merchant.</td></tr>';
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="7" style="color:red; text-align:center;">Error: ${err.message}</td></tr>`;
        }
    }

    async function crmAction(id, action) {
        const merchantId = document.getElementById('crm-merchant-id').value;
        const res = await fetch(`/api/v1/merchant/bookings/${id}/${action}`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Merchant-ID': merchantId }
        });
        const data = await res.json();
        alert(data.message || 'Updated');
        loadMerchantBookings();
    }

    async function crmStatus(id, status) {
        const merchantId = document.getElementById('crm-merchant-id').value;
        const res = await fetch(`/api/v1/merchant/bookings/${id}/status`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Merchant-ID': merchantId },
            body: JSON.stringify({ status: status, location: 'International Sorting Hub' })
        });
        const data = await res.json();
        alert(data.message || 'Status updated');
        loadMerchantBookings();
    }

    async function crmReject(id) {
        const reason = prompt('Enter Rejection Reason code (CAPACITY_FULL / SCHEDULE_CONFLICT / OPERATIONAL_DELAY):', 'CAPACITY_FULL');
        if (!reason) return;
        const merchantId = document.getElementById('crm-merchant-id').value;

        const res = await fetch(`/api/v1/merchant/bookings/${id}/reject`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Merchant-ID': merchantId },
            body: JSON.stringify({ reason_code: reason, reason_notes: 'Automated test console rejection' })
        });
        const data = await res.json();
        if (data.reassigned) {
            alert(`🚨 REJECTION EXCEPTION ENGINE TRIGGERED!\n\nShipment was automatically reassigned to: ${data.reassigned_to.company_name} (Merchant ID: ${data.reassigned_to.merchant_id})!`);
        } else {
            alert(data.message || 'Rejection processed');
        }
        loadMerchantBookings();
    }

    async function handleTrackShipment() {
        const awb = document.getElementById('track-awb-input').value.trim();
        if (!awb) return;

        try {
            const res = await fetch(`/api/v1/shipments/track/${awb}`);
            const data = await res.json();
            if (data.success) {
                document.getElementById('track-status-title').innerText = data.data.current_status.toUpperCase();
                document.getElementById('track-carrier-title').innerText = data.data.carrier;

                const timelineBox = document.getElementById('tracking-timeline-box');
                timelineBox.innerHTML = '';
                data.data.timeline.forEach((item, idx) => {
                    const step = document.createElement('div');
                    step.className = 'timeline-step';
                    step.innerHTML = `
                        <div class="timeline-dot">${idx + 1}</div>
                        <div class="timeline-content">
                            <h4>${item.title} <span class="status-tag status-${item.status.toLowerCase()}" style="font-size:10px; margin-left:6px;">${item.status}</span></h4>
                            <p>${item.description || ''}</p>
                            <time>📅 ${item.timestamp} ${item.location ? '• 📍 ' + item.location : ''}</time>
                        </div>
                    `;
                    timelineBox.appendChild(step);
                });
                document.getElementById('tracking-card-box').style.display = 'block';
            } else {
                alert('No shipment found for AWB: ' + awb);
            }
        } catch (err) {
            alert('Tracking error: ' + err.message);
        }
    }

    async function loadAnalytics() {
        try {
            const res = await fetch('/api/v1/admin/analytics/overview');
            const data = await res.json();
            if (data.success) {
                const gm = data.data.gross_metrics;
                const fn = data.data.funnel;
                const ex = data.data.exception_metrics;
                document.getElementById('kpi-bookings').innerText = gm.total_bookings;
                document.getElementById('kpi-gmv').innerText = '$' + gm.gross_marketplace_volume.toLocaleString();
                document.getElementById('kpi-conversion').innerText = fn.quote_to_booking_conversion_rate + '%';
                document.getElementById('kpi-reassignment').innerText = ex.reassignment_success_rate + '%';
            }

            const auditRes = await fetch('/api/v1/admin/audit-logs?per_page=10');
            const auditData = await auditRes.json();
            if (auditData.success) {
                const tbody = document.getElementById('audit-table-body');
                tbody.innerHTML = '';
                auditData.data.data.forEach(log => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td style="font-size:12px; font-family:monospace;">${new Date(log.created_at).toLocaleTimeString()}</td>
                        <td><span class="badge" style="background:#eef7e8; color:var(--primary-dark);">${log.actor_type}</span></td>
                        <td><strong>${log.event_type}</strong></td>
                        <td>${log.entity_type} ${log.entity_id ? '#' + log.entity_id : ''}</td>
                        <td>${log.tenant_id ? 'Merchant #' + log.tenant_id : 'System'}</td>
                        <td style="font-size:11.5px; max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${JSON.stringify(log.payload)}</td>
                    `;
                    tbody.appendChild(tr);
                });
            }
        } catch (err) {
            console.error(err);
        }
    }
</script>

</body>
</html>
