<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $campaign->title }} - {{ $organization->name }}</title>
    <style>
        @import url('https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap');

        :root {
            --primary: {{ $campaign->primary_color ?? '#1d4ed8' }};
            --canvas: #f2f5fb;
            --surface: #ffffff;
            --surface-alt: #f8fafc;
            --text: #0f172a;
            --muted: #51607b;
            --border: #dce3ef;
            --ring: rgba(29, 78, 216, 0.2);
            --shadow-soft: 0 18px 36px rgba(15, 23, 42, 0.08);
            --shadow-strong: 0 30px 70px rgba(15, 23, 42, 0.14);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Figtree", "Segoe UI", sans-serif;
            color: var(--text);
            line-height: 1.55;
            background: radial-gradient(circle at 8% -10%, rgba(59, 130, 246, 0.18), transparent 45%),
                        radial-gradient(circle at 100% 0%, rgba(29, 78, 216, 0.12), transparent 35%),
                        radial-gradient(circle at 90% 95%, rgba(14, 165, 233, 0.14), transparent 35%),
                        var(--canvas);
        }

        .shell {
            max-width: 1240px;
            margin: 0 auto;
            padding: 30px 24px 70px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 8px 0 28px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .logo {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), #0ea5e9);
            box-shadow: 0 14px 28px rgba(29, 78, 216, 0.26);
        }

        .brand h1 {
            font-size: 19px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin: 0;
        }

        .status-pill {
            padding: 7px 14px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.75);
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 600;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin: 0 0 16px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 28px;
            box-shadow: var(--shadow-soft);
        }

        .pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-top: 16px;
        }

        .pill {
            padding: 7px 13px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: var(--surface-alt);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.02em;
            color: var(--muted);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: 999px;
            padding: 13px 24px;
            background: var(--primary);
            color: #fff;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 14px 26px rgba(29, 78, 216, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 34px rgba(29, 78, 216, 0.34);
        }

        input, textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 13px;
            font-family: "Figtree", "Segoe UI", sans-serif;
            font-size: 14px;
            background: #fff;
            color: var(--text);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--ring);
        }

        input[type="file"]::file-selector-button {
            margin-right: 10px;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 7px 11px;
            background: var(--surface-alt);
            color: var(--text);
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
        }

        label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            letter-spacing: 0.02em;
            margin: 14px 0 6px;
        }

        textarea { min-height: 120px; resize: vertical; }

        .helper {
            font-size: 12px;
            color: var(--muted);
            margin-top: 10px;
        }

        .helper-list {
            margin: 8px 0 0;
            padding-left: 16px;
        }

        .alert {
            border-radius: 12px;
            border: 1px solid #fecaca;
            background: #fff1f2;
            color: #9f1239;
            padding: 12px 14px;
            font-size: 13px;
            margin-bottom: 14px;
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(340px, 0.9fr);
            gap: 30px;
            align-items: start;
            margin-top: 12px;
        }

        .hero-copy {
            display: grid;
            gap: 20px;
        }

        .hero-form {
            position: sticky;
            top: 24px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 26px;
        }

        .hero h2 {
            font-size: 44px;
            line-height: 1.1;
            letter-spacing: -0.02em;
            margin: 0;
            max-width: 16ch;
        }

        .hero p {
            margin: 10px 0 0;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.75;
        }

        .hero-subtitle {
            font-size: 18px;
            color: #243b63;
            margin-top: 14px;
            max-width: 58ch;
            font-weight: 500;
        }

        .eyebrow {
            display: inline-flex;
            margin-bottom: 14px;
            padding: 7px 12px;
            border-radius: 999px;
            border: 1px solid var(--border);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #334e7d;
            background: rgba(255, 255, 255, 0.8);
        }

        .hero-figure {
            border-radius: 18px;
            overflow: hidden;
            border: 1px solid var(--border);
            min-height: 220px;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.06), rgba(29, 78, 216, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            box-shadow: var(--shadow-soft);
            color: #334155;
            font-weight: 600;
        }

        .hero-figure img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hero-figure::after {
            content: "";
            position: absolute;
            inset: 10px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.55);
        }

        .metrics {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 20px;
        }

        .metric {
            padding: 16px;
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid var(--border);
            border-radius: 14px;
            text-align: center;
            font-size: 12px;
            color: var(--muted);
        }

        .metric strong {
            display: block;
            font-size: 17px;
            margin-bottom: 4px;
            color: var(--text);
            font-weight: 700;
        }

        .highlight {
            border: 1px solid var(--border);
            background: linear-gradient(120deg, rgba(255, 255, 255, 0.95), rgba(237, 242, 252, 0.9));
            border-radius: 14px;
            padding: 16px 16px;
            font-size: 14px;
            color: #29406a;
            margin-top: 20px;
            line-height: 1.65;
        }

        .muted {
            color: var(--muted);
            font-size: 15px;
            margin-top: 14px;
            line-height: 1.75;
        }

        footer {
            margin-top: 36px;
            color: var(--muted);
            font-size: 12px;
            text-align: center;
        }

        @keyframes rise-in {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero-copy > * {
            animation: rise-in 0.35s ease both;
        }

        .hero-copy > *:nth-child(2) { animation-delay: 0.05s; }
        .hero-copy > *:nth-child(3) { animation-delay: 0.1s; }
        .hero-copy > *:nth-child(4) { animation-delay: 0.15s; }
        .hero-form .card { animation: rise-in 0.35s ease 0.08s both; }

        @media (prefers-reduced-motion: reduce) {
            * { animation: none !important; transition: none !important; }
        }

        @media (max-width: 960px) {
            .hero { grid-template-columns: 1fr; }
            .hero-form { position: static; }
            .grid { grid-template-columns: 1fr; }
            .metrics { grid-template-columns: 1fr 1fr; }
            .hero h2 { max-width: none; }
        }

        @media (max-width: 600px) {
            .shell { padding: 20px 16px 50px; }
            .card { padding: 20px; border-radius: 16px; }
            .hero h2 { font-size: 32px; }
            .hero-subtitle { font-size: 16px; }
            .metrics { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    @php
        $statusLabel = $campaign->status instanceof \App\Enums\CampaignStatus
            ? $campaign->status->label()
            : (\App\Enums\CampaignStatus::tryFrom($campaign->status)?->label() ?? $campaign->status);
    @endphp

    <div class="shell">
        <header class="topbar">
            <div class="brand">
                <div class="logo"></div>
                <h1>{{ $organization->name }}</h1>
            </div>
            <span class="status-pill">{{ $statusLabel }}</span>
        </header>

        @yield('content')

        <footer>
            <p>{{ $organization->name }} - {{ now()->year }}</p>
        </footer>
    </div>
</body>
</html>
