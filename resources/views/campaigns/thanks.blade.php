@extends('layouts.public')

@section('content')
    <section class="hero">
        <div class="card">
            <h2>Vielen Dank fuer Ihre Bewerbung</h2>
            <p class="hero-subtitle">Wir haben Ihre Bewerbung fuer {{ $campaign->title }} erhalten.</p>
            <p class="muted">Unser Team wird sie pruefen und sich in Kuerze melden.</p>
            <div class="pill-row">
                <span class="pill">Bewerbung erhalten</span>
                <span class="pill">Wir melden uns bald</span>
            </div>
            <a class="btn" href="{{ route('campaign.show', ['org_slug' => $organization->slug, 'campaign_slug' => $campaign->slug]) }}">Zurueck zum Job</a>
        </div>
        <div class="hero-figure">
            <span>Sie sind in guten Haenden</span>
        </div>
    </section>
@endsection
