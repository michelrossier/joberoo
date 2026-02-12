<x-filament-panels::page>
    @php
        $totals = $this->totals;
    @endphp

    <style>
        .funnel-analytics-dashboard {
            display: flex;
            flex-direction: column;
            gap: 5.5rem;
            padding-bottom: 2.5rem;
        }

        .funnel-analytics-dashboard .analytics-hero-grid {
            gap: 3rem;
            padding-bottom: 4rem;
        }

        .funnel-analytics-dashboard .analytics-filter-grid {
            margin-top: 0.75rem;
            row-gap: 1.5rem;
        }

        .funnel-analytics-dashboard .analytics-kpi-grid {
            row-gap: 2rem;
        }

        .funnel-analytics-dashboard .analytics-kpi-card {
            padding: 2.5rem 2rem 2.5rem;
        }

        .funnel-analytics-dashboard .analytics-funnel-header {
            margin-bottom: 3rem;
        }

        .funnel-analytics-dashboard .analytics-funnel-grid {
            row-gap: 2rem;
            padding-bottom: 2.5rem;
        }

        .funnel-analytics-dashboard .analytics-tables-grid {
            row-gap: 2.5rem;
        }

        .funnel-analytics-dashboard .analytics-table-head {
            background-color: rgb(249 250 251 / 1);
        }

        :root.dark .funnel-analytics-dashboard .analytics-table-head,
        .dark .funnel-analytics-dashboard .analytics-table-head {
            background-color: rgb(17 24 39 / 0.45);
        }

        @media (min-width: 768px) {
            .funnel-analytics-dashboard .analytics-filter-grid {
                column-gap: 1.5rem;
            }

            .funnel-analytics-dashboard .analytics-kpi-grid {
                column-gap: 1.5rem;
            }

            .funnel-analytics-dashboard .analytics-tables-grid {
                column-gap: 2rem;
            }
        }
    </style>

    <div class="funnel-analytics-dashboard space-y-0 pb-10">
        <section class="mb-24 overflow-hidden rounded-3xl bg-white/95 dark:bg-gray-900/90">
            <div class="analytics-hero-grid grid gap-12 p-8 pb-16 md:p-10 md:pb-16 xl:grid-cols-3">
                <div class="xl:col-span-2">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-600 dark:text-cyan-300">Bewerbungs-Uebersicht</p>
                    <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Erweitertes Analyse-Dashboard</h2>
                    <p class="mb-8 max-w-2xl text-sm text-gray-600 dark:text-gray-300">
                        Ueberwachen Sie Besucherqualitaet, Pipeline-Konversion und Team-Durchsatz an einem Ort.
                    </p>

                    <div class="flex flex-wrap gap-3">
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
                            {{ $totals['sources'] }} Quellen
                        </span>
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
                            {{ number_format($totals['views']) }} Aufrufe
                        </span>
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
                            {{ number_format($totals['submissions']) }} Bewerbungen
                        </span>
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                            {{ number_format($totals['conversion'], 1) }}% Gesamtkonversion
                        </span>
                    </div>
                </div>

                <div class="analytics-filter-grid mt-2 grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300" for="campaign-filter">
                            Job
                        </label>
                        <select
                            id="campaign-filter"
                            wire:model.live="campaignId"
                            class="w-full rounded-xl bg-gray-100 px-3 py-2.5 text-sm text-gray-900 transition focus:outline-none focus:ring-2 focus:ring-cyan-500/25 dark:bg-gray-950 dark:text-gray-100 dark:focus:ring-cyan-400/20"
                        >
                            <option value="">Alle Jobs</option>
                            @foreach ($this->campaignOptions as $id => $title)
                                <option value="{{ $id }}">{{ $title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300" for="days-filter">
                            Zeitraum
                        </label>
                        <select
                            id="days-filter"
                            wire:model.live="days"
                            class="w-full rounded-xl bg-gray-100 px-3 py-2.5 text-sm text-gray-900 transition focus:outline-none focus:ring-2 focus:ring-cyan-500/25 dark:bg-gray-950 dark:text-gray-100 dark:focus:ring-cyan-400/20"
                        >
                            <option value="7">Letzte 7 Tage</option>
                            <option value="30">Letzte 30 Tage</option>
                            <option value="90">Letzte 90 Tage</option>
                            <option value="365">Letzte 365 Tage</option>
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <section class="analytics-kpi-grid mb-24 grid gap-8 md:grid-cols-2 xl:grid-cols-5">
            <article class="analytics-kpi-card relative overflow-hidden rounded-2xl bg-white px-8 pb-10 pt-10 dark:bg-gray-900">
                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-sky-500 to-cyan-500"></div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">Bewerbungen</p>
                <p class="mb-3 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($this->kpis['applications']) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Gesamte Eingaenge im Zeitraum</p>
            </article>

            <article class="analytics-kpi-card relative overflow-hidden rounded-2xl bg-white px-8 pb-10 pt-10 dark:bg-gray-900">
                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-500 to-violet-500"></div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Aktive Pipeline</p>
                <p class="mb-3 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($this->kpis['active']) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Nicht angenommen oder abgelehnt</p>
            </article>

            <article class="analytics-kpi-card relative overflow-hidden rounded-2xl bg-white px-8 pb-10 pt-10 dark:bg-gray-900">
                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-500"></div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Einstellungen</p>
                <p class="mb-3 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($this->kpis['hired']) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Angenommene Kandidaten</p>
            </article>

            <article class="analytics-kpi-card relative overflow-hidden rounded-2xl bg-white px-8 pb-10 pt-10 dark:bg-gray-900">
                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-amber-500 to-orange-500"></div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Durchschn. Zeit bis Pruefung</p>
                <p class="mb-3 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($this->kpis['avg_time_to_review_hours'], 1) }}h</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Von Eingang bis zur ersten Pruefung</p>
            </article>

            <article class="analytics-kpi-card relative overflow-hidden rounded-2xl bg-white px-8 pb-10 pt-10 dark:bg-gray-900">
                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-fuchsia-500 to-pink-500"></div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-fuchsia-700 dark:text-fuchsia-300">Durchschn. Zeit bis Einstellung</p>
                <p class="mb-3 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($this->kpis['avg_time_to_hire_hours'], 1) }}h</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Von Eingang bis zur Zusage</p>
            </article>
        </section>

        <section class="mb-24 overflow-hidden rounded-3xl bg-white dark:bg-gray-900">
            <div class="analytics-funnel-header mb-12 px-8 pt-8">
                <h3 class="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">Funnel-Stufen</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Qualitaet des Fortschritts ueber die Recruiting-Pipeline.</p>
            </div>
            <div class="analytics-funnel-grid grid gap-8 px-8 pb-10 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($this->stageFunnel as $stage)
                    @php
                        $conversion = max(0, min(100, (float) $stage['conversion']));
                    @endphp
                    <article class="rounded-2xl bg-gray-50/80 p-6 dark:bg-gray-950/50">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">{{ $stage['label'] }}</p>
                            <span class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-gray-700 dark:bg-white/5 dark:text-gray-300">
                                {{ number_format($conversion, 1) }}%
                            </span>
                        </div>
                        <p class="mb-4 mt-3 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($stage['count']) }}</p>
                        <div class="h-2 rounded-full bg-gray-200 dark:bg-white/10">
                            <div
                                class="h-2 rounded-full bg-gradient-to-r from-cyan-500 to-blue-500 transition-all duration-500 dark:from-cyan-400 dark:to-blue-400"
                                style="width: {{ $conversion }}%;"
                            ></div>
                        </div>
                        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Konversion von der vorherigen Stufe</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="analytics-tables-grid mb-24 grid gap-10 xl:grid-cols-2">
            <div class="overflow-hidden rounded-3xl bg-white dark:bg-gray-900">
                <div class="mb-8 px-8 pt-8">
                    <h3 class="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">Quellen-Performance</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Qualitaet der Traffic-Quellen auf Basis der Bewerbungs-Konversion.</p>
                </div>
                <div class="overflow-x-auto pb-2">
                    <table class="min-w-full">
                        <thead class="analytics-table-head">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Quelle</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Aufrufe</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Bewerbungen</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Konversion</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                @php
                                    $conversion = max(0, min(100, (float) $row['conversion']));
                                @endphp
                                <tr class="bg-white transition hover:bg-gray-50/70 dark:bg-transparent dark:hover:bg-white/[0.03]">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $row['source'] === 'direct' ? 'Direkt' : $row['source'] }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-gray-300">{{ number_format($row['views']) }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-gray-300">{{ number_format($row['submissions']) }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-gray-300">
                                        <div class="inline-flex min-w-[7rem] items-center justify-end gap-2">
                                            <span class="w-12 text-right tabular-nums">{{ number_format($conversion, 1) }}%</span>
                                            <div class="h-1.5 w-16 overflow-hidden rounded-full bg-gray-200 dark:bg-white/15">
                                                <div
                                                    class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-blue-500 dark:from-cyan-400 dark:to-blue-400"
                                                    style="width: {{ $conversion }}%;"
                                                ></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Keine Quelldaten fuer den ausgewaehlten Zeitraum.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="overflow-hidden rounded-3xl bg-white dark:bg-gray-900">
                <div class="mb-8 px-8 pt-8">
                    <h3 class="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">Recruiter-Durchsatz</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Vergleich von Auslastung und Ergebnis pro Recruiter.</p>
                </div>
                <div class="overflow-x-auto pb-2">
                    <table class="min-w-full">
                        <thead class="analytics-table-head">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Recruiter</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Zugewiesen</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Status-Updates</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Notizen</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Einstellungen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->recruiterThroughput as $row)
                                <tr class="bg-white transition hover:bg-gray-50/70 dark:bg-transparent dark:hover:bg-white/[0.03]">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $row['name'] }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-gray-300">{{ number_format($row['assigned']) }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-gray-300">{{ number_format($row['status_updates']) }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-gray-300">{{ number_format($row['notes']) }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-gray-300">
                                        <span class="font-medium">{{ number_format($row['hires']) }}</span>
                                        <span class="ml-1 inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                            {{ number_format($row['hire_rate'], 1) }}%
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Keine Recruiter-Aktivitaet fuer den ausgewaehlten Zeitraum.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl bg-white dark:bg-gray-900">
            <div class="mb-8 px-8 pt-8">
                <h3 class="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">Job-Performance</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Durchgaengige Ergebnisse je Job von Aufrufen bis Einstellungen.</p>
            </div>
            <div class="overflow-x-auto pb-2">
                <table class="min-w-full">
                    <thead class="analytics-table-head">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Job</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Aufrufe</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Bewerbungen</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Einstellungen</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Aufruf->Bewerbung</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Bewerbung->Einstellung</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->campaignPerformance as $row)
                            @php
                                $viewToSubmit = max(0, min(100, (float) $row['conversion']));
                                $submitToHire = max(0, min(100, (float) $row['hire_rate']));
                            @endphp
                            <tr class="bg-white transition hover:bg-gray-50/70 dark:bg-transparent dark:hover:bg-white/[0.03]">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $row['title'] }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-gray-300">{{ number_format($row['views']) }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-gray-300">{{ number_format($row['submissions']) }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-gray-300">{{ number_format($row['hires']) }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-gray-300">
                                    <div class="inline-flex min-w-[7rem] items-center justify-end gap-2">
                                        <span class="w-12 text-right tabular-nums">{{ number_format($viewToSubmit, 1) }}%</span>
                                        <div class="h-1.5 w-16 overflow-hidden rounded-full bg-gray-200 dark:bg-white/15">
                                            <div
                                                class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-blue-500 dark:from-cyan-400 dark:to-blue-400"
                                                style="width: {{ $viewToSubmit }}%;"
                                            ></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-gray-300">
                                    <div class="inline-flex min-w-[7rem] items-center justify-end gap-2">
                                        <span class="w-12 text-right tabular-nums">{{ number_format($submitToHire, 1) }}%</span>
                                        <div class="h-1.5 w-16 overflow-hidden rounded-full bg-gray-200 dark:bg-white/15">
                                            <div
                                                class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 dark:from-emerald-400 dark:to-teal-400"
                                                style="width: {{ $submitToHire }}%;"
                                            ></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Keine Job-Performance-Daten fuer den ausgewaehlten Zeitraum.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
