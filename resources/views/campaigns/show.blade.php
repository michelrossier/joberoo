@extends('layouts.public')

@section('content')
    <section class="hero">
        <div class="hero-copy">
            <div>
                <span class="eyebrow">Karrierechance</span>
                <h2>{{ $campaign->title }}</h2>
                @if ($campaign->subtitle)
                    <p class="hero-subtitle">{{ $campaign->subtitle }}</p>
                @endif
                <p>{{ $campaign->description }}</p>
            </div>

            <div class="pill-row">
                @if ($campaign->location)
                    <span class="pill">{{ $campaign->location }}</span>
                @endif
                @if ($campaign->employment_type)
                    <span class="pill">{{ $campaign->employment_type }}</span>
                @endif
                @if ($campaign->salary_range)
                    <span class="pill">{{ $campaign->salary_range }}</span>
                @endif
            </div>

            <div class="metrics">
                <div class="metric">
                    <strong>Schnell</strong>
                    <span>2-Wochen-Prozess</span>
                </div>
                <div class="metric">
                    <strong>Remote</strong>
                    <span>Verteiltes Team</span>
                </div>
                <div class="metric">
                    <strong>Wirkung</strong>
                    <span>Sie praegen das Erlebnis</span>
                </div>
            </div>

            <div class="highlight">
                Wir schaetzen saubere Prozesse, klare Kommunikation und hohe Qualitaet in jeder Interaktion. Sie praegen das Bewerbungserlebnis vom ersten Kontakt bis zum Angebot.
            </div>

            <div class="hero-figure">
                @if ($campaign->hero_image_path)
                    <img src="{{ Storage::disk('public')->url($campaign->hero_image_path) }}" alt="{{ $campaign->title }}">
                @else
                    <span>Werden Sie Teil des Teams</span>
                @endif
            </div>
        </div>

        <div class="hero-form">
            <div class="card">
                <h3 class="section-title">{{ $campaign->cta_text ?? 'Jetzt bewerben' }}</h3>

                @if ($errors->any())
                    <div class="alert">
                        <strong>Bitte korrigieren Sie Folgendes:</strong>
                        <ul class="helper-list">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('campaign.apply', ['org_slug' => $organization->slug, 'campaign_slug' => $campaign->slug]) }}" enctype="multipart/form-data">
                    @csrf
                    <label for="first_name">Vorname</label>
                    <input id="first_name" name="first_name" value="{{ old('first_name') }}" required>

                    <label for="last_name">Nachname</label>
                    <input id="last_name" name="last_name" value="{{ old('last_name') }}" required>

                    <label for="email">E-Mail</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required>

                    <label for="phone">Telefon</label>
                    <input id="phone" name="phone" value="{{ old('phone') }}">

                    <label for="linkedin_url">LinkedIn-URL</label>
                    <input id="linkedin_url" name="linkedin_url" value="{{ old('linkedin_url') }}">

                    <label for="portfolio_url">Portfolio-URL</label>
                    <input id="portfolio_url" name="portfolio_url" value="{{ old('portfolio_url') }}">

                    <label for="cover_letter_text">Anschreiben</label>
                    <textarea id="cover_letter_text" name="cover_letter_text">{{ old('cover_letter_text') }}</textarea>

                    <label for="resume">Lebenslauf (PDF/DOC/DOCX)</label>
                    <input id="resume" type="file" name="resume">

                    <label for="cover_letter">Anschreiben-Datei (PDF/DOC/DOCX)</label>
                    <input id="cover_letter" type="file" name="cover_letter">

                    <button class="btn" type="submit">{{ $campaign->cta_text ?? 'Jetzt bewerben' }}</button>
                    <p class="helper">Dateien werden sicher gespeichert und vom Recruiting-Team geprueft.</p>
                </form>
            </div>
        </div>
    </section>

    <section class="grid">
        <div class="card">
            <h3 class="section-title">Rollenuebersicht</h3>
            <p class="muted">{{ $campaign->description }}</p>
            <p class="muted">Sie arbeiten eng mit Recruiting und Engineering zusammen, um einen reibungslosen Ablauf von Job bis Einstellung zu liefern. Rechnen Sie mit hoher Verantwortung, schnellen Iterationen und direkter Wirkung.</p>
        </div>

        <div class="card">
            <h3 class="section-title">So sieht Erfolg aus</h3>
            <p class="muted">Sie fuehren cross-funktionale Discovery, liefern woechentlich durchdachte Iterationen und heben das Bewerbungserlebnis an jedem Touchpoint an.</p>
            <div class="pill-row">
                <span class="pill">Hohe Verantwortung</span>
                <span class="pill">Direkte Wirkung</span>
                <span class="pill">Schnelles Feedback</span>
            </div>
        </div>
    </section>
@endsection
