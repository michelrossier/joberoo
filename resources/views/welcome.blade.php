<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recruiteroo | Bewerbungsfunnel-Plattform fuer wachsende Teams</title>
    <style>
        @import url('https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap');

        :root {
            --bg: #f4f7fb;
            --surface: #ffffff;
            --surface-soft: #f8fbff;
            --text: #0a1c36;
            --muted: #57657f;
            --border: #d8e2ee;
            --accent: #0369a1;
            --accent-strong: #0f4eb8;
            --accent-soft: rgba(14, 165, 233, 0.12);
            --success: #047857;
            --warning: #b45309;
            --danger: #b91c1c;
            --shadow-sm: 0 18px 36px rgba(10, 28, 54, 0.08);
            --shadow-lg: 0 36px 80px rgba(10, 28, 54, 0.13);
            --radius-lg: 24px;
            --radius-md: 16px;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #081425;
                --surface: #0d1d35;
                --surface-soft: #10253f;
                --text: #e7f0ff;
                --muted: #a8b5cc;
                --border: #213756;
                --accent: #38bdf8;
                --accent-strong: #60a5fa;
                --accent-soft: rgba(56, 189, 248, 0.18);
                --success: #34d399;
                --warning: #f59e0b;
                --danger: #fca5a5;
                --shadow-sm: 0 20px 36px rgba(3, 8, 19, 0.45);
                --shadow-lg: 0 40px 90px rgba(3, 8, 19, 0.6);
            }
        }

        * { box-sizing: border-box; }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: "Manrope", "Segoe UI", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 12% -20%, rgba(56, 189, 248, 0.26), transparent 45%),
                radial-gradient(circle at 92% 2%, rgba(20, 184, 166, 0.22), transparent 30%),
                radial-gradient(circle at 88% 82%, rgba(14, 116, 144, 0.16), transparent 32%),
                var(--bg);
            line-height: 1.6;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .container {
            width: min(1180px, calc(100% - 2rem));
            margin: 0 auto;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(8px);
            background: color-mix(in srgb, var(--bg) 90%, transparent);
            border-bottom: 1px solid color-mix(in srgb, var(--border) 65%, transparent);
        }

        .topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            min-height: 74px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: .75rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            font-size: 1rem;
        }

        .brand-mark {
            width: 40px;
            height: 40px;
            border-radius: 13px;
            background: linear-gradient(145deg, #0ea5e9, #2563eb);
            box-shadow: 0 14px 28px rgba(14, 165, 233, 0.35);
        }

        .top-links {
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .top-links a {
            font-size: .9rem;
            font-weight: 600;
            color: var(--muted);
            padding: .5rem .8rem;
            border-radius: 999px;
            transition: background-color .2s ease, color .2s ease;
        }

        .top-links a:hover {
            background: var(--accent-soft);
            color: var(--text);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            padding: .78rem 1.1rem;
            border-radius: 12px;
            border: 1px solid transparent;
            font-size: .93rem;
            font-weight: 700;
            letter-spacing: 0.01em;
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease, background-color .2s ease;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(145deg, var(--accent), var(--accent-strong));
            color: #ffffff;
            box-shadow: 0 14px 28px rgba(15, 78, 184, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 34px rgba(15, 78, 184, 0.35);
        }

        .btn-ghost {
            border-color: var(--border);
            color: var(--text);
            background: color-mix(in srgb, var(--surface) 84%, transparent);
        }

        .btn-ghost:hover {
            border-color: var(--accent);
            background: var(--accent-soft);
        }

        .hero {
            padding: 4rem 0 2.6rem;
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(290px, .92fr);
            gap: 2rem;
            align-items: start;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .35rem .75rem;
            border-radius: 999px;
            border: 1px solid var(--border);
            font-size: .76rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--accent);
            background: color-mix(in srgb, var(--surface) 92%, transparent);
        }

        .hero h1 {
            margin: 1.1rem 0 .95rem;
            font-size: clamp(2rem, 4.2vw, 3.6rem);
            line-height: 1.05;
            letter-spacing: -0.03em;
            max-width: 16ch;
        }

        .hero p {
            margin: 0;
            font-size: clamp(1rem, 1.4vw, 1.1rem);
            color: var(--muted);
            max-width: 52ch;
        }

        .hero-cta {
            display: flex;
            flex-wrap: wrap;
            gap: .7rem;
            margin-top: 1.5rem;
        }

        .metric-grid {
            margin-top: 1.6rem;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
        }

        .metric {
            background: color-mix(in srgb, var(--surface) 90%, transparent);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: .95rem .85rem;
            box-shadow: var(--shadow-sm);
        }

        .metric strong {
            display: block;
            font-size: 1.1rem;
            line-height: 1.1;
            margin-bottom: .2rem;
        }

        .metric span {
            color: var(--muted);
            font-size: .78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .signup-card {
            position: sticky;
            top: 92px;
            background: color-mix(in srgb, var(--surface) 95%, transparent);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.35rem;
            box-shadow: var(--shadow-lg);
            animation: rise .5s ease-out both;
        }

        .signup-card h2 {
            margin: 0;
            font-size: 1.2rem;
            line-height: 1.2;
        }

        .signup-card p {
            margin: .55rem 0 0;
            color: var(--muted);
            font-size: .9rem;
        }

        .form-grid {
            display: grid;
            gap: .85rem;
            margin-top: 1rem;
        }

        label {
            display: block;
            margin: 0 0 .35rem;
            font-size: .76rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--muted);
        }

        input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: .8rem .85rem;
            font: inherit;
            font-size: .95rem;
            color: var(--text);
            background: var(--surface-soft);
            transition: border-color .2s ease, box-shadow .2s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 25%, transparent);
        }

        .field-error {
            margin-top: .3rem;
            color: var(--danger);
            font-size: .78rem;
            font-weight: 600;
        }

        .form-note {
            margin-top: .6rem;
            color: var(--muted);
            font-size: .78rem;
        }

        .alert {
            border-radius: 12px;
            border: 1px solid color-mix(in srgb, var(--danger) 55%, transparent);
            background: color-mix(in srgb, var(--danger) 14%, transparent);
            color: var(--danger);
            padding: .78rem .85rem;
            font-size: .82rem;
            margin-bottom: .55rem;
        }

        .section {
            margin-top: 2.2rem;
        }

        .section h3 {
            margin: 0;
            font-size: clamp(1.3rem, 2.2vw, 1.95rem);
            letter-spacing: -0.02em;
        }

        .section-sub {
            margin: .5rem 0 0;
            color: var(--muted);
            max-width: 66ch;
            font-size: .98rem;
        }

        .benefits {
            margin-top: 1.1rem;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .9rem;
        }

        .feature-list {
            margin-top: 1.1rem;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .9rem;
        }

        .card {
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            background: color-mix(in srgb, var(--surface) 95%, transparent);
            padding: 1rem;
            box-shadow: var(--shadow-sm);
            animation: rise .5s ease-out both;
        }

        .card:nth-child(2) { animation-delay: .06s; }
        .card:nth-child(3) { animation-delay: .12s; }
        .card:nth-child(4) { animation-delay: .18s; }
        .card:nth-child(5) { animation-delay: .24s; }
        .card:nth-child(6) { animation-delay: .3s; }

        .card .icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: var(--accent-strong);
            background: var(--accent-soft);
            margin-bottom: .7rem;
        }

        .card h4 {
            margin: 0;
            font-size: 1rem;
            letter-spacing: -0.01em;
        }

        .card p {
            margin: .5rem 0 0;
            color: var(--muted);
            font-size: .9rem;
        }

        .steps {
            margin-top: 1rem;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .9rem;
        }

        .step-index {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 999px;
            background: color-mix(in srgb, var(--accent) 20%, transparent);
            color: var(--accent-strong);
            font-weight: 800;
            font-size: .85rem;
            margin-bottom: .65rem;
        }

        .cta-strip {
            margin: 2.4rem 0 3rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--accent) 15%, transparent), transparent 40%),
                color-mix(in srgb, var(--surface) 94%, transparent);
            box-shadow: var(--shadow-lg);
            padding: 1.45rem;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 1rem;
        }

        .cta-strip h3 {
            margin: 0;
            font-size: clamp(1.2rem, 1.8vw, 1.6rem);
            letter-spacing: -0.02em;
        }

        .cta-strip p {
            margin: .45rem 0 0;
            color: var(--muted);
            font-size: .95rem;
        }

        footer {
            padding: 0 0 2.1rem;
            color: var(--muted);
            font-size: .82rem;
            display: flex;
            justify-content: space-between;
            gap: .8rem;
            flex-wrap: wrap;
        }

        @keyframes rise {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1080px) {
            .hero {
                grid-template-columns: 1fr;
            }

            .signup-card {
                position: static;
            }
        }

        @media (max-width: 860px) {
            .benefits,
            .feature-list,
            .steps {
                grid-template-columns: 1fr 1fr;
            }

            .metric-grid {
                grid-template-columns: 1fr 1fr 1fr;
            }

            .top-links a.link-only {
                display: none;
            }
        }

        @media (max-width: 640px) {
            .topbar-inner {
                min-height: 64px;
            }

            .brand-mark {
                width: 34px;
                height: 34px;
                border-radius: 11px;
            }

            .hero {
                padding-top: 2.8rem;
            }

            .metric-grid,
            .benefits,
            .feature-list,
            .steps {
                grid-template-columns: 1fr;
            }

            .cta-strip {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="container topbar-inner">
            <a href="{{ route('home') }}" class="brand">
                <span class="brand-mark" aria-hidden="true"></span>
                <span>Recruiteroo</span>
            </a>
            <nav class="top-links">
                <a class="link-only" href="#benefits">Vorteile</a>
                <a class="link-only" href="#features">Funktionen</a>
                <a href="/admin/login">Anmelden</a>
                <a href="#signup" class="btn btn-primary">Kostenlos starten</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="hero">
            <div>
                <span class="hero-badge">Fuer Recruiting-Teams</span>
                <h1>Bauen Sie Ihren Bewerbungsfunnel auf und besetzen Sie Stellen schneller.</h1>
                <p>
                    Recruiteroo bietet Ihrem Unternehmen einen zentralen Arbeitsbereich fuer Stellenkampagnen,
                    Bewerbungen und Recruiting-Analysen. Statt Tool-Chaos erhalten Sie einen skalierbaren,
                    wiederholbaren Einstellungsprozess.
                </p>

                <div class="hero-cta">
                    <a href="#signup" class="btn btn-primary">Firmenkonto erstellen</a>
                    <a href="/admin/login" class="btn btn-ghost">Ich habe bereits ein Konto</a>
                </div>

                <div class="metric-grid">
                    <article class="metric">
                        <strong>4-Spalten-Kanban</strong>
                        <span>Kandidaten per Status verschieben</span>
                    </article>
                    <article class="metric">
                        <strong>Job-Einblicke</strong>
                        <span>Aufrufe und Bewerbungen nachverfolgen</span>
                    </article>
                    <article class="metric">
                        <strong>Mandantenfaehig</strong>
                        <span>Sicherer Firmen-Arbeitsbereich</span>
                    </article>
                </div>
            </div>

            <aside class="signup-card" id="signup">
                <h2>Starten Sie Ihren Firmen-Arbeitsbereich</h2>
                <p>Erstellen Sie Ihr Konto und starten Sie in wenigen Minuten Ihre erste Stellenkampagne.</p>

                @if ($errors->any())
                    <div class="alert">
                        Bitte korrigieren Sie die markierten Felder und versuchen Sie es erneut.
                    </div>
                @endif

                <form method="POST" action="{{ route('company-signup.store') }}" class="form-grid" novalidate>
                    @csrf

                    <div>
                        <label for="company_name">Firmenname</label>
                        <input id="company_name" name="company_name" type="text" value="{{ old('company_name') }}" required autocomplete="organization">
                        @error('company_name')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="name">Ihr vollstaendiger Name</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required autocomplete="name">
                        @error('name')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email">Geschaeftliche E-Mail</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
                        @error('email')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password">Passwort</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password">
                        @error('password')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation">Passwort bestaetigen</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary">Firmenkonto erstellen</button>
                    <p class="form-note">Keine Kreditkarte erforderlich. Sie koennen Ihr Recruiting-Team direkt nach der Registrierung einladen.</p>
                </form>
            </aside>
        </section>

        <section class="section" id="benefits">
            <h3>Warum Teams zu Recruiteroo wechseln</h3>
            <p class="section-sub">
                Von schnell wachsenden Start-ups bis zu etablierten HR-Teams:
                Recruiteroo bringt Struktur, Tempo und Transparenz in jede offene Stelle.
            </p>

            <div class="benefits">
                <article class="card">
                    <div class="icon">01</div>
                    <h4>Eigene gebrandete Jobseiten</h4>
                    <p>Veroeffentlichen Sie Job-Landingpages schnell und ordnen Sie alle Bewerbungen sauber dem richtigen Job zu.</p>
                </article>
                <article class="card">
                    <div class="icon">02</div>
                    <h4>Pipeline in Bewegung halten</h4>
                    <p>Mit Drag-and-Drop-Spalten aktualisiert Ihr Recruiting-Team Status ohne Reibung.</p>
                </article>
                <article class="card">
                    <div class="icon">03</div>
                    <h4>Erkennen, was wirklich konvertiert</h4>
                    <p>Messen Sie Besucher, Bewerbungen und Konversionsraten, um Ihren Recruiting-ROI zu verbessern.</p>
                </article>
            </div>
        </section>

        <section class="section" id="features">
            <h3>Alles, was Sie fuer operative Recruiting-Prozesse brauchen</h3>
            <p class="section-sub">
                Recruiteroo vereint Jobmanagement, Kandidaten-Tracking und Analysen in einem schlanken Backend.
            </p>

            <div class="feature-list">
                <article class="card">
                    <div class="icon">A</div>
                    <h4>Jobmanagement</h4>
                    <p>Erstellen und bearbeiten Sie Stellenkampagnen mit klaren Status-, Performance- und Konversionsmetriken.</p>
                </article>
                <article class="card">
                    <div class="icon">B</div>
                    <h4>Kanban-Board fuer Bewerbungen</h4>
                    <p>Pruefen Sie eingehende Bewerbungen und bewegen Sie Kandidaten durch jede Recruiting-Phase.</p>
                </article>
                <article class="card">
                    <div class="icon">C</div>
                    <h4>Aktivitaetsverlauf</h4>
                    <p>Verfolgen Sie Statusaenderungen, Notizen und Zuweisungen fuer maximale Nachvollziehbarkeit.</p>
                </article>
                <article class="card">
                    <div class="icon">D</div>
                    <h4>Dateiverwaltung</h4>
                    <p>Speichern Sie Lebenslaeufe und Anschreiben in einer einheitlichen, recruitingfreundlichen Bewerbungsansicht.</p>
                </article>
                <article class="card">
                    <div class="icon">E</div>
                    <h4>Erweitertes Analyse-Dashboard</h4>
                    <p>Behalten Sie Funnel-Performance, Team-Durchsatz und Top-Jobs jederzeit im Blick.</p>
                </article>
                <article class="card">
                    <div class="icon">F</div>
                    <h4>Mandantenfaehige Architektur</h4>
                    <p>Jede Organisation arbeitet in einem eigenen, sicheren Mandanten mit rollenbasierten Zugriffsrechten.</p>
                </article>
            </div>
        </section>

        <section class="section">
            <h3>So funktioniert es</h3>
            <p class="section-sub">
                Konto einmal aufsetzen und sofort Stellenkampagnen veroeffentlichen.
            </p>

            <div class="steps">
                <article class="card">
                    <span class="step-index">1</span>
                    <h4>Arbeitsbereich erstellen</h4>
                    <p>Registrieren Sie Ihr Unternehmen und erhalten Sie sofort einen Admin-Mandanten fuer Ihr Recruiting-Team.</p>
                </article>
                <article class="card">
                    <span class="step-index">2</span>
                    <h4>Jobs veroeffentlichen</h4>
                    <p>Veroeffentlichen Sie Stellen und teilen Sie Ihren Job-Link ueber alle Kanaele hinweg.</p>
                </article>
                <article class="card">
                    <span class="step-index">3</span>
                    <h4>Steuern und optimieren</h4>
                    <p>Verschieben Sie Bewerber durch die Spalten und verbessern Sie Konversion sowie Einstellungsdauer mit Daten.</p>
                </article>
            </div>
        </section>

        <section class="cta-strip">
            <div>
                <h3>Bereit fuer eine planbare Recruiting-Pipeline?</h3>
                <p>Starten Sie heute mit Ihrem ersten Job und zentralisieren Sie jede Bewerbung in einem Ablauf.</p>
            </div>
            <a href="#signup" class="btn btn-primary">Mit Recruiteroo starten</a>
        </section>

        <footer>
            <span>Recruiteroo</span>
            <span>Entwickelt fuer moderne Recruiting-Teams</span>
        </footer>
    </main>
</body>
</html>
