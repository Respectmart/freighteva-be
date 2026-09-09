<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Freighteva Routing API — Swagger Documentation</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui.css" />
    <link rel="icon" type="image/png" href="https://freightmata.com/new_assets/images/courier.png" />
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        .topbar {
            background: #0f172a;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .topbar-badge {
            background: #2563eb;
            color: #ffffff;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 9999px;
            text-transform: uppercase;
        }
        .swagger-ui .topbar {
            display: none !important;
        }
        .swagger-ui .info {
            margin: 24px 0 !important;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="topbar-brand">
            <span>🚢 Freighteva Global Logistics</span>
            <span class="topbar-badge">API v1.0</span>
        </div>
        <div>
            <span style="font-size: 13px; color: #94a3b8;">Location-Aware Routing & Matchmaking Engine</span>
        </div>
    </div>

    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            window.ui = SwaggerUIBundle({
                url: "{{ url('/api/docs/openapi.json') }}",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "BaseLayout"
            });
        };
    </script>
</body>
</html>
